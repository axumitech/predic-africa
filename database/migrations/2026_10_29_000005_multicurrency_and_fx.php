<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Before this migration only XAF operations were possible. Never guess the
        // denomination of unexpected historical foreign funds or silently rescale them.
        if (DB::table('payment_wallets')->where('currency', '!=', 'XAF')->where('balance', '!=', 0)->exists()
            || DB::table('payments')->where('currency', '!=', 'XAF')->exists()) {
            throw new RuntimeException('Fonds hors XAF historiques : rapprochement préalable nécessaire.');
        }
        $foreignWallets = DB::table('payment_wallets')->where('currency', '!=', 'XAF')->get();
        foreach ($foreignWallets as $wallet) {
            if (DB::table('accounts')->where('user_id', $wallet->user_id)->where('balance', '!=', 0)->exists()
                || DB::table('ledger_transactions')->where('user_id', $wallet->user_id)->exists()) {
                throw new RuntimeException('Compte hors XAF historique incohérent.');
            }
        }
        foreach (['accounts', 'ledger_transactions', 'ledger_entries', 'payment_movements'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('currency', 3)->default('XAF')->index());
        }
        foreach ($foreignWallets as $wallet) {
            DB::table('accounts')->where('user_id', $wallet->user_id)->update(['currency' => $wallet->currency]);
        }
        Schema::create('fx_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('base_currency', 3);
            $table->json('rates');
            $table->timestamp('as_of');
            $table->timestamp('fetched_at');
            $table->unique(['provider', 'base_currency', 'as_of']);
        });
        Schema::create('fx_sync_state', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
        });
        DB::table('fx_sync_state')->insert(['id' => 1]);
        Schema::create('fx_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('market_id')->constrained();
            $table->foreignId('snapshot_id')->nullable()->constrained('fx_snapshots');
            $table->uuid('reference')->index();
            $table->string('currency', 3);
            $table->string('outcome');
            $table->unsignedInteger('odds');
            $table->unsignedBigInteger('wallet_stake');
            $table->unsignedBigInteger('wallet_payout');
            $table->unsignedBigInteger('base_stake');
            $table->unsignedBigInteger('base_payout');
            $table->string('rate', 80);
            $table->timestamp('as_of');
            $table->timestamp('expires_at')->index();
            $table->string('fingerprint', 64);
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('ledger_transactions');
            $table->timestamp('created_at');
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->string('wallet_currency', 3)->default('XAF');
            $table->unsignedBigInteger('wallet_stake')->nullable();
            $table->unsignedBigInteger('wallet_payout')->nullable();
            $table->foreignUuid('fx_quote_id')->nullable()->unique()->constrained('fx_quotes');
        });
        DB::table('positions')->update(['wallet_stake' => DB::raw('stake'), 'wallet_payout' => DB::raw('payout')]);
    }

    public function down(): void
    {
        throw new RuntimeException('Ne pas retirer la devise d’un journal monétaire. Restauration contrôlée requise.');
    }
};
