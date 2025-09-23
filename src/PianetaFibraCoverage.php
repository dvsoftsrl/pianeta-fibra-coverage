<?php

namespace DvSoft\PianetaFibraCoverage;

use DvSoft\PianetaFibraCoverage\DTO\CityMatch;
use DvSoft\PianetaFibraCoverage\DTO\CoverageParams;
use DvSoft\PianetaFibraCoverage\DTO\CoverageProfile;
use DvSoft\PianetaFibraCoverage\DTO\CoverageResult;
use DvSoft\PianetaFibraCoverage\DTO\HouseNumberMatch;
use DvSoft\PianetaFibraCoverage\DTO\LabeledMatch;
use DvSoft\PianetaFibraCoverage\DTO\Location;
use DvSoft\PianetaFibraCoverage\DTO\ResolveOutcome;
use DvSoft\PianetaFibraCoverage\DTO\StreetMatch;
use DvSoft\PianetaFibraCoverage\Enums\CustomerType;
use DvSoft\PianetaFibraCoverage\Exceptions\AmbiguityException;
use DvSoft\PianetaFibraCoverage\Exceptions\ApiException;
use DvSoft\PianetaFibraCoverage\Exceptions\AuthException;
use DvSoft\PianetaFibraCoverage\Exceptions\NotFoundException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

use function array_filter;

class PianetaFibraCoverage
{
    private const DEFAULT_BASE_URI = 'https://api.pianetafibra.it/v2/api.php';

