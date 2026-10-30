<?php

namespace App\Modules\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RateSync
{
    public function refresh(): bool
    {
        if (! config('fx.key')) return false;
        // Durable throttle, including concurrent CLI processes and failures. Never
        // fetch from within a bet, settlement, payment confirmation or page request.
        $claimed = DB::transaction(function () {
            $state = DB::table('fx_sync_state')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($state->next_attempt_at && now()->lt($state->next_attempt_at)) return false;
            DB::table('fx_sync_state')->where('id', 1)->update(['next_attempt_at' => now()->addSeconds(config('fx.refresh_seconds'))]);
            return true;
        });
        if (! $claimed) return false;
        $error = 'unavailable';
        $retry = (int) config('fx.refresh_seconds');
        try {
            $response = Http::withHeaders(['apikey' => config('fx.key')])->acceptJson()->withoutRedirecting()
                ->withOptions(['verify' => true])->connectTimeout(3)->timeout(config('fx.timeout'))
                ->get('https://api.currencyapi.com/v3/latest', ['base_currency' => 'XAF',
                    'currencies' => implode(',', array_keys(Money::SCALES))]);
            if ($response->status() === 429) {
                $error = 'rate_limited';
                $header = $response->header('Retry-After');
                $retry = ctype_digit($header) ? min(86400, max(60, (int) $header)) : 900;
                throw new \UnexpectedValueException;
            }
            if (! $response->successful()) {
                $error = in_array($response->status(), [401, 403]) ? 'credentials_or_plan' : 'unavailable';
                throw new \UnexpectedValueException;
            }
            $error = 'invalid_response';
            $json = ExactJson::decode($response->body());
            if (($json['meta']['base_currency'] ?? null) !== 'XAF'
                || ! is_string($json['meta']['last_updated_at'] ?? null)
                || ! preg_match('/\A\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:\.\d+)?(?:Z|\+00:00)\z/', $json['meta']['last_updated_at'])) {
                throw new \UnexpectedValueException;
            }
            $asOf = CarbonImmutable::parse($json['meta']['last_updated_at']);
            if ($asOf->gt(now()->addSeconds(30)) || $asOf->lte(now()->subSeconds(config('fx.max_age_seconds')))) {
                $error = 'stale_source';
                throw new \UnexpectedValueException;
            }
            $rates = ['XAF' => '1'];
            foreach (Money::SCALES as $currency => $_) {
                $value = $json['data'][$currency] ?? null;
                if ($value === null && $currency !== 'XAF') continue;
                if (! is_array($value) || ($value['code'] ?? null) !== $currency
                    || (! is_int($value['value'] ?? null) && ! is_string($value['value'] ?? null))
                    || ! preg_match('/\A[0-9]{1,12}(?:\.[0-9]{1,24})?(?:[eE][+-]?[0-9]{1,2})?\z/D', (string) $value['value'])) throw new \UnexpectedValueException;
                $decimal = BigDecimal::of($value['value']);
                if ($decimal->isLessThan('0.00000001') || $decimal->isGreaterThan('1000000')
                    || ($currency === 'XAF' && ! $decimal->isEqualTo(1))) throw new \UnexpectedValueException;
                $rates[$currency] = (string) $decimal->toScale(18, RoundingMode::HalfEven);
            }
            DB::transaction(function () use ($rates, $asOf) {
                DB::table('fx_snapshots')->insertOrIgnore(['provider' => config('fx.provider'), 'base_currency' => 'XAF',
                    'rates' => json_encode($rates, JSON_THROW_ON_ERROR), 'as_of' => $asOf, 'fetched_at' => now()]);
                DB::table('fx_sync_state')->where('id', 1)->update(['last_success_at' => now(), 'last_error' => null, 'attempts' => 0]);
            });
            return true;
        } catch (\Throwable) {
            // Never log raw provider responses, credentials, or exception messages.
            DB::table('fx_sync_state')->where('id', 1)->update(['last_error' => $error,
                'attempts' => DB::raw('attempts + 1'), 'next_attempt_at' => now()->addSeconds(max($retry, (int) config('fx.refresh_seconds')))]);
            return false;
        }
    }
}
