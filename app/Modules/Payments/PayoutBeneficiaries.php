<?php

namespace App\Modules\Payments;

use App\Modules\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutBeneficiaries
{
    public function find(int $userId, string $currency): ?array
    {
        $account = config('payments.payout_beneficiaries')[$userId] ?? null;
        $beneficiary = is_array($account) ? ($account[$currency] ?? $account) : null;
        if (! config('payments.payouts_enabled') || ! is_array($beneficiary)
            || ($beneficiary['currency'] ?? null) !== $currency) {
            return null;
        }

        $capabilities = app(Capabilities::class);
        $operators = config('ndsgpay_operators');
        $matches = array_values(array_filter($capabilities->enabled(), fn ($capability) =>
            $capability['correspondent'] === ($beneficiary['correspondent'] ?? null)));
        if (count($matches) !== 1 || $matches[0]['country'] !== ($beneficiary['country'] ?? null)
            || $matches[0]['currency'] !== $currency) {
            return null;
        }
        $operator = $operators[$matches[0]['correspondent']] ?? null;
        if (($operator['country'] ?? null) !== $matches[0]['country'] || ($operator['currency'] ?? null) !== $currency) {
            return null;
        }
        $walletId = DB::table('payment_wallets')->where('user_id', $userId)->where('currency', $currency)->value('id');
        if ($walletId === null) {
            return null;
        }
        try {
            $phone = $capabilities->normalizePhone($beneficiary['msisdn'] ?? null, $matches[0]);
            $min = Money::minor($beneficiary['min_amount'] ?? null, $currency);
            $max = Money::minor($beneficiary['max_amount'] ?? null, $currency);
            $daily = Money::minor($beneficiary['daily_limit'] ?? null, $currency);
        } catch (ValidationException) {
            return null;
        }
        if ($phone !== $beneficiary['msisdn'] || $min <= 0 || $min > $max || $max > $daily) {
            return null;
        }

        return [
            'wallet_id' => (int) $walletId, 'country' => $matches[0]['country'], 'currency' => $currency,
            'correspondent' => $matches[0]['correspondent'], 'msisdn' => $phone,
            'min_amount' => $min, 'max_amount' => $max, 'daily_limit' => $daily,
            'token' => hash_hmac('sha256', json_encode([$userId, (int) $walletId, $currency, $matches[0]['country'], $matches[0]['correspondent'], $phone]), config('app.key')),
        ];
    }
}
