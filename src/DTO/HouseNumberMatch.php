<?php

declare(strict_types=1);

namespace DvSoft\PianetaFibraCoverage\DTO;

final class HouseNumberMatch implements LabeledMatch
{
    public function __construct(
        public readonly int $egonHouseNumberId,
        public readonly string $label,
        public readonly ?string $description = null,
    ) {}

    public function label(): string
    {
        return $this->label;
    }
}
