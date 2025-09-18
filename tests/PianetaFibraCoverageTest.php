<?php

use DvSoft\PianetaFibraCoverage\DTO\CoverageParams;
use DvSoft\PianetaFibraCoverage\DTO\Location;
use DvSoft\PianetaFibraCoverage\DTO\ResolveOutcome;
use DvSoft\PianetaFibraCoverage\Enums\CustomerType;
use DvSoft\PianetaFibraCoverage\Exceptions\AmbiguityException;
use DvSoft\PianetaFibraCoverage\Exceptions\ApiException;
use DvSoft\PianetaFibraCoverage\Exceptions\AuthException;
use DvSoft\PianetaFibraCoverage\Exceptions\NotFoundException;
use DvSoft\PianetaFibraCoverage\Facades\PianetaFibraCoverage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('searches cities and uses caching when enabled', function () {
    Cache::shouldReceive('store')
        ->once()
        ->with(null) // Default cache store
        ->andReturnSelf();

    Cache::shouldReceive('remember')
        ->once()
        ->with('pf:city:rome', 43200, Mockery::type('Closure')) // Cache key e TTL
        ->andReturn([
            ['egonCityId' => 1, 'label' => 'Rome', 'zip' => '00100', 'province' => 'RM', 'region' => 'Lazio'],
            ['egonCityId' => 2, 'label' => 'Milan', 'zip' => '20100', 'province' => 'MI', 'region' => 'Lombardia'],
        ]);

    $cities = PianetaFibraCoverage::searchCities('Rome');

    expect($cities)
        ->toBeArray()
        ->and($cities[0])->toMatchArray(['egonCityId' => 1, 'label' => 'Rome', 'zip' => '00100', 'province' => 'RM', 'region' => 'Lazio']);
});

it('search street', function () {
    Http::fake([
        '*' => Http::response([
            'STR_AREA_OUT' => [
                'STR' => [
                    ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                ]
            ]
        ])
    ]);


    $streets = PianetaFibraCoverage::searchStreets('1111', 'Via Roma');

    expect($streets)
        ->toBeArray()
        ->and($streets[0])
        ->toMatchArray([
            'egonStreetId' => 1,
            'label' => 'Via Roma'
        ])
        ->and($streets[0]->label())->toBe('Via Roma');
});

it('search house number', function () {
    Http::fake([
        '*' => Http::response([
            'CIV_AREA_OUT' => [
                'CIV' => [
                    ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '50'], 'DSXESP' => ''],
                ]
            ]
        ])
    ]);


    $streets = PianetaFibraCoverage::searchHouseNumbers('1111', '50');

    expect($streets)
        ->toBeArray()
        ->and($streets[0])
        ->toMatchArray([
            'egonHouseNumberId' => 1,
            'label' => '50',
            'description' => ''
        ])
        ->and($streets[0]->label())->toBe('50');
});

it('resolve coverage from location', function (bool $matchOrFail) {
    $location = new Location('Via Roma', '12', 'Rome', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'CNL_AREA_OUT' => [
                    'CNL' => [
                        ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Rome', 'CDXZIP' => '20831', 'DSXOBJDPT' => 'MB', 'DSXOBJREG' => 'Lombardia'],
                    ]
                ]
            ])
            ->push([
                'STR_AREA_OUT' => [
                    'STR' => [
                        ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                    ]
                ]
            ])
            ->push([
                'CIV_AREA_OUT' => [
                    'CIV' => [
                        ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '12'], 'DSXESP' => ''],
                    ]
                ]
            ])
            ->push([
                'IsAvailable' => true,
                'TechnologyCode' => 'FTTH',
                'Coverage' => [
                    ['type' => '100/20', 'url' => 'https://fiber.example.com/ftth'],
                    ['type' => '200/50', 'url' => 'https://fiber.example.com/ftth10'],
                ]
            ])
            ->whenEmpty(Http::response(['error' => 'Troppe richieste'], 500)) // Default se supera il limite massimo di retry
    ]);


    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda, $matchOrFail);

    expect($outcome->resolved)->toBeTrue();

    $coverage = $outcome->coverage;

    expect($coverage->isAvailable)->toBeTrue()
        ->and($coverage->technologyCode)->toBe('FTTH')
        ->and($coverage->profiles[0])->toMatchArray([
            'type' => '100/20',
            'url' => 'https://fiber.example.com/ftth',
        ])
        ->and($coverage->profiles[1])->toMatchArray([
            'type' => '200/50',
            'url' => 'https://fiber.example.com/ftth10',
        ])
        ->and($coverage->profiles[0]->downMbps())->toBe(100)
        ->and($coverage->profiles[1]->downMbps())->toBe(200)
        ->and($coverage->profiles[0]->upMbps())->toBe(20)
        ->and($coverage->profiles[1]->upMbps())->toBe(50);

})->with([true, false]);

