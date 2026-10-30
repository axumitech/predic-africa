<?php

namespace App\Modules\Ledger;

use App\Modules\Notifications\Notifier;
use App\Modules\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ledger
{
    public static function fingerprint(int $userId, string $kind, int $amount, string $context, string $currency): string
    {
        // Preserve the historical XAF fingerprint, including old positions.
        return hash('sha256', "$userId|$kind|$amount|$context".($currency === 'XAF' ? '' : "|$currency"));
    }

    // All callers hold a database transaction. Signed pairs always sum to zero.
    public function transfer(int $userId, string $reference, string $kind, int $amount, bool $credit, string $context = '', ?int $paymentId = null, ?string $effect = null, ?string $currency = null): int
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
        $currency ??= \App\Modules\Money\Wallets::currency($userId);
        Money::scale($currency);
        if (! DB::table('payment_wallets')->where('user_id', $userId)->exists()) {
            DB::table('payment_wallets')->insert(['user_id' => $userId, 'currency' => 'XAF', 'balance' => 0]);
        }
        if (! DB::table('payment_wallets')->where('user_id', $userId)->where('currency', $currency)->exists()) {
            throw ValidationException::withMessages(['amount' => 'Devise incompatible avec le portefeuille.']);
        }
        $accountCode = \App\Modules\Money\Wallets::accountCode($userId, $currency);
        $treasury = $currency === 'XAF' ? 'treasury' : 'treasury:'.$currency;
        DB::table('accounts')->insertOrIgnore(['code' => $treasury, 'currency' => $currency, 'balance' => 0]);
        DB::table('accounts')->insertOrIgnore(['code' => $accountCode, 'user_id' => $userId, 'currency' => $currency, 'balance' => 0]);
        $accounts = DB::table('accounts')->whereIn('code', [$treasury, $accountCode])->orderBy('id')->lockForUpdate()->get();
        $account = $accounts->firstWhere('user_id', $userId);
        $bank = $accounts->firstWhere('code', $treasury);
        // Preserve existing fingerprints; the immutable signed entry also binds
        // the reference to its direction, including transactions predating this fix.
        $fingerprint = self::fingerprint($userId, $kind, $amount, $context, $currency);
        $prior = DB::table('ledger_transactions')->where('reference', $reference)->lockForUpdate()->first();
        if ($prior) {
            $signedAmount = DB::table('ledger_entries')->where('transaction_id', $prior->id)
                ->where('account_id', $account->id)->value('amount');
            if ($prior->currency !== $currency || $prior->fingerprint !== $fingerprint || $signedAmount === null || (int) $signedAmount !== ($credit ? $amount : -$amount)) {
                throw ValidationException::withMessages(['reference' => 'Cette référence a déjà été utilisée pour une autre opération.']);
            }

            return $prior->id;
        }
        $wallet = DB::table('payment_wallets')->where('user_id', $userId)->where('currency', $currency)->lockForUpdate()->firstOrFail();
        if ($wallet->currency !== $currency || $account->currency !== $currency || $bank->currency !== $currency
            || (int) $wallet->balance !== (int) $account->balance) {
            throw new \LogicException('Solde du portefeuille et journal incohérents.');
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
            'fingerprint' => $fingerprint, 'amount' => $amount, 'currency' => $currency, 'created_at' => now(),
        ]);
        $delta = $credit ? $amount : -$amount;
        DB::table('ledger_entries')->insert([
            ['transaction_id' => $id, 'account_id' => $account->id, 'currency' => $currency, 'amount' => $delta],
            ['transaction_id' => $id, 'account_id' => $bank->id, 'currency' => $currency, 'amount' => -$delta],
        ]);
        DB::table('accounts')->where('id', $account->id)->increment('balance', $delta);
        DB::table('accounts')->where('id', $bank->id)->decrement('balance', $delta);
        DB::table('payment_wallets')->where('id', $wallet->id)->increment('balance', $delta);
        DB::table('payment_movements')->insert([
            'payment_id' => $paymentId, 'user_id' => $userId, 'ledger_transaction_id' => $id,
            'effect' => $effect ?? ($credit ? 'credit' : 'debit'), 'currency' => $currency, 'amount' => $delta, 'created_at' => now(),
        ]);
        if ($paymentId === null) {
            Notifier::send($userId, $kind.' : '.Money::decimal($amount, $currency).' '.$currency.'.');
        }

        return $id;
    }
}
