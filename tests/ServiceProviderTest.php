<?php

use DvSoft\PianetaFibraCoverage\PianetaFibraCoverage;
use Psr\Log\LoggerInterface;

it('registers the PianetaFibraCoverage singleton', function () {
    $instance = app(PianetaFibraCoverage::class);

    expect($instance)->toBeInstanceOf(PianetaFibraCoverage::class)
        ->and(app(PianetaFibraCoverage::class))->toBe($instance);
});

it('enable/disable logger', function (bool $enabled) {

    // Mock del logger per verificare se viene passato correttamente
    $mockLogger = mock(LoggerInterface::class);

    if ($enabled) {
        // Assicurati che il logger venga risolto solo quando abilitato
        $this->app->instance(LoggerInterface::class, $mockLogger);
    }

    config()->set('pianeta-fibra-coverage.enable_logger', $enabled);

    $instance = app(PianetaFibraCoverage::class);

    expect($instance)->toBeInstanceOf(PianetaFibraCoverage::class);

    // Ora verifica se il logger è stato abilitato oppure no
    $loggerProperty = (new ReflectionClass($instance))->getProperty('logger');

    if ($enabled) {
        expect($loggerProperty->getValue($instance))->toBe($mockLogger);
    } else {
        expect($loggerProperty->getValue($instance))->toBeNull();
    }

})->with([true, false]);
