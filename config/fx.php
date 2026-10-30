<?php

return [
    'provider' => 'currencyapi',
    'key' => env('CURRENCYAPI_KEY'),
    'refresh_enabled' => env('FX_REFRESH_ENABLED', true),
    'refresh_seconds' => 86400,
    // Daily source plus one publication cycle of tolerance; never extend fetched rates.
    'max_age_seconds' => 172800,
    'quote_seconds' => 60,
    'timeout' => 8,
];
