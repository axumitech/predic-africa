<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_notifications', fn (Blueprint $table) => $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('platform_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('market_id'));
    }
};
