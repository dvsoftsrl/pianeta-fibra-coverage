<?php

use DvSoft\PianetaFibraCoverage\DTO\CoverageProfile;
use DvSoft\PianetaFibraCoverage\DTO\CoverageResult;

it('can create a CoverageResult instance', function () {
    $profiles = [
        new CoverageProfile('300/50'),
        new CoverageProfile('500/100'),
    ];

    $coverageResult = new CoverageResult(
        isAvailable: true,
        technologyCode: 'FTTH',
        profiles: $profiles,
        raw: [],
    );

    expect($coverageResult->isAvailable)->toBeTrue();
    expect($coverageResult->technologyCode)->toBe('FTTH');
    expect($coverageResult->profiles)->toHaveCount(2);
    expect($coverageResult->raw)->toBeArray();
});

it('returns the best profile based on download and upload Mbps', function () {
    $profiles = [
        new CoverageProfile('300/50'),
        new CoverageProfile('500/100'), // Best profile
        new CoverageProfile('100/25'),
    ];

    $coverageResult = new CoverageResult(
        isAvailable: true,
        technologyCode: 'FTTH',
        profiles: $profiles,
        raw: [],
    );

    $bestProfile = $coverageResult->bestProfile();

    expect($bestProfile)->toBeInstanceOf(CoverageProfile::class);
    expect($bestProfile->type)->toBe('500/100');
});

it('returns null if there are no profiles when calling bestProfile()', function () {
    $coverageResult = new CoverageResult(
        isAvailable: false,
        technologyCode: 'None',
        profiles: [],
        raw: [],
    );

    expect($coverageResult->bestProfile())->toBeNull();
});

it('return null on speeds if coverage profile is malformed', function () {

    $coverageProfile = new CoverageProfile('300-50');

    expect($coverageProfile->downMbps())->toBeNull();
    expect($coverageProfile->upMbps())->toBeNull();

});
