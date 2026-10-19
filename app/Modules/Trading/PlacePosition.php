<?php

namespace App\Modules\Trading;

use App\Modules\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlacePosition
{
    public function __construct(private Ledger $ledger) {}

    public function place(int $userId, int $id, string $outcome, int $amount, string $reference): void
    {
        Validator::make(compact('outcome', 'amount', 'reference'), [
            'outcome' => ['required', Rule::in(['yes', 'no'])],
            'amount' => 'required|integer|min:100|max:1000000', 'reference' => 'required|uuid',
        ])->validate();
        DB::transaction(function () use ($userId, $id, $reference, $amount, $outcome) {
            // Market first, then user, then accounts: same order as settlement.
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
            abort_if($user->suspended, 403, 'Compte suspendu.');
            // Preliminary existence check only; Ledger rechecks under account locks.
            // Do not lock a ledger row before accounts (opposite to Ledger's order).
            $prior = DB::table('ledger_transactions')->where('reference', $reference)->first();
            if (! $prior && ($market->status !== 'open' || now()->gte($market->closes_at))) {
                throw ValidationException::withMessages(['amount' => 'Ce marché est fermé aux nouvelles positions.']);
            }
            $transaction = $this->ledger->transfer($userId, $reference, 'Mise', (int) $amount, false, "$id|{$outcome}");
            $position = DB::table('positions')->where('transaction_id', $transaction)->first();
            if ($position) {
                if ((int) $position->user_id !== $userId || (int) $position->market_id !== $id
                    || $position->outcome !== $outcome || (int) $position->stake !== $amount) {
                    throw ValidationException::withMessages(['reference' => 'Position associée incohérente. Un rapprochement est nécessaire.']);
                }

                return;
            }
            if ($prior) {
                // Never manufacture a position from an orphaned debit, especially
                // after the market has already been settled.
                throw ValidationException::withMessages(['reference' => 'Position associée introuvable. Un rapprochement est nécessaire.']);
            }
            $odds = $outcome === 'yes' ? $market->yes_odds : $market->no_odds;
            $payout = FixedOdds::payout($amount, $odds);
            DB::table('positions')->insert([
                'user_id' => $userId, 'market_id' => $id, 'transaction_id' => $transaction,
                'outcome' => $outcome, 'stake' => $amount, 'odds' => $odds,
                'payout' => $payout, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }
}
