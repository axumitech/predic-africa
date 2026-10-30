<?php

namespace App\Modules\Trading;

use App\Modules\Ledger\Ledger;
use App\Modules\Money\Money;
use App\Modules\Money\PositionQuotes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlacePosition
{
    public function __construct(private Ledger $ledger) {}

    public function place(int $userId, int $id, string $outcome, int|string $amount, string $reference, ?string $quoteId = null): void
    {
        Validator::make(compact('outcome', 'amount', 'reference'), [
            'outcome' => ['required', Rule::in(['yes', 'no'])],
            'amount' => 'required', 'reference' => 'required|uuid',
        ])->validate();
        DB::transaction(function () use ($userId, $id, $reference, $amount, $outcome, $quoteId) {
            // Market first, then user, then accounts: same order as settlement.
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
            abort_if($user->suspended, 403, 'Compte suspendu.');
            $currency = DB::table('payment_wallets')->where('user_id', $userId)->value('currency') ?? 'XAF';
            $walletStake = Money::minor($amount, $currency);
            $prior = DB::table('ledger_transactions')->where('reference', $reference)->first();
            if ($prior) {
                $position = DB::table('positions')->where('transaction_id', $prior->id)->first();
                if (! $position || (int) $position->user_id !== $userId || (int) $position->market_id !== $id
                    || $position->outcome !== $outcome || $position->wallet_currency !== $currency
                    || (int) ($position->wallet_stake ?? $position->stake) !== $walletStake
                    || ($quoteId !== null && $position->fx_quote_id !== $quoteId)) {
                    throw ValidationException::withMessages(['reference' => 'Position associée incohérente ou introuvable. Un rapprochement est nécessaire.']);
                }
                $context = self::context($id, $outcome, $currency, $position->fx_quote_id);
                // Replay needs neither a current rate nor an unexpired quote.
                $this->ledger->transfer($userId, $reference, 'Mise', $walletStake, false, $context, currency: $currency);
                return;
            }
            if ($market->status !== 'open' || now()->gte($market->closes_at)) {
                throw ValidationException::withMessages(['amount' => 'Ce marché est fermé aux nouvelles positions.']);
            }
            $odds = $outcome === 'yes' ? $market->yes_odds : $market->no_odds;
            $quote = null;
            if ($quoteId !== null || $currency !== 'XAF') {
                $quote = DB::table('fx_quotes')->where('id', $quoteId)->lockForUpdate()->first();
                if (! $quote || (int) $quote->user_id !== $userId || (int) $quote->market_id !== $id
                    || $quote->currency !== $currency || $quote->reference !== $reference || $quote->outcome !== $outcome
                    || (int) $quote->wallet_stake !== $walletStake || (int) $quote->odds !== (int) $odds
                    || $quote->consumed_at !== null || now()->gte($quote->expires_at)
                    || ! PositionQuotes::valid($quote)) {
                    throw ValidationException::withMessages(['quote_id' => 'Le devis est expiré ou ne correspond plus à votre position. Actualisez-le avant de confirmer.']);
                }
            }
            $baseStake = $quote ? (int) $quote->base_stake : $walletStake;
            $payout = FixedOdds::payout($baseStake, $odds);
            $walletPayout = Money::payout($walletStake, $odds);
            if ($quote && ((int) $quote->base_payout !== $payout || (int) $quote->wallet_payout !== $walletPayout)) {
                throw ValidationException::withMessages(['quote_id' => 'Devis incohérent. Actualisez-le.']);
            }
            $transaction = $this->ledger->transfer($userId, $reference, 'Mise', $walletStake, false,
                self::context($id, $outcome, $currency, $quoteId), currency: $currency);
            DB::table('positions')->insert([
                'user_id' => $userId, 'market_id' => $id, 'transaction_id' => $transaction,
                'outcome' => $outcome, 'stake' => $baseStake, 'odds' => $odds,
                'wallet_currency' => $currency, 'wallet_stake' => $walletStake, 'wallet_payout' => $walletPayout, 'fx_quote_id' => $quoteId,
                'payout' => $payout, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($quote) {
                DB::table('fx_quotes')->where('id', $quoteId)->update(['consumed_at' => now(), 'transaction_id' => $transaction]);
            }
        }, 3);
    }
    public static function context(int $marketId, string $outcome, string $currency, ?string $quoteId): string
    {
        // Keep the old XAF format so historical debits and references remain valid.
        return "$marketId|$outcome".($currency === 'XAF' ? '' : "|$currency|$quoteId");
    }
}
