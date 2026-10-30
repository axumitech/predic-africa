<?php

namespace App\Modules\Payments;

class WebhookSignature
{
    public function valid(string $raw, string $header, string $merchant): bool
    {
        $secret = config('payments.webhook_secret');
        $expectedMerchant = Ndsgpay::merchantScope();
        if (! is_string($secret) || $secret === ''
            || ($expectedMerchant !== null && ! hash_equals($expectedMerchant, $merchant)) || strlen($raw) > config('payments.webhook_max_bytes')
            || ! preg_match('/\At=([1-9][0-9]{0,10}),sha256=([a-fA-F0-9]{64})\z/D', $header, $parts)) {
            return false;
        }
        if (abs(time() - (int) $parts[1]) > config('payments.webhook_tolerance')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $parts[1].'.'.$raw, $secret), strtolower($parts[2]));
    }
}
