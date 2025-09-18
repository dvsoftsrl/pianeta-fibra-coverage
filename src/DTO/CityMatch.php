<?php
declare(strict_types=1);
namespace DvSoft\PianetaFibraCoverage\DTO;
final class CityMatch implements LabeledMatch
{
    public function __construct(
        public readonly int $egonCityId,
        public readonly string $label,
        public readonly ?string $zip = null,
        public readonly ?string $province = null,
        public readonly ?string $region = null,
    ) {}
    public function label(): string { return $this->label; }
}
