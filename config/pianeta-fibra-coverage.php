<?php

// config for DvSoft/PianetaFibraCoverage
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

    'enable_logger' => env('PIANETA_FIBRA_ENABLE_LOGGER', false),
];
