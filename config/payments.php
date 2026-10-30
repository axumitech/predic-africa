<?php

$capabilities = json_decode(env('NDSGPAY_CAPABILITIES', '[]'), true);

return [
    'enabled' => env('NDSGPAY_ENABLED', false),
    'base_url' => env('NDSGPAY_BASE_URL', 'https://ndsgpay-backend.vercel.app/api'),
    'secret_key' => env('NDSGPAY_SECRET_KEY'),
    'webhook_secret' => env('NDSGPAY_WEBHOOK_SECRET'),
    // Optional additional webhook-header check when the provider confirms the ID.
    'merchant_id' => env('NDSGPAY_MERCHANT_ID'),
    'webhook_tolerance' => max(1, (int) env('NDSGPAY_WEBHOOK_TOLERANCE', 300)),
    'webhook_max_bytes' => max(1024, (int) env('NDSGPAY_WEBHOOK_MAX_BYTES', 16384)),
    'connect_timeout' => 3,
    'timeout' => 8,
    'alert_after_minutes' => 30,
    // Fail closed: only confirmed country/operator/currency tuples from the registration catalogue.
    // Each tuple: country, correspondent, currency, calling_code, national_lengths.
    'capabilities' => is_array($capabilities) ? $capabilities : [],
    'min_amount' => 100,
    'max_amount' => 1000000,
    // Technical payout support remains closed until a dedicated reviewed workflow exists.
    'payouts_enabled' => false,
];
