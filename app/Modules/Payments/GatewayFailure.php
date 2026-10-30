<?php

namespace App\Modules\Payments;

class GatewayFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $sent = true, public readonly int $retryAfter = 30)
    {
        parent::__construct('NDSGPay: '.$reason);
    }
}
