<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', fn (Blueprint $table) => $table->string('pipeline_source')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('markets', fn (Blueprint $table) => $table->dropColumn('pipeline_source'));
    }
};
