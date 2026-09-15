<?php

return [
    'enabled' => (bool) env('AI_PIPELINE_ENABLED', true),
    'retry_seconds' => (int) env('AI_PIPELINE_RETRY_SECONDS', 30),
    'url' => env('AI_PIPELINE_URL', 'http://127.0.0.1:8101'),
    'timeout' => (int) env('AI_PIPELINE_TIMEOUT', 1),
    'cache_seconds' => (int) env('AI_PIPELINE_CACHE_SECONDS', 30),
];
