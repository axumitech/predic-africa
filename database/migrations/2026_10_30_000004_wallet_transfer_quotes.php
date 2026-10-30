<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transfer_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('source_currency', 3);
            $table->string('target_currency', 3);
            $table->unsignedBigInteger('source_amount');
            $table->unsignedBigInteger('target_amount');
            $table->string('rate', 100);
            $table->foreignId('snapshot_id')->constrained('fx_snapshots');
            $table->timestamp('as_of');
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->uuid('debit_reference')->unique();
            $table->uuid('credit_reference')->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Les devis de transferts exécutés doivent être conservés pour audit.');
    }
};
