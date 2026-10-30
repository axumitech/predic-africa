<?php

namespace App\Modules\Money;

use App\Modules\Ledger\Ledger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletTransfers
{
    public function quote(int $userId, string $source, string $target, mixed $amount): object
    {
        return DB::transaction(function () use ($userId, $source, $target, $amount) {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
            abort_if($user->suspended, 403);
            if ($source === $target) $this->invalid('Choisissez deux portefeuilles de devises différentes.');
            $wallets = DB::table('payment_wallets')->where('user_id', $userId)->whereIn('currency', [$source, $target])->get();
            if ($wallets->count() !== 2) $this->invalid('Les deux portefeuilles doivent vous appartenir.');
            $minor = Money::minor($amount, $source);
            if ($minor <= 0 || $minor > $wallets->firstWhere('currency', $source)->balance) $this->invalid('Montant invalide ou solde insuffisant.');
            // Both legs use one immutable snapshot; never mix rates from different dates.
            $snapshot = DB::table('fx_snapshots')->where('provider', config('fx.provider'))->where('base_currency', 'XAF')->orderByDesc('as_of')->first();
            if (! $snapshot) $this->invalid('Le taux de change est indisponible. Réessayez après son actualisation.');
            $asOf = CarbonImmutable::parse($snapshot->as_of, 'UTC');
            $rateExpiry = $asOf->addSeconds(config('fx.max_age_seconds'));
            if ($asOf->gt(now()->addSeconds(30)) || $rateExpiry->lte(now())) $this->invalid('Le taux de change est trop ancien. Réessayez après son actualisation.');
            try {
                $rates = json_decode($snapshot->rates, true, 32, JSON_THROW_ON_ERROR);
                $from = BigDecimal::of($source === 'XAF' ? '1' : ($rates[$source] ?? '0'));
                $to = BigDecimal::of($target === 'XAF' ? '1' : ($rates[$target] ?? '0'));
                if ($from->isLessThanOrEqualTo(0) || $to->isLessThanOrEqualTo(0)) throw new \RuntimeException;
                // Round only once, in target minor units, so a truncated display rate cannot change the credit.
                $converted = BigDecimal::of(Money::decimal($minor, $source))->multipliedBy($to)
                    ->withPointMovedRight(Money::scale($target))->dividedBy($from, 0, RoundingMode::Down)->toInt();
                $rate = (string) $to->dividedBy($from, 18, RoundingMode::Down);
            } catch (\Throwable) {
                $this->invalid('Conversion indisponible pour ce montant ou ces devises.');
            }
            if ($converted <= 0) $this->invalid('Le montant est trop faible pour cette conversion.');
            $quote = (object) ['id' => (string) Str::uuid(), 'user_id' => $userId, 'source_currency' => $source,
                'target_currency' => $target, 'source_amount' => $minor, 'target_amount' => $converted,
                'rate' => $rate, 'snapshot_id' => $snapshot->id, 'as_of' => $asOf->format('Y-m-d H:i:s'),
                'expires_at' => min(now()->addSeconds(config('fx.quote_seconds'))->utc()->format('Y-m-d H:i:s'), $rateExpiry->format('Y-m-d H:i:s')),
                'confirmed_at' => null, 'debit_reference' => (string) Str::uuid(), 'credit_reference' => (string) Str::uuid(),
                'created_at' => now()->utc()->format('Y-m-d H:i:s')];
            DB::table('wallet_transfer_quotes')->insert((array) $quote);
            return $quote;
        });
    }

    public function confirm(int $userId, string $quoteId): void
    {
        DB::transaction(function () use ($userId, $quoteId) {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
            abort_if($user->suspended, 403);
            $quote = DB::table('wallet_transfer_quotes')->where('id', $quoteId)->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($quote, 404);
            // A retry acknowledges the original transfer, even after quote expiration.
            if ($quote->confirmed_at !== null) return;
            if (now()->gte(CarbonImmutable::parse($quote->expires_at, 'UTC'))) $this->invalid('Le devis a expiré. Demandez un nouveau taux avant de confirmer.');
            $ledger = app(Ledger::class);
            $ledger->transfer($userId, $quote->debit_reference, 'Transfert sortant', (int) $quote->source_amount, false, "wallet-transfer:$quoteId", currency: $quote->source_currency);
            $ledger->transfer($userId, $quote->credit_reference, 'Transfert entrant', (int) $quote->target_amount, true, "wallet-transfer:$quoteId", currency: $quote->target_currency);
            DB::table('wallet_transfer_quotes')->where('id', $quoteId)->update(['confirmed_at' => now()->utc()]);
        }, 3);
    }

    public static function publicData(object $quote): array
    {
        return ['id' => $quote->id, 'source_currency' => $quote->source_currency, 'target_currency' => $quote->target_currency,
            'source_amount' => Money::decimal((int) $quote->source_amount, $quote->source_currency),
            'target_amount' => Money::decimal((int) $quote->target_amount, $quote->target_currency), 'rate' => $quote->rate,
            'as_of' => CarbonImmutable::parse($quote->as_of, 'UTC')->toIso8601String(),
            'expires_at' => CarbonImmutable::parse($quote->expires_at, 'UTC')->toIso8601String(), 'server_time' => now()->toIso8601String()];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }
}
