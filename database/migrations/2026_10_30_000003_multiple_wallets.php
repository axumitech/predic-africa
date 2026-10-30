<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('active_wallet_currency', 3)->nullable());
        foreach (['payment_wallets', 'accounts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['user_id']);
                $table->unique(['user_id', 'currency']);
            });
        }
    }

    public function down(): void
    {
        // Refuse rollback if it would destroy additional funded or empty wallets.
        foreach (['payment_wallets', 'accounts'] as $name) {
            if (\Illuminate\Support\Facades\DB::table($name)->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new \RuntimeException('Plusieurs portefeuilles existent : retour arrière impossible sans rapprochement.');
            }
        }
        foreach (['payment_wallets', 'accounts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['user_id', 'currency']);
                $table->unique('user_id');
            });
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('active_wallet_currency'));
    }
};
