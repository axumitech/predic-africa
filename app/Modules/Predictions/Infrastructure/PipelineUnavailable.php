<?php

namespace App\Modules\Predictions\Infrastructure;

use RuntimeException;

class PipelineUnavailable extends RuntimeException
{
    public function __construct(public readonly int $retryAfter = 30)
    {
        parent::__construct('Le pipeline IA est temporairement indisponible.');
    }
}
