<?php

namespace DvSoft\PianetaFibraCoverage;

use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PianetaFibraCoverageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('pianeta-fibra-coverage')
            ->hasConfigFile();
    }


    public function packageRegistered(): void
    {
        $this->app->singleton(PianetaFibraCoverage::class, function ($app) {
            $cfg = config('pianeta-fibra-coverage');

            $token = (string)($cfg['token'] ?? '');

            // Logger opzionale
            $logger = $app->has(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

            return new PianetaFibraCoverage(
                bearerToken: $token,
                baseUri: $cfg['base_uri'],
                timeoutSeconds: $cfg['timeout'],
                maxRetries: $cfg['max_retries'],
                logger: $logger,
            );
        });
    }
}
