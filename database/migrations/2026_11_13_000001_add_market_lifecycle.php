<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('event_ends_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->json('resolution_rule')->nullable();
            $table->timestamp('processing_due_at')->nullable();
            $table->unsignedInteger('processing_failures')->default(0);
            $table->string('processing_error')->nullable();
            $table->index(['status', 'closes_at', 'id'], 'market_close_due');
            $table->index(['status', 'processing_due_at', 'id'], 'market_processing_due');
            $table->index(['status', 'event_ends_at', 'id'], 'market_event_end_due');
        });
        Schema::create('market_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->timestamp('created_at');
            $table->index(['market_id', 'id']);
        });
        Schema::create('position_settlements', function (Blueprint $table) {
            $table->foreignId('position_id')->primary()->constrained('positions');
            $table->foreignId('ledger_transaction_id')->nullable()->unique()->constrained();
            $table->string('status');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->timestamp('created_at');
        });
        Schema::table('positions', fn (Blueprint $table) => $table->index(['market_id', 'status', 'id'], 'position_settlement_batch'));
    }

    public function down(): void
    {
        Schema::dropIfExists('position_settlements');
        Schema::dropIfExists('market_transitions');
        Schema::table('positions', fn (Blueprint $table) => $table->dropIndex('position_settlement_batch'));
        Schema::table('markets', function (Blueprint $table) {
            $table->dropIndex('market_close_due');
            $table->dropIndex('market_processing_due');
            $table->dropIndex('market_event_end_due');
            $table->dropColumn(['closed_at', 'event_ends_at', 'resolved_at', 'settled_at', 'resolution_rule',
                'processing_due_at', 'processing_failures', 'processing_error']);
        });
    }
};
