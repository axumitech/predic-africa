<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileLedger extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Contrôler le journal sans modifier les soldes (exécuter sans écritures concurrentes)';

    public function handle(): int
    {
        $entries = DB::table('ledger_entries')->selectRaw('account_id, SUM(amount) AS total')->groupBy('account_id');
        $accounts = DB::table('accounts')->leftJoinSub($entries, 'entries', 'accounts.id', '=', 'entries.account_id')
            ->whereRaw('accounts.balance <> COALESCE(entries.total, 0)')
            ->orWhere(fn ($query) => $query->whereNotNull('accounts.user_id')->where('accounts.balance', '<', 0))->count();
        $pairs = DB::table('ledger_entries')->selectRaw('transaction_id, COUNT(*) AS entry_count, SUM(amount) AS total, COUNT(DISTINCT account_id) AS account_count')->groupBy('transaction_id');
        $transactions = DB::table('ledger_transactions')->leftJoinSub($pairs, 'entries', 'ledger_transactions.id', '=', 'entries.transaction_id')
            ->whereRaw('COALESCE(entries.entry_count, 0) <> 2 OR entries.total <> 0 OR entries.account_count <> 2')->count();
        $this->line("Comptes incohérents : $accounts ; transactions déséquilibrées ou incomplètes : $transactions.");

        $wallets = DB::table('payment_wallets')->leftJoin('accounts', fn ($join) => $join->on('accounts.user_id', '=', 'payment_wallets.user_id')->on('accounts.currency', '=', 'payment_wallets.currency'))
            ->where(fn ($query) => $query->whereNull('accounts.id')->orWhereColumn('accounts.balance', '!=', 'payment_wallets.balance'))->count();
        if ($wallets > 0) {
            $this->line("Portefeuilles désynchronisés : $wallets.");
        }
        $currencyErrors = DB::table('ledger_entries as e')->join('accounts as a', 'a.id', '=', 'e.account_id')
            ->join('ledger_transactions as t', 't.id', '=', 'e.transaction_id')
            ->where(fn ($q) => $q->whereColumn('e.currency', '!=', 'a.currency')->orWhereColumn('e.currency', '!=', 't.currency'))->count();
        $currencyErrors += DB::table('payment_wallets as w')->join('accounts as a', fn ($join) => $join->on('a.user_id', '=', 'w.user_id')->on('a.currency', '=', 'w.currency'))
            ->whereColumn('w.currency', '!=', 'a.currency')->count();
        $currencyErrors += DB::table('payment_movements as m')->join('ledger_transactions as t', 't.id', '=', 'm.ledger_transaction_id')
            ->whereColumn('m.currency', '!=', 't.currency')->count();
        if ($currencyErrors > 0) $this->line("Devises incohérentes : $currencyErrors.");

        return $accounts === 0 && $transactions === 0 && $wallets === 0 && $currencyErrors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
