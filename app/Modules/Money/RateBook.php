<?php

namespace App\Modules\Money;

use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RateBook
{
    public function view(string $currency): array
    {
        $base = ['base' => 'XAF', 'currency' => $currency, 'scale' => Money::scale($currency),
            'available' => false, 'rate' => null, 'as_of' => null, 'expires_at' => null,
            'snapshot_id' => null, 'provider' => config('fx.provider'), 'server_time' => now()->toIso8601String(),
            'min_stake' => null, 'max_stake' => null];
        if ($currency === 'XAF') {
            return [...$base, 'available' => true, 'rate' => '1', 'as_of' => now()->toIso8601String(),
                'expires_at' => now()->addSeconds(config('fx.max_age_seconds'))->toIso8601String(),
                'min_stake' => '100', 'max_stake' => '1000000'];
        }
        $snapshot = DB::table('fx_snapshots')->where('provider', config('fx.provider'))->where('base_currency', 'XAF')->orderByDesc('as_of')->first();
        if (! $snapshot) return $base;
        $asOf = CarbonImmutable::parse($snapshot->as_of, 'UTC');
        $expires = $asOf->addSeconds(config('fx.max_age_seconds'));
        $rate = json_decode($snapshot->rates, true, 32, JSON_THROW_ON_ERROR)[$currency] ?? null;
        $fresh = $rate !== null && $asOf->lte(now()->addSeconds(30)) && $expires->gt(now());

        return [...$base, 'available' => $fresh, 'rate' => $rate, 'as_of' => $asOf->toIso8601String(),
            'expires_at' => $expires->toIso8601String(), 'snapshot_id' => $snapshot->id,
            'min_stake' => $rate ? Money::decimal(Money::convert(100, 'XAF', $currency, $rate, RoundingMode::Up), $currency) : null,
            'max_stake' => $rate ? Money::decimal(Money::convert(1000000, 'XAF', $currency, $rate), $currency) : null];
    }

    public function fresh(string $currency): array
    {
        $rate = $this->view($currency);
        if (! $rate['available']) {
            throw ValidationException::withMessages(['quote_id' => 'Le taux de change est indisponible ou trop ancien. Aucune mise n’a été débitée. Réessayez après son actualisation.']);
        }

        return $rate;
    }
}