it('fail with ambiguous cities resolving coverage', function () {
    $location = new Location('Via Roma', '12', 'Rome', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'CNL_AREA_OUT' => [
                    'CNL' => [
                        ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Rome', 'CDXZIP' => '20831', 'DSXOBJDPT' => 'MB', 'DSXOBJREG' => 'Lombardia'],
                        ['CDPOBJCNL' => ['lValue' => 5], 'DSXOBJCNL' => 'Rome', 'CDXZIP' => '20831', 'DSXOBJDPT' => 'MB', 'DSXOBJREG' => 'Lombardia'],
                    ]
                ]
            ])
            ->push([
                'STR_AREA_OUT' => [
                    'STR' => [
                        ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                    ]
                ]
            ])
            ->push([
                'CIV_AREA_OUT' => [
                    'CIV' => [
                        ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '12'], 'DSXESP' => ''],
                    ]
                ]
            ])
            ->push([
                'IsAvailable' => true,
                'TechnologyCode' => 'FTTH',
                'Coverage' => [
                    ['type' => '100/20', 'url' => 'https://fiber.example.com/ftth'],
                    ['type' => '200/50', 'url' => 'https://fiber.example.com/ftth10'],
                ]
            ])
            ->whenEmpty(Http::response(['error' => 'Troppe richieste'], 500)) // Default se supera il limite massimo di retry
    ]);

    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda, false);

    expect($outcome->alternatives)->toBeArray()->and($outcome->resolved)->toBeFalse();
});


it('fail with ambiguous street resolving coverage', function () {
    $location = new Location('Via Roma', '12', 'Rome', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'CNL_AREA_OUT' => [
                    'CNL' => [
                        ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Rome', 'CDXZIP' => '20831', 'DSXOBJDPT' => 'MB', 'DSXOBJREG' => 'Lombardia'],
                    ]
                ]
            ])
            ->push([
                'STR_AREA_OUT' => [
                    'STR' => [
                        ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                        ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                    ]
                ]
            ])
            ->push([
                'CIV_AREA_OUT' => [
                    'CIV' => [
                        ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '12'], 'DSXESP' => ''],
                    ]
                ]
            ])
            ->push([
                'IsAvailable' => true,
                'TechnologyCode' => 'FTTH',
                'Coverage' => [
                    ['type' => '100/20', 'url' => 'https://fiber.example.com/ftth'],
                    ['type' => '200/50', 'url' => 'https://fiber.example.com/ftth10'],
                ]
            ])
            ->whenEmpty(Http::response(['error' => 'Troppe richieste'], 500)) // Default se supera il limite massimo di retry
    ]);

    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda, false);

    expect($outcome->alternatives)->toBeArray()->and($outcome->resolved)->toBeFalse();
});



it('fail with ambiguous house number resolving coverage', function () {
    $location = new Location('Via Roma', '12', 'Rome', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'CNL_AREA_OUT' => [
                    'CNL' => [
                        ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Rome', 'CDXZIP' => '20831', 'DSXOBJDPT' => 'MB', 'DSXOBJREG' => 'Lombardia'],
                    ]
                ]
            ])
            ->push([
                'STR_AREA_OUT' => [
                    'STR' => [
                        ['CDPOBJSTR' => ['lValue' => 1], 'DSXOBJSTR' => 'Via Roma'],
                    ]
                ]
            ])
            ->push([
                'CIV_AREA_OUT' => [
                    'CIV' => [
                        ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '12'], 'DSXESP' => ''],
                        ['CDPOBJCIV' => ['lValue' => 1], 'NRPNUMCIV' => ['lValue' => '12'], 'DSXESP' => ''],
                    ]
                ]
            ])
            ->push([
                'IsAvailable' => true,
                'TechnologyCode' => 'FTTH',
                'Coverage' => [
                    ['type' => '100/20', 'url' => 'https://fiber.example.com/ftth'],
                    ['type' => '200/50', 'url' => 'https://fiber.example.com/ftth10'],
                ]
            ])
            ->whenEmpty(Http::response(['error' => 'Troppe richieste'], 500)) // Default se supera il limite massimo di retry
    ]);

    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda, false);

    expect($outcome->alternatives)->toBeArray()->and($outcome->resolved)->toBeFalse();
});

it('bypasses cache when disabled', function () {
    Http::fake([
        '*' => Http::response([
            'CNL_AREA_OUT' => [
                'CNL' => [
                    ['CDPOBJCNL' => ['lValue' => 3], 'DSXOBJCNL' => 'Naples', 'CDXZIP' => '80100', 'DSXOBJDPT' => 'NA', 'DSXOBJREG' => 'Campania'],
                    ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Florence', 'CDXZIP' => '50100', 'DSXOBJDPT' => 'FI', 'DSXOBJREG' => 'Toscana'],
                ],
            ],
        ]),
    ]);

    Config::set('pianeta-fibra-coverage.use_cache', false);

    $cities = PianetaFibraCoverage::searchCities('Naples');

    expect($cities)
        ->toBeArray()
        ->and($cities[0])->toMatchArray([
            'egonCityId' => 3,
            'label' => 'Naples',
            'zip' => '80100',
            'province' => 'NA',
            'region' => 'Campania',
        ]);
});

