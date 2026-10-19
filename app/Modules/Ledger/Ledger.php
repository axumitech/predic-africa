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
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Montant invalide ou solde insuffisant.']);
        }
        // Serialize all ledger writers before checking the reference, including
        // callers that do not already hold the user's lock.
        DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
        DB::table('accounts')->insertOrIgnore(['code' => 'simulation', 'balance' => 0]);
        DB::table('accounts')->insertOrIgnore(['code' => "user:$userId", 'user_id' => $userId, 'balance' => 0]);
        $accounts = DB::table('accounts')->whereIn('code', ['simulation', "user:$userId"])->orderBy('id')->lockForUpdate()->get();
        $account = $accounts->firstWhere('user_id', $userId);
        $bank = $accounts->firstWhere('code', 'simulation');
        // Preserve existing fingerprints; the immutable signed entry also binds
        // the reference to its direction, including transactions predating this fix.
        $fingerprint = hash('sha256', "$userId|$kind|$amount|$context");
        $prior = DB::table('ledger_transactions')->where('reference', $reference)->lockForUpdate()->first();
        if ($prior) {
            $signedAmount = DB::table('ledger_entries')->where('transaction_id', $prior->id)
                ->where('account_id', $account->id)->value('amount');
            if ($prior->fingerprint !== $fingerprint || $signedAmount === null || (int) $signedAmount !== ($credit ? $amount : -$amount)) {
                throw ValidationException::withMessages(['reference' => 'Cette référence a déjà été utilisée pour une autre opération.']);
            }

            return $prior->id;
        }
        if (! $credit && $account->balance < $amount) {
            throw ValidationException::withMessages(['amount' => 'Montant invalide ou solde insuffisant.']);
        }
        // Check signed BIGINT bounds before SQL arithmetic can overflow or
        // SQLite can silently promote a balance to floating point.
        if (($credit && $account->balance > PHP_INT_MAX - $amount)
            || (! $credit && $bank->balance > PHP_INT_MAX - $amount)
            || ($credit && $bank->balance < PHP_INT_MIN + $amount)) {
            throw ValidationException::withMessages(['amount' => 'Limite de solde atteinte.']);
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
