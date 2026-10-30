<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_records', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->unsignedBigInteger('source_id');
            $table->longText('payload');
            $table->timestamp('retired_at');
            $table->unique(['source', 'source_id']);
        });
        Schema::table('payment_movements', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('ledger_transaction_id')->nullable()->unique()->constrained('ledger_transactions');
            $table->unsignedBigInteger('payment_id')->nullable()->change();
        });
        DB::transaction(function () {
            $archive = function (string $source, $query): void {
                foreach ($query->orderBy('id')->get() as $record) {
                    DB::table('retired_records')->insert(['source' => $source, 'source_id' => $record->id,
                        'payload' => json_encode($record, JSON_THROW_ON_ERROR), 'retired_at' => now()]);
                }
            };
            foreach (['accounts', 'ledger_transactions', 'ledger_entries', 'positions'] as $source) {
                $archive($source, DB::table($source));
            }
            // Existing trading history predates real-money trading. Keep it outside active tables.
            DB::table('positions')->delete();
            DB::table('ledger_entries')->delete();
            DB::table('ledger_transactions')->delete();
            DB::table('accounts')->update(['balance' => 0]);
            DB::table('accounts')->where('code', 'simulation')->update(['code' => 'treasury']);
            DB::table('accounts')->insertOrIgnore(['code' => 'treasury', 'balance' => 0]);
            $bank = DB::table('accounts')->where('code', 'treasury')->firstOrFail();
            foreach (DB::table('payment_movements')->get() as $movement) {
                DB::table('payment_movements')->where('id', $movement->id)->update([
                    'user_id' => DB::table('payments')->where('id', $movement->payment_id)->value('user_id'),
                ]);
            }
            foreach (DB::table('payment_wallets')->get() as $wallet) {
                DB::table('accounts')->insertOrIgnore(['code' => 'user:'.$wallet->user_id, 'user_id' => $wallet->user_id, 'balance' => 0]);
                if ((int) $wallet->balance === 0) {
                    continue;
                }
                if ($wallet->currency !== 'XAF' || $wallet->balance < 0) {
                    throw new RuntimeException('Portefeuille réel incohérent : migration arrêtée.');
                }
                $account = DB::table('accounts')->where('user_id', $wallet->user_id)->firstOrFail();
                $reference = (string) Str::uuid();
                $id = DB::table('ledger_transactions')->insertGetId(['reference' => $reference, 'user_id' => $wallet->user_id,
                    'kind' => 'Ouverture NDSGPay', 'fingerprint' => hash('sha256', "$wallet->user_id|Ouverture NDSGPay|$wallet->balance|"),
                    'amount' => $wallet->balance, 'created_at' => now()]);
                DB::table('ledger_entries')->insert([
                    ['transaction_id' => $id, 'account_id' => $account->id, 'amount' => $wallet->balance],
                    ['transaction_id' => $id, 'account_id' => $bank->id, 'amount' => -$wallet->balance],
                ]);
                DB::table('accounts')->where('id', $account->id)->update(['balance' => $wallet->balance]);
                DB::table('accounts')->where('id', $bank->id)->decrement('balance', $wallet->balance);
            }
            $demoMarkets = DB::table('markets')->where(fn ($q) => $q->where('title', 'like', 'Démo :%')->orWhere('title', 'like', 'Demo :%'));
            $archive('markets', clone $demoMarkets);
            $demoMarkets->delete();
            // Previously published definitions must be reviewed before accepting monetary stakes.
            DB::table('markets')->where('status', 'open')->update(['status' => 'pending', 'updated_at' => now()]);
            $notifications = DB::table('platform_notifications')->where(fn ($q) => $q->where('message', 'like', '%crédits%')->orWhere('message', 'like', '%simul%')->orWhere('message', 'like', '%démonstration%'));
            $archive('platform_notifications', clone $notifications);
            $notifications->delete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Ne pas restaurer des crédits historiques sur des fonds réels. Restauration contrôlée depuis sauvegarde et retired_records requise.');
    }
};
