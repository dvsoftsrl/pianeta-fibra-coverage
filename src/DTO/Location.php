<?php
declare(strict_types=1);
namespace DvSoft\PianetaFibraCoverage\DTO;
final class Location
{
    public function __construct(
        public readonly string $street,
        public readonly string $houseNumber,
        public readonly string $city,
        public readonly string $zip,
        public readonly string $province,
        public readonly string $region,
    ) {}
}
