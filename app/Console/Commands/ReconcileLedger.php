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

        return $accounts === 0 && $transactions === 0 ? self::SUCCESS : self::FAILURE;
    }
}