it('handles retry logic correctly', function () {
    $retryCount = 2; // Il numero massimo di retry che vogliamo gestire
    Config::set('pianeta-fibra-coverage.max_retries', $retryCount);

    Http::fake([
        '*' => Http::sequence()
            ->pushStatus(500) // Prima richiesta fallisce
            ->push([ // Seconda richiesta restituisce un risultato valido
                'CNL_AREA_OUT' => [
                    'CNL' => [
                        [
                            'CDPOBJCNL' => ['lValue' => 1],
                            'DSXOBJCNL' => 'Rome'
                        ]
                    ]
                ]
            ])
            ->whenEmpty(Http::response(['error' => 'Troppe richieste'], 500)), // Default se supera il limite massimo di retry
    ]);

    $cities = PianetaFibraCoverage::searchCities('Rome');

    expect($cities)
        ->toBeArray()
        ->and($cities[0])->toMatchArray(['egonCityId' => 1, 'label' => 'Rome'])
        ->and($cities[0]->label())->toBe('Rome');
});

it('fetches coverage using config-based timeout and retries', function () {
    Http::fake([
        '*' => Http::response([
            'IsAvailable' => true,
            'TechnologyCode' => 'FTTH',
            'Coverage' => [
                ['type' => 'FTTH 1Gbps', 'url' => 'https://fiber.example.com/ftth'],
            ],
        ]),
    ]);

    Config::set('pianeta-fibra-coverage.timeout', 5);
    Config::set('pianeta-fibra-coverage.max_retries', 3);

    $coverage = PianetaFibraCoverage::getCoverage(new CoverageParams(
        egonCityId: 1,
        egonStreetId: 2,
        egonHouseNumberId: 3,
        city: 'Rome',
        street: 'Via Roma',
        houseNumber: '10',
        zip: '00100',
        province: 'RM',
        region: 'Lazio',
        customerType: CustomerType::Privato
    ));

    expect($coverage->isAvailable)->toBeTrue()
        ->and($coverage->technologyCode)->toBe('FTTH')
        ->and($coverage->profiles[0])->toMatchArray([
            'type' => 'FTTH 1Gbps',
            'url' => 'https://fiber.example.com/ftth',
        ]);
});



it('throw AuthException if token error', function () {

    Http::fake([
        '*' => Http::response('', 401),
    ]);

    Config::set('pianeta-fibra-coverage.token', 'invalid_token');

    PianetaFibraCoverage::searchCities('Test');
})->throws(AuthException::class);

it('throw ApiException if error', function () {

    Http::fake([
        '*' => Http::response('', 500),
    ]);

    Config::set('pianeta-fibra-coverage.token', 'invalid_token');

    PianetaFibraCoverage::searchCities('Test');
})->throws(ApiException::class);

it('throw AmbiguityException when needed', function () {
    $location = new Location('Via Roma', '12', 'Naples', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::response([
            'CNL_AREA_OUT' => [
                'CNL' => [
                    ['CDPOBJCNL' => ['lValue' => 3], 'DSXOBJCNL' => 'Naples', 'CDXZIP' => '80100', 'DSXOBJDPT' => 'NA', 'DSXOBJREG' => 'Campania'],
                    ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Naples', 'CDXZIP' => '50100', 'DSXOBJDPT' => 'FI', 'DSXOBJREG' => 'Toscana'],
                ],
            ],
        ]),
    ]);

    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda);

    expect($outcome->alternatives)->toBeArray()->and($outcome->resolved)->toBeFalse();

})->throws(AmbiguityException::class);

it('throw NotFoundException when needed', function () {
    $location = new Location('Via Roma', '12', 'Rome', '20831', 'MB', 'Lombardia');

    Http::fake([
        '*' => Http::response([
            'CNL_AREA_OUT' => [
                'CNL' => [
                    ['CDPOBJCNL' => ['lValue' => 3], 'DSXOBJCNL' => 'Naples', 'CDXZIP' => '80100', 'DSXOBJDPT' => 'NA', 'DSXOBJREG' => 'Campania'],
                    ['CDPOBJCNL' => ['lValue' => 4], 'DSXOBJCNL' => 'Naples', 'CDXZIP' => '50100', 'DSXOBJDPT' => 'FI', 'DSXOBJREG' => 'Toscana'],
                ],
            ],
        ]),
    ]);

    /** @var ResolveOutcome $outcome */
    $outcome = PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda);

    expect($outcome->alternatives)->toBeArray()->and($outcome->resolved)->toBeFalse();

})->throws(NotFoundException::class);
