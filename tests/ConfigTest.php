<?php
it('loads the default configuration', function () {
    $config = config('pianeta-fibra-coverage');

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys([
            'token',
            'base_uri',
            'timeout',
            'max_retries',
            'use_cache',
            'cache_store',
            'cache_ttl_seconds',
        ]);
});
