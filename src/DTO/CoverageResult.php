<?php

declare(strict_types=1);

namespace DvSoft\PianetaFibraCoverage\DTO;

final class CoverageResult
{
    /** @param CoverageProfile[] $profiles */
    public function __construct(
        public readonly bool   $isAvailable,
        public readonly string $technologyCode,
        public readonly array  $profiles,
        public readonly array  $raw,
    )
    {
    }

    public function bestProfile(): ?CoverageProfile
    {
        if (!$this->profiles) {
            return null;
        }
        $sorted = $this->profiles;
        usort($sorted, function (CoverageProfile $a, CoverageProfile $b) {
            $da = $a->downMbps() ?? -1;
            $db = $b->downMbps() ?? -1;
            $ua = $a->upMbps() ?? -1;
            $ub = $b->upMbps() ?? -1;

            return [$db, $ub] <=> [$da, $ua];
        });

        return $sorted[0];
    }
}
