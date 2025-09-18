<?php
declare(strict_types=1);
namespace DvSoft\PianetaFibraCoverage\DTO;
use function usort;

final class CoverageProfile
{
    public function __construct(
        public readonly string $type, // example: "300/50"
        public readonly ?string $url = null,
    ) {}

    public function downMbps(): ?int
    {
        if (preg_match('/^(\d+)\/(\d+)$/', $this->type, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function upMbps(): ?int
    {
        if (preg_match('/^(\d+)\/(\d+)$/', $this->type, $matches)) {
            return (int) $matches[2];
        }
        return null;
    }
}
