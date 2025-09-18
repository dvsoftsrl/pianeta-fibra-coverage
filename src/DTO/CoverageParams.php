<?php

declare(strict_types=1);

namespace DvSoft\PianetaFibraCoverage\DTO;

use DvSoft\PianetaFibraCoverage\Enums\CustomerType;

final class CoverageParams
{
    public function __construct(
        public readonly int $egonCityId,
        public readonly int $egonStreetId,
        public readonly int $egonHouseNumberId,
        public readonly string $street,
        public readonly string $houseNumber,
        public readonly string $city,
        public readonly string $zip,
        public readonly string $province,
        public readonly string $region,
        public readonly CustomerType $customerType,
    ) {}
}
