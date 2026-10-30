<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('currency', 3)->default('XAF');
            $table->bigInteger('balance')->default(0);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('reference')->unique();
            $table->string('kind');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('correspondent');
            $table->string('msisdn');
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->default('CREATING');
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
        Schema::create('payment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->string('effect');
            $table->bigInteger('amount');
            $table->timestamp('created_at');
            $table->unique(['payment_id', 'effect']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_wallets');
    }
};
