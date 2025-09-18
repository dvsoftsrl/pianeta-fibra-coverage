# Client PHP per API di copertura PianetaFibra

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dvsoftsrl/pianeta-fibra-coverage.svg?style=flat-square)](https://packagist.org/packages/dvsoftsrl/pianeta-fibra-coverage)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dvsoftsrl/pianeta-fibra-coverage/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dvsoftsrl/pianeta-fibra-coverage/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dvsoftsrl/pianeta-fibra-coverage/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dvsoftsrl/pianeta-fibra-coverage/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dvsoftsrl/pianeta-fibra-coverage.svg?style=flat-square)](https://packagist.org/packages/dvsoftsrl/pianeta-fibra-coverage)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/pianeta-fibra-coverage.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/pianeta-fibra-coverage)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require dvsoftsrl/pianeta-fibra-coverage
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="pianeta-fibra-coverage-config"
```

This is the contents of the published config file:

```php
return [
    // Token API (header 'authorization: Bearer <TOKEN>')
    'token' => env('PIANETAFIBRA_TOKEN', ''),

    // Endpoint base API
    'base_uri' => env('PIANETAFIBRA_BASE_URI', 'https://api.pianetafibra.it/v2/api.php'),

    // HTTP client
    'timeout' => env('PIANETAFIBRA_TIMEOUT', 10),
    'max_retries' => env('PIANETAFIBRA_MAX_RETRIES', 2),

    // Caching
    'use_cache' => env('PIANETAFIBRA_USE_CACHE', true),
    // Se valorizzato, usa questo store di cache (es. 'redis', 'file'); altrimenti usa quello di default se use_cache=true
    'cache_store' => env('PIANETAFIBRA_CACHE_STORE', null),
    // TTL per anagrafiche city/street/civic (secondi)
    'cache_ttl_seconds' => env('PIANETAFIBRA_CACHE_TTL', 43200),

    // Comportamento di default per la funzione totale (puoi sempre override col parametro)
    // true => match esatto o eccezione; false => ritorna ResolveOutcome::ambiguous(...) con alternative
    'default_match_or_fail' => env('PIANETAFIBRA_MATCH_OR_FAIL', true),
];
```

## Usage

```php
PianetaFibraCoverage::resolveCoverageFromLocation($location, CustomerType::Azienda);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [DV Soft srl](https://github.com/dvsoftsrl)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
