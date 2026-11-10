<?php

namespace App\Modules\Payments;

use App\Modules\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutOptions
{
    public function forWallet(int $userId, string $currency): ?array
    {
        $limits = config('payments.payout_limits')[$currency] ?? null;
        if (! config('payments.payouts_enabled') || ! is_array($limits)) {
            return null;
        }

        try {
            $min = Money::minor($limits['min_amount'] ?? null, $currency);
            $max = Money::minor($limits['max_amount'] ?? null, $currency);
            $daily = Money::minor($limits['daily_limit'] ?? null, $currency);
        } catch (ValidationException) {
            return null;
        }
        if ($min <= 0 || $min > $max || $max > $daily) {
            return null;
        }

        $walletId = DB::table('payment_wallets')->where('user_id', $userId)->where('currency', $currency)->value('id');
        $capabilities = collect(app(Capabilities::class)->enabled())->groupBy('correspondent');
        $operators = config('ndsgpay_operators');
        $eligible = [];
        foreach ($capabilities as $code => $matches) {
            $capability = $matches->first();
            $operator = $operators[$code] ?? null;
            if ($matches->count() === 1 && $capability['currency'] === $currency
                && ($operator['currency'] ?? null) === $currency && ($operator['country'] ?? null) === $capability['country']) {
                $eligible[$code] = $capability;
            }
        }
        if ($walletId === null || $eligible === []) {
            return null;
        }

        return [
            'wallet_id' => (int) $walletId, 'currency' => $currency, 'operators' => $eligible,
            'min_amount' => $min, 'max_amount' => $max, 'daily_limit' => $daily,
            'token' => hash_hmac('sha256', "payout-wallet:$userId:$walletId:$currency", config('app.key')),
        ];
    }
}
