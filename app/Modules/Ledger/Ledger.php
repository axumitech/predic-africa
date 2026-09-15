<?php

namespace App\Modules\Ledger;

use App\Modules\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ledger
{
    // All callers hold a database transaction. Signed pairs always sum to zero.
    public function transfer(int $userId, string $reference, string $kind, int $amount, bool $credit, string $context = ''): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Ledger requires a transaction.');
        }
        $fingerprint = hash('sha256', "$userId|$kind|$amount|$context");
        $prior = DB::table('ledger_transactions')->where('reference', $reference)->first();
        if ($prior) {
            if ($prior->fingerprint !== $fingerprint) {
                throw ValidationException::withMessages(['reference' => 'Cette référence a déjà été utilisée pour une autre opération.']);
            }

            return $prior->id;
        }
        DB::table('accounts')->insertOrIgnore(['code' => 'simulation', 'balance' => 0]);
        DB::table('accounts')->insertOrIgnore(['code' => "user:$userId", 'user_id' => $userId, 'balance' => 0]);
        $accounts = DB::table('accounts')->whereIn('code', ['simulation', "user:$userId"])->orderBy('id')->lockForUpdate()->get();
        $account = $accounts->firstWhere('user_id', $userId);
        $bank = $accounts->firstWhere('code', 'simulation');
        if ($amount <= 0 || (! $credit && $account->balance < $amount)) {
            throw ValidationException::withMessages(['amount' => 'Montant invalide ou solde insuffisant.']);
        }
        $id = DB::table('ledger_transactions')->insertGetId([
            'reference' => $reference, 'user_id' => $userId, 'kind' => $kind,
            'fingerprint' => $fingerprint, 'amount' => $amount, 'created_at' => now(),
        ]);
        $delta = $credit ? $amount : -$amount;
        DB::table('ledger_entries')->insert([
            ['transaction_id' => $id, 'account_id' => $account->id, 'amount' => $delta],
            ['transaction_id' => $id, 'account_id' => $bank->id, 'amount' => -$delta],
        ]);
        DB::table('accounts')->where('id', $account->id)->increment('balance', $delta);
        DB::table('accounts')->where('id', $bank->id)->decrement('balance', $delta);
        Notifier::send($userId, "$kind : $amount crédits de démonstration.");

        return $id;
    }
}
