<?php
declare(strict_types=1);
namespace DvSoft\PianetaFibraCoverage\DTO;
final class StreetMatch implements LabeledMatch
{
    public function __construct(
        public readonly int $egonStreetId,
        public readonly string $label,
    ) {}
    public function label(): string { return $this->label; }
}
