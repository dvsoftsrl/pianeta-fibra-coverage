<?php

declare(strict_types=1);

namespace DvSoft\PianetaFibraCoverage\DTO;

final class ResolveOutcome
{
    /** @param array<int, object> $alternatives */
    private function __construct(
        public readonly bool            $resolved,
        public readonly ?CoverageResult $coverage,
        public readonly array           $alternatives,
        public readonly ?string         $pendingScope,
    )
    {
    }

    public static function resolved(CoverageResult $c): self
    {
        return new self(true, $c, [], null);
    }

    /** @param array<int, object> $alternatives */
    public static function ambiguous(string $scope, array $alternatives): self
    {
        return new self(false, null, $alternatives, $scope);
    }

    public static function notFound(string $scope): self
    {
        return new self(false, null, [], $scope);
    }
}
