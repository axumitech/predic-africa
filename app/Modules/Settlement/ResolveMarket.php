<?php

namespace App\Modules\Settlement;

use App\Modules\Ledger\Ledger;
use App\Modules\Notifications\Notifier;
use App\Modules\Trading\FixedOdds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResolveMarket
{
    public function __construct(private Ledger $ledger) {}

    // The admin HTTP boundary authorizes this internal application operation.
    public function resolve(int $id, string $result, string $source): void
    {
        $source = trim($source);
        Validator::make(compact('result', 'source'), [
            'result' => ['required', Rule::in(['yes', 'no', 'void'])],
            'source' => 'required|string|min:10|max:2000',
        ])->validate();
        DB::transaction(function () use ($id, $result, $source) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            if (in_array($market->status, ['resolved', 'void'])) {
                abort_unless($market->result === $result
                    && $market->status === ($result === 'void' ? 'void' : 'resolved'), 422, 'Résultat déjà enregistré.');
                abort_if(DB::table('positions')->where('market_id', $id)->where('status', 'open')->exists(),
                    422, 'Positions ouvertes sur un marché terminé. Un rapprochement est nécessaire.');

                return;
            }
            abort_unless($market->status === 'open', 422, 'Seul un marché publié peut être résolu.');
            abort_if($result !== 'void' && now()->lt($market->closes_at), 422, 'Attendez la clôture du marché.');
            abort_if(DB::table('positions')->where('market_id', $id)->where('status', '!=', 'open')->exists(),
                422, 'Positions déjà traitées sur un marché ouvert. Un rapprochement est nécessaire.');
            // The market lock serializes every application writer of its positions.
            // A range FOR UPDATE here also locks InnoDB index gaps, blocking bets
            // on other markets before we can acquire their participant's lock.
            $positions = DB::table('positions')->where('market_id', $id)->where('status', 'open')->orderBy('user_id')->orderBy('id')->get();
            // Acquire every participant before the first ledger transfer. Never
            // hold the shared simulation account while waiting for another user.
            DB::table('users')->whereIn('id', $positions->pluck('user_id')->unique())
                ->orderBy('id')->lockForUpdate()->get();
            $debits = DB::table('ledger_transactions')->whereIn('id', $positions->pluck('transaction_id'))->get()->keyBy('id');
            $signedEntries = DB::table('ledger_entries')->join('accounts', 'accounts.id', '=', 'ledger_entries.account_id')
                ->whereIn('transaction_id', $positions->pluck('transaction_id'))->whereNotNull('accounts.user_id')
                ->select('transaction_id', 'accounts.user_id', 'amount')->get()->keyBy('transaction_id');
            foreach ($positions as $position) {
                $debit = $debits->get($position->transaction_id);
                $entry = $signedEntries->get($position->transaction_id);
                $fingerprint = hash('sha256', "$position->user_id|Mise|$position->stake|$id|$position->outcome");
                abort_unless($debit && $entry && (int) $debit->user_id === (int) $position->user_id
                    && $debit->kind === 'Mise' && (int) $debit->amount === (int) $position->stake
                    && $debit->fingerprint === $fingerprint && (int) $entry->user_id === (int) $position->user_id
                    && (int) $entry->amount === -$position->stake,
                    422, 'Débit de position incohérent. Un rapprochement est nécessaire.');
                abort_unless(in_array($position->outcome, ['yes', 'no'], true)
                    && (int) $position->payout === FixedOdds::payout($position->stake, $position->odds),
                    422, 'Position incohérente. Un rapprochement est nécessaire.');
                $refund = $result === 'void';
                $won = $position->outcome === $result;
                if ($refund || $won) {
                    $this->ledger->transfer($position->user_id, (string) Str::uuid(), $refund ? 'Remboursement' : 'Gain', $refund ? $position->stake : $position->payout, true, "position:$position->id");
                }
                if (! $refund && ! $won) {
                    Notifier::send($position->user_id, "La position #$position->id est perdue après résolution du marché #$id.");
                }
                DB::table('positions')->where('id', $position->id)->update(['status' => $refund ? 'refunded' : ($won ? 'won' : 'lost'), 'updated_at' => now()]);
            }
            DB::table('markets')->where('id', $id)->update(['status' => $result === 'void' ? 'void' : 'resolved', 'result' => $result, 'resolution_source' => $source, 'updated_at' => now()]);
        }, 3);
    }
}
