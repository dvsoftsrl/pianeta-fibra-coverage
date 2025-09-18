<?php


use DvSoft\PianetaFibraCoverage\PianetaFibraCoverage;

it('registers the PianetaFibraCoverage singleton', function () {
    $instance = app(PianetaFibraCoverage::class);

    expect($instance)->toBeInstanceOf(PianetaFibraCoverage::class)
        ->and(app(PianetaFibraCoverage::class))->toBe($instance);
});