    public function __construct(
        private readonly string $bearerToken,
        private readonly ?string $baseUri = self::DEFAULT_BASE_URI,
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxRetries = 2,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function resolveCoverageFromLocation(Location $loc, CustomerType $customerType, ?bool $matchOrFail = null): ResolveOutcome
    {
        $matchOrFail ??= config('pianeta-fibra-coverage.default_match_or_fail', true);
        $cities = $this->searchCities($loc->city);
        try {
            $city = $this->selectOne($loc->city, $cities, $matchOrFail, 'city');
        } catch (AmbiguityException $e) {
            return ResolveOutcome::ambiguous('city', $cities);
        } catch (NotFoundException $e) {
            return ResolveOutcome::notFound('city');
        }

        $streets = $this->searchStreets($city->egonCityId, $loc->street);
        try {
            $street = $this->selectOne($loc->street, $streets, $matchOrFail, 'street');
        } catch (AmbiguityException $e) {
            return ResolveOutcome::ambiguous('street', $streets);
        } catch (NotFoundException $e) {
            return ResolveOutcome::notFound('street');
        }

        $hnums = $this->searchHouseNumbers($street?->egonStreetId, $loc->houseNumber);
        try {
            $hnum = $this->selectOne($loc->houseNumber, $hnums, $matchOrFail, 'housenumber');
        } catch (AmbiguityException $e) {
            return ResolveOutcome::ambiguous('housenumber', $hnums);
        } catch (NotFoundException $e) {
            return ResolveOutcome::notFound('housenumber');
        }

        $coverage = $this->getCoverage(new CoverageParams(
            egonCityId: $city->egonCityId,
            egonStreetId: $street->egonStreetId,
            egonHouseNumberId: $hnum->egonHouseNumberId,
            street: $street->label,
            houseNumber: $hnum->label,
            city: $city->label,
            zip: $loc->zip,
            province: $loc->province,
            region: $loc->region,
            customerType: $customerType,
        ));

        return ResolveOutcome::resolved($coverage);
    }

    /** @return CityMatch[] */
    public function searchCities(string $city, bool $useCache = true): array
    {
        $payload = ['endpoint' => 'city', 'city' => $city];
        $cacheKey = $this->key('city', $city);

        return $this->getOrFetch($cacheKey, fn () => $this->mapCityList($this->request($payload)), $useCache);
    }

    private function key(string ...$parts): string
    {
        return 'pf:'.implode(':', array_map(static fn ($p) => strtolower(trim((string) $p)), $parts));
    }

    /** @template T @param callable():array $fetch @return T[] */
    private function getOrFetch(string $key, callable $fetch, bool $useCache): array
    {
        $useCache = config('pianeta-fibra-coverage.use_cache', true) && $useCache;
        $ttl = config('pianeta-fibra-coverage.cache_ttl_seconds', 43200);
        $store = config('pianeta-fibra-coverage.cache_store', null);

        if ($useCache) {
            return Cache::store($store)->remember($key, $ttl, fn () => $fetch());
        }

        return $fetch();
    }

    /** @param array $data @return CityMatch[] */
    private function mapCityList(array $data): array
    {
        $items = Arr::get($data, 'CNL_AREA_OUT.CNL', []);
        $items = array_filter($items);
        $out = [];
        foreach ($items as $row) {
            $out[] = new CityMatch(
                egonCityId: Arr::get($row, 'CDPOBJCNL.lValue', 0),
                label: Arr::get($row, 'DSXOBJCNL', ''),
                zip: Arr::get($row, 'CDXZIP'),
                province: Arr::get($row, 'DSXOBJDPT'),
                region: Arr::get($row, 'DSXOBJREG'),
            );
        }

        return $out;
    }

    /** @return array<mixed> */
    private function request(array $payload): array
    {
        try {
            $response = Http::withToken($this->bearerToken)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry($this->maxRetries, 200)
                ->throw()
                ->get($this->baseUri, $payload);

            $this->logger?->debug('PF API response', ['payload' => $payload, 'status' => $response->status()]);

            return $response->json();
        } catch (RequestException $e) {

            $status = $e->getCode();
            if ($status === 401 || $status === 403) {
                throw new AuthException('Unauthorized/Forbidden');
            }
            throw new ApiException('Server error: '.$status);
        }

    }

    /** @template T of LabeledMatch
     * @param  T[]  $list
     */
    private function selectOne(string $expected, array $list, bool $matchOrFail, string $scope): LabeledMatch
    {
        $normalized = $this->normalize($expected);
        $exact = array_values(array_filter($list, fn (LabeledMatch $m) => $this->normalize($m->label) === $normalized));

        if ($matchOrFail) {
            if (count($exact) === 1) {
                return $exact[0];
            }
            if (count($exact) > 1) {
                throw new AmbiguityException("Multiple {$scope} matches for '{$expected}'");
            }
            throw new NotFoundException("{$scope} not found for '{$expected}'");
        }
        if (count($exact) >= 1) {
            return $exact[0];
        }

        throw new NotFoundException("{$scope} not found for '{$expected}'");
    }

    private function normalize(string $s): string
    {
        return (string) Str::of($s)
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->replace("'", '')
            ->lower();
    }

    /** @return StreetMatch[] */
    public function searchStreets(int $egonCityId, string $street, bool $useCache = true): array
    {
        $payload = ['endpoint' => 'address', 'id_city' => $egonCityId, 'street' => $street];
        $cacheKey = $this->key('street', (string) $egonCityId, $street);

        return $this->getOrFetch($cacheKey, fn () => $this->mapStreetList($this->request($payload)), $useCache);
    }

    /** @param array $data @return StreetMatch[] */
    private function mapStreetList(array $data): array
    {
        $items = Arr::get($data, 'STR_AREA_OUT.STR', []);
        $items = array_filter($items);
        $out = [];
        foreach ($items as $row) {
            $out[] = new StreetMatch(
                egonStreetId: Arr::get($row, 'CDPOBJSTR.lValue', 0),
                label: Arr::get($row, 'DSXOBJSTR', ''),
            );
        }

        return $out;
    }

    /** @return HouseNumberMatch[] */
    public function searchHouseNumbers(int $egonStreetId, string $num, bool $useCache = true): array
    {
        $payload = ['endpoint' => 'housenumber', 'id_street' => $egonStreetId, 'num' => $num];
        $cacheKey = $this->key('hnum', (string) $egonStreetId, $num);

        return $this->getOrFetch($cacheKey, fn () => $this->mapHouseNumberList($this->request($payload)), $useCache);
    }

    /** @param array $data @return HouseNumberMatch[] */
    private function mapHouseNumberList(array $data): array
    {
        $items = Arr::get($data, 'CIV_AREA_OUT.CIV', []);
        $items = array_filter($items);
        $out = [];
        foreach ($items as $row) {
            $out[] = new HouseNumberMatch(
                egonHouseNumberId: Arr::get($row, 'CDPOBJCIV.lValue', 0),
                label: Arr::get($row, 'NRPNUMCIV.lValue', ''),
                description: Arr::get($row, 'DSXESP'),
            );
        }

        return Arr::sort($out, 'description');
    }

    public function getCoverage(CoverageParams $params): CoverageResult
    {
        $payload = [
            'endpoint' => 'coverage',
            'customer_type' => $params->customerType->value,
            'myStreet' => $params->street,
            'myNum' => $params->houseNumber,
            'myCity' => $params->city,
            'myCap' => $params->zip,
            'myPrv' => $params->province,
            'myER' => $params->egonHouseNumberId,
            'myStrg' => $params->egonStreetId,
            'myReg' => $params->region,
        ];

        $data = $this->request($payload);

        return $this->mapCoverage($data);
    }

    private function mapCoverage(array $data): CoverageResult
    {
        $isAvailable = (bool) ($data['IsAvailable'] ?? $data['available'] ?? false);
        $techCode = (string) ($data['TechnologyCode'] ?? $data['technology'] ?? '');
        $rawProfiles = $data['Coverage'] ?? $data['profiles'] ?? [];

        $profiles = [];
        foreach ($rawProfiles as $p) {
            $profiles[] = new CoverageProfile(
                type: (string) ($p['type'] ?? $p['name'] ?? ''),
                url: isset($p['url']) ? (string) $p['url'] : null,
            );
        }

        return new CoverageResult(
            isAvailable: $isAvailable,
            technologyCode: $techCode,
            profiles: $profiles,
            raw: $data,
        );
    }
}
