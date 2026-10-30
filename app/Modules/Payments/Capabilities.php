<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Money\Money;
use App\Modules\Identity\RegistrationRegions;
use Illuminate\Validation\ValidationException;

class Capabilities
{
    public function forAccount(User $user): array
    {
        $currency = \App\Modules\Money\Wallets::currency($user->id);

        return array_values(array_filter($this->enabled(), fn ($capability) => $capability['currency'] === $currency));
    }

    public function enabled(): array
    {
        return array_values(array_filter(app(\App\Modules\Infrastructure\Settings::class)->capabilities(), fn ($c) => is_array($c)
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
        if (! is_string($phone) || ! preg_match('/\A\+?[0-9 ()-]+\z/D', trim($phone))) {
            throw ValidationException::withMessages(['msisdn' => 'Numéro Mobile Money invalide.']);
        }
        $international = str_starts_with(trim($phone), '+');
        $phone = str_replace(['+', ' ', '(', ')', '-'], '', $phone);
        $prefix = $capability['calling_code'];
        $lengths = $capability['national_lengths'];
        if (str_starts_with($phone, '00')) {
            $international = true;
            $phone = substr($phone, 2);
        }
        $hasPrefix = str_starts_with($phone, $prefix)
            && in_array(strlen($phone) - strlen($prefix), $lengths, true);
        if (! $international && ! $hasPrefix) {
            // Only remove a national trunk prefix for countries that declare one.
            $trunk = config('regions.phones.'.$capability['country'].'.trunk');
            if (is_string($trunk) && $trunk !== '' && str_starts_with($phone, $trunk)
                && in_array(strlen($phone) - strlen($trunk), $lengths, true)) {
                $phone = substr($phone, strlen($trunk));
            }
            if (in_array(strlen($phone), $lengths, true)
                && ! (is_string($trunk) && $trunk !== '' && str_starts_with($phone, $trunk))) {
                $phone = $prefix.$phone;
            }
        }
        if (! str_starts_with($phone, $prefix) || strlen($phone) > 15
            || ! in_array(strlen($phone) - strlen($prefix), $capability['national_lengths'], true)) {
            throw ValidationException::withMessages(['msisdn' => 'Indicatif ou longueur incompatible avec le pays sélectionné.']);
        }
        // Wallet topup is an explicitly authorized variable-amount business rule,
        // not a browser-supplied order price. Currency and owner are server-derived.
        $amount = Money::minor($data['amount'] ?? null, $capability['currency']);
        $settings = app(\App\Modules\Infrastructure\Settings::class)->current();
        if ($amount < Money::minor($settings['min_amount'], $capability['currency'])
            || $amount > Money::minor($settings['max_amount'], $capability['currency'])) {
            throw ValidationException::withMessages(['amount' => 'Montant hors des limites autorisées.']);
        }

        return ['kind' => 'deposit', 'amount' => $amount, 'reference' => $data['reference'], 'correspondent' => $capability['correspondent'],
            'msisdn' => $phone, 'currency' => $capability['currency'], 'country' => $capability['country']];
    }
}
