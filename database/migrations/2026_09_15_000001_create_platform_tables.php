<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('trader');
            $table->boolean('suspended')->default(false);
        });
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained();
            $table->string('code')->unique();
            $table->bigInteger('balance')->default(0);
        });
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('kind');
            $table->string('fingerprint');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at');
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions');
            $table->foreignId('account_id')->constrained('accounts');
            $table->bigInteger('amount');
            $table->index(['account_id', 'id']);
        });
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('status')->default('pending');
            $table->timestamp('closes_at');
            $table->unsignedInteger('yes_odds')->default(200);
            $table->unsignedInteger('no_odds')->default(200);
            $table->string('result')->nullable();
            $table->text('resolution_source')->nullable();
            $table->timestamps();
            $table->index(['status', 'category', 'closes_at']);
        });
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('market_id')->constrained();
            $table->foreignId('transaction_id')->unique()->constrained('ledger_transactions');
            $table->string('outcome');
            $table->unsignedBigInteger('stake');
            $table->unsignedInteger('odds');
            $table->unsignedBigInteger('payout');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets');
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamp('created_at');
        });
        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['platform_notifications', 'support_messages', 'support_tickets', 'positions', 'markets', 'ledger_entries', 'ledger_transactions', 'accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'suspended']));
    }
};
