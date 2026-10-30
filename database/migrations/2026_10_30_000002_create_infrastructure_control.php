<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('revision')->default(0);
            $table->json('settings');
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('infrastructure_settings')->insert(['id' => 1, 'revision' => 0, 'settings' => '{}']);
        Schema::create('infrastructure_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at');
        });
        Schema::create('infrastructure_heartbeats', function (Blueprint $table) {
            $table->string('component')->primary();
            $table->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Conserver le journal des actions superadmin ; restauration contrôlée requise.');
    }
};
