<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'positions' => [['market_id', 'status', 'user_id'], ['user_id', 'id']],
        'ledger_transactions' => [['user_id', 'id']],
        'markets' => [['creator_id', 'id']],
        'support_messages' => [['ticket_id', 'id']],
        'platform_notifications' => [['user_id', 'id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $schema) use ($indexes) {
                foreach ($indexes as $columns) {
                    $schema->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $schema) use ($indexes) {
                foreach ($indexes as $columns) {
                    $schema->dropIndex($columns);
                }
            });
        }
    }
};
