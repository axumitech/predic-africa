<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Money\Money;
use App\Modules\Identity\RegistrationRegions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Capabilities
{
    public function forAccount(User $user): array
    {
        $currency = DB::table('payment_wallets')->where('user_id', $user->id)->value('currency') ?? 'XAF';

        return array_values(array_filter($this->enabled(), fn ($capability) => $capability['currency'] === $currency
            && ($user->country === null || $capability['country'] === $user->country)));
    }

    public function enabled(): array
    {
        return array_values(array_filter(config('payments.capabilities'), fn ($c) => is_array($c)
            && is_string($c['currency'] ?? null)
            && isset(Money::SCALES[$c['currency'] ?? ''])
            && in_array($c['currency'], app(RegistrationRegions::class)->currenciesFor($c['country'] ?? null), true)
            && is_string($c['country'] ?? null) && is_string($c['correspondent'] ?? null) && is_string($c['calling_code'] ?? null)
            && preg_match('/\A[A-Z]{3}\z/', $c['country'] ?? '')
            && preg_match('/\A[A-Z0-9_]{2,64}\z/', $c['correspondent'] ?? '')
            && preg_match('/\A[1-9][0-9]{0,3}\z/', $c['calling_code'] ?? '')
            && is_array($c['national_lengths'] ?? null) && count($c['national_lengths']) > 0
            && count(array_filter($c['national_lengths'], fn ($n) => is_int($n) && $n >= 4 && $n <= 14)) === count($c['national_lengths'])));
    }

    public function validate(array $data): array
    {
        $matches = array_values(array_filter($this->enabled(), fn ($c) => $c['correspondent'] === ($data['correspondent'] ?? null)));
        if (count($matches) !== 1) {
            throw ValidationException::withMessages(['correspondent' => 'Couple pays/opérateur/devise non confirmé ou ambigu.']);
        }
        $capability = $matches[0];
        $phone = $data['msisdn'] ?? '';
        if (! is_string($phone) || ! preg_match('/\A\+?[0-9 ()-]+\z/', $phone)) {
            throw ValidationException::withMessages(['msisdn' => 'Numéro international invalide.']);
        }
        $phone = str_replace(['+', ' ', '(', ')', '-'], '', $phone);
        $prefix = $capability['calling_code'];
        if (! str_starts_with($phone, $prefix) || strlen($phone) > 15
            || ! in_array(strlen($phone) - strlen($prefix), $capability['national_lengths'], true)) {
            throw ValidationException::withMessages(['msisdn' => 'Indicatif ou longueur incompatible avec le pays sélectionné.']);
        }
        // Wallet topup is an explicitly authorized variable-amount business rule,
        // not a browser-supplied order price. Currency and owner are server-derived.
        $amount = Money::minor($data['amount'] ?? null, $capability['currency']);
        if ($amount < Money::minor((string) config('payments.min_amount'), $capability['currency'])
            || $amount > Money::minor((string) config('payments.max_amount'), $capability['currency'])) {
            throw ValidationException::withMessages(['amount' => 'Montant hors des limites autorisées.']);
        }

        return ['kind' => 'deposit', 'amount' => $amount, 'reference' => $data['reference'], 'correspondent' => $capability['correspondent'],
            'msisdn' => $phone, 'currency' => $capability['currency'], 'country' => $capability['country']];
    }
}
