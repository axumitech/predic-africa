<?php

return [
    'scheduler_worker' => (bool) env('MARKET_SCHEDULER_WORKER', true),
    'automatic_enabled' => (bool) env('MARKET_AUTOMATIC_RESOLUTION_ENABLED', false),
    // Trusted, operator-managed adapters. Keys identify independent sources.
    'providers' => [],
];
