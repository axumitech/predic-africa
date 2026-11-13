<?php

namespace App\Modules\Settlement;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Markets\MarketState;
use App\Modules\Money\Money;
use App\Modules\Money\PositionQuotes;
use App\Modules\Notifications\Notifier;
use App\Modules\Trading\FixedOdds;
use App\Modules\Trading\PlacePosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ResolveMarket
{
    public function __construct(private Ledger $ledger) {}

    public function resolve(int $id, string $result, string $source, ?int $actor = null): void
    {
        $source = $this->validateDecision($result, $source);
        DB::transaction(function () use ($id, $result, $source, $actor) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $this->recordLocked($market, $result, $source, $actor, null);
            // Preserve all-or-nothing behavior for synchronous callers.
            $this->settleLocked($market, null);
        }, 3);
    }

    private function validateDecision(string $result, string $source): string
    {
        $source = trim($source);
        Validator::make(compact('result', 'source'), [
            'result' => ['required', Rule::in(['yes', 'no', 'void'])],
            'source' => 'required|string|min:10|max:2000',
        ])->validate();

        return $source;
    }

    public function record(int $id, string $result, string $source, ?int $actor = null, ?array $evidence = null): void
    {
        $source = $this->validateDecision($result, $source);
        DB::transaction(function () use ($id, $result, $source, $actor, $evidence) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            if ($this->recordLocked($market, $result, $source, $actor, $evidence)) {
                $label = ['yes' => 'Oui', 'no' => 'Non', 'void' => 'Annulation'][$result];
                Notifier::marketParticipants($id, "Décision officielle du marché #$id · $label.");
            }
        }, 3);
    }

    private function recordLocked(object $market, string $result, string $source, ?int $actor, ?array $evidence): bool
    {
        $id = (int) $market->id;
        if ($actor !== null) {
            $admin = User::whereKey($actor)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('admin');
        }
        if ($market->result !== null) {
            abort_unless($market->result === $result && in_array($market->status,
                ['resolved', 'settling', 'settled', 'cancelled', 'void'], true), 422, 'Résultat déjà enregistré.');
            if (in_array($market->status, ['settled', 'cancelled', 'void'], true)
                || ($market->status === 'resolved' && $market->resolved_at === null)) {
                abort_unless(in_array($market->status, ['cancelled', 'void'], true) === ($result === 'void'),
                    422, 'Statut et résultat incohérents. Un rapprochement est nécessaire.');
                abort_if(DB::table('positions')->where('market_id', $id)->where('status', 'open')->exists(),
                    422, 'Positions ouvertes sur un marché terminé. Un rapprochement est nécessaire.');
            }

            return false;
        }
        abort_if($result !== 'void' && now()->lt($market->closes_at), 422, 'Attendez la clôture du marché.');
        MarketState::closeIfDue($market);
        abort_unless(in_array($market->status, ['open', 'closed', 'pending_resolution', 'disputed'], true), 422, 'Ce marché ne peut pas être résolu.');
        abort_if(DB::table('positions')->where('market_id', $id)->where('status', '!=', 'open')->exists(),
            422, 'Positions déjà traitées. Un rapprochement est nécessaire.');
        MarketState::transition($market, $result === 'void' ? 'settling' : 'resolved', $source, [
            'result' => $result, 'resolution_source' => $source, 'resolved_at' => now(),
            'processing_due_at' => now(), 'processing_failures' => 0, 'processing_error' => null,
        ], $actor, $evidence);

        return true;
    }

    private static function reference(int $positionId): string
    {
        $hash = hash('sha256', "predic-africa:position-settlement:$positionId");

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-8'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    public function settleBatch(int $id, int $limit = 100): bool
    {
        return DB::transaction(function () use ($id, $limit) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();

            return $market ? $this->settleLocked($market, $limit) : true;
        }, 3);
    }

    private function settleLocked(object $market, ?int $limit): bool
    {
        $id = (int) $market->id;
        if ($market->resolved_at === null || ! in_array($market->status, ['resolved', 'settling'], true)) {
            return true;
        }
        $result = $market->result;
        abort_unless(in_array($result, ['yes', 'no', 'void'], true), 422, 'Résultat de règlement invalide.');
        if ($market->status === 'resolved') {
            MarketState::transition($market, 'settling', 'Démarrage du règlement.');
        }
        // The market lock serializes every application writer of its positions.
        // A range FOR UPDATE here also locks InnoDB index gaps, blocking bets
        // on other markets before we can acquire their participant's lock.
        $positions = DB::table('positions')->where('market_id', $id)->where('status', 'open')->orderBy('id')->when($limit !== null, fn ($q) => $q->limit($limit))->get();
        // Acquire every participant before the first ledger transfer. Never
        // hold the shared treasury account while waiting for another user.
        DB::table('users')->whereIn('id', $positions->pluck('user_id')->unique())
            ->orderBy('id')->lockForUpdate()->get();
        $debits = DB::table('ledger_transactions')->whereIn('id', $positions->pluck('transaction_id'))->get()->keyBy('id');
        $signedEntries = DB::table('ledger_entries')->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
            ->whereIn('transaction_id', $positions->pluck('transaction_id'))->whereNotNull('accounts.user_id')
            ->select('transaction_id', 'accounts.user_id', 'amount', 'ledger_entries.currency', 'accounts.currency as account_currency')->get()->keyBy('transaction_id');
        $quotes = DB::table('fx_quotes')->whereIn('id', $positions->pluck('fx_quote_id')->filter())->get()->keyBy('id');
        foreach ($positions as $position) {
            $debit = $debits->get($position->transaction_id);
            $entry = $signedEntries->get($position->transaction_id);
            $currency = $position->wallet_currency;
            $walletStake = (int) ($position->wallet_stake ?? $position->stake);
            $walletPayout = (int) ($position->wallet_payout ?? $position->payout);
            $context = PlacePosition::context($id, $position->outcome, $currency, $position->fx_quote_id);
            $fingerprint = Ledger::fingerprint($position->user_id, 'Mise', $walletStake, $context, $currency);
            $quote = $quotes->get($position->fx_quote_id);
            if ($position->fx_quote_id || $currency !== 'XAF') {
                abort_unless($quote && PositionQuotes::valid($quote)
                    && $quote->consumed_at !== null && (int) $quote->transaction_id === (int) $position->transaction_id
                    && (int) $quote->user_id === (int) $position->user_id && (int) $quote->market_id === $id
                    && $quote->currency === $currency && $quote->outcome === $position->outcome
                    && (int) $quote->odds === (int) $position->odds && (int) $quote->base_stake === (int) $position->stake
                    && (int) $quote->base_payout === (int) $position->payout && (int) $quote->wallet_stake === $walletStake
                    && (int) $quote->wallet_payout === $walletPayout, 422, 'Devis de position incohérent. Rapprochement nécessaire.');
            }
            abort_unless($debit && $entry && (int) $debit->user_id === (int) $position->user_id
                && $debit->kind === 'Mise' && $debit->currency === $currency && (int) $debit->amount === $walletStake
                && $debit->fingerprint === $fingerprint && (int) $entry->user_id === (int) $position->user_id
                && $entry->currency === $currency && $entry->account_currency === $currency && (int) $entry->amount === -$walletStake,
                422, 'Débit de position incohérent. Un rapprochement est nécessaire.');
            abort_unless(in_array($position->outcome, ['yes', 'no'], true)
                && (int) $position->payout === FixedOdds::payout($position->stake, $position->odds)
                && $walletPayout === Money::payout($walletStake, $position->odds),
                422, 'Position incohérente. Un rapprochement est nécessaire.');
            $refund = $result === 'void';
            $won = $position->outcome === $result;
            $amount = $refund ? $walletStake : ($won ? $walletPayout : 0);
            $status = $refund ? 'refunded' : ($won ? 'won' : 'lost');
            $credit = $amount === 0 ? null : $this->ledger->transfer(
                $position->user_id, self::reference((int) $position->id), $refund ? 'Remboursement' : 'Gain',
                $amount, true, "position:$position->id", currency: $currency,
            );
            DB::table('position_settlements')->insert([
                'position_id' => $position->id, 'ledger_transaction_id' => $credit,
                'status' => $status, 'amount' => $amount, 'currency' => $currency, 'created_at' => now(),
            ]);
            if ($status === 'lost') {
                Notifier::send($position->user_id, "La position #$position->id est perdue après résolution du marché #$id.");
            }
            DB::table('positions')->where('id', $position->id)->update(['status' => $status, 'updated_at' => now()]);
        }
        $done = ! DB::table('positions')->where('market_id', $id)->where('status', 'open')->exists();
        if ($done) {
            $mismatch = DB::table('positions as p')
                ->leftJoin('position_settlements as s', 's.position_id', '=', 'p.id')
                ->leftJoin('ledger_transactions as t', 't.id', '=', 's.ledger_transaction_id')
                ->leftJoin('payment_movements as m', 'm.ledger_transaction_id', '=', 't.id')
                ->where('p.market_id', $id)->where(function ($query) {
                    $query->whereNull('s.position_id')->orWhereColumn('s.status', '!=', 'p.status')
                        ->orWhereColumn('s.currency', '!=', 'p.wallet_currency')
                        ->orWhereRaw("s.amount <> CASE p.status WHEN 'won' THEN COALESCE(p.wallet_payout, p.payout)
                            WHEN 'refunded' THEN COALESCE(p.wallet_stake, p.stake) ELSE 0 END")
                        ->orWhere(fn ($q) => $q->where('s.amount', '>', 0)->where(fn ($credit) => $credit
                            ->whereNull('t.id')->orWhereColumn('t.amount', '!=', 's.amount')
                            ->orWhereColumn('t.currency', '!=', 's.currency')->orWhereColumn('t.user_id', '!=', 'p.user_id')
                            ->orWhereNull('m.id')->orWhereColumn('m.amount', '!=', 's.amount')
                            ->orWhereColumn('m.currency', '!=', 's.currency')->orWhereColumn('m.user_id', '!=', 'p.user_id')))
                        ->orWhere(fn ($q) => $q->where('s.amount', 0)->whereNotNull('t.id'));
                })->exists();
            abort_if($mismatch, 422, 'Écriture de règlement incohérente. Un rapprochement est nécessaire.');
            MarketState::transition($market, $result === 'void' ? 'cancelled' : 'settled', 'Règlement terminé.', [
                'settled_at' => now(), 'processing_due_at' => null, 'processing_failures' => 0, 'processing_error' => null,
            ]);
        } else {
            DB::table('markets')->where('id', $id)->update(['processing_failures' => 0, 'processing_error' => null]);
        }

        return $done;
    }
}
