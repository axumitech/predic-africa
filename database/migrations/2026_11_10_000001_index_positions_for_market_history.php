<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // InnoDB already indexes the foreign key on market_id.
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::table('positions', fn (Blueprint $table) => $table->index(['market_id', 'created_at']));
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::table('positions', fn (Blueprint $table) => $table->dropIndex(['market_id', 'created_at']));
        }
    }
};
