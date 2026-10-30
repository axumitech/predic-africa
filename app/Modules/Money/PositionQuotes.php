<?php

namespace App\Modules\Money;

use App\Models\User;
use App\Modules\Trading\FixedOdds;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PositionQuotes
{
    public static function fingerprint(object $quote, ?string $key = null): string
    {
        $fields = ['id', 'user_id', 'market_id', 'snapshot_id', 'reference', 'currency', 'outcome', 'odds',
            'wallet_stake', 'wallet_payout', 'base_stake', 'base_payout', 'rate', 'as_of', 'expires_at'];
        $values = [];
        foreach ($fields as $field) $values[$field] = (string) ($quote->$field ?? '');
        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), $key ?? config('app.key'));
    }

    public static function valid(object $quote): bool
    {
        foreach ([config('app.key'), ...config('app.previous_keys', [])] as $key) {
            if (is_string($key) && hash_equals($quote->fingerprint, self::fingerprint($quote, $key))) return true;
        }
        return false;
    }

    public function create(User $user, int $marketId, string $outcome, mixed $amount, string $reference): object
    {
        abort_if($user->suspended, 403);
        $market = DB::table('markets')->where('id', $marketId)->firstOrFail();
        if ($market->status !== 'open' || now()->gte($market->closes_at)) {
            throw ValidationException::withMessages(['amount' => 'Ce marché est fermé aux nouvelles positions.']);
        }
        $currency = DB::table('payment_wallets')->where('user_id', $user->id)->value('currency') ?? 'XAF';
        $rate = app(RateBook::class)->fresh($currency);
        $stake = Money::minor($amount, $currency);
        if ($stake < Money::minor($rate['min_stake'], $currency) || $stake > Money::minor($rate['max_stake'], $currency)) {
            throw ValidationException::withMessages(['amount' => 'La mise doit être comprise entre '.$rate['min_stake'].' et '.$rate['max_stake'].' '.$currency.'.']);
        }
        $base = Money::toBase($stake, $currency, $rate['rate']);
        $odds = $outcome === 'yes' ? $market->yes_odds : $market->no_odds;
        $basePayout = FixedOdds::payout($base, $odds);
        $quote = (object) ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'market_id' => $marketId,
            'snapshot_id' => $rate['snapshot_id'], 'reference' => $reference, 'currency' => $currency, 'outcome' => $outcome,
            'odds' => $odds, 'wallet_stake' => $stake, 'wallet_payout' => Money::payout($stake, $odds),
            'base_stake' => $base, 'base_payout' => $basePayout, 'rate' => $rate['rate'],
            'as_of' => CarbonImmutable::parse($rate['as_of'])->utc()->format('Y-m-d H:i:s'),
            'expires_at' => min(now()->addSeconds(config('fx.quote_seconds'))->utc()->format('Y-m-d H:i:s'),
                CarbonImmutable::parse($rate['expires_at'])->utc()->format('Y-m-d H:i:s')),
            'consumed_at' => null, 'transaction_id' => null, 'created_at' => now()->utc()->format('Y-m-d H:i:s')];
        $quote->fingerprint = self::fingerprint($quote);
        DB::table('fx_quotes')->insert((array) $quote);
        return $quote;
    }

    public static function publicData(object $quote): array
    {
        return ['id' => $quote->id, 'currency' => $quote->currency, 'outcome' => $quote->outcome, 'odds' => $quote->odds,
            'amount' => Money::decimal((int) $quote->wallet_stake, $quote->currency),
            'payout' => Money::decimal((int) $quote->wallet_payout, $quote->currency),
            'base_amount' => (string) $quote->base_stake, 'base_payout' => (string) $quote->base_payout,
            'rate' => $quote->rate, 'as_of' => CarbonImmutable::parse($quote->as_of, 'UTC')->toIso8601String(),
            'expires_at' => CarbonImmutable::parse($quote->expires_at, 'UTC')->toIso8601String(),
            'server_time' => now()->toIso8601String()];
    }
}
