<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('ndsgpay');
            $table->string('merchant_id')->nullable();
            $table->string('business_type')->default('wallet_topup');
            $table->string('business_id')->nullable();
            $table->string('country', 3)->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->uuid('correlation_id')->nullable()->unique();
            $table->string('local_state')->default('initiating')->index();
            $table->string('provider_status')->nullable();
            $table->string('active_key')->nullable()->unique();
            $table->string('error_code')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamp('check_lease_until')->nullable();
            $table->uuid('check_token')->nullable();
            $table->unsignedInteger('check_attempts')->default(0);
        });
        // Preserve financial history. Encrypt legacy phones without logging their contents.
        DB::table('payments')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('payments')->where('id', $row->id)->update([
                    'phone_encrypted' => Crypt::encryptString($row->msisdn), 'msisdn' => '[encrypted]',
                    'business_type' => $row->kind === 'deposit' ? 'wallet_topup' : 'wallet_withdrawal',
                    'business_id' => $row->reference, 'correlation_id' => (string) Str::uuid(),
                    'local_state' => match ($row->status) {
                        'COMPLETED' => 'completed', 'FAILED' => 'failed', 'UNKNOWN', 'CREATING' => 'initiation_unknown', default => 'pending'
                    },
                    'provider_status' => in_array($row->status, ['UNKNOWN', 'CREATING']) ? null : $row->status,
                    'initiated_at' => $row->created_at,
                    'confirmed_at' => null, // Old callbacks did not perform an authenticated GET.
                    'next_check_at' => $row->provider_id ? now() : null,
                ]);
            }
        });
        Schema::create('payment_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 64)->unique();
            $table->string('merchant_id');
            $table->string('kind');
            $table->string('provider_id');
            $table->string('provider_status');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('next_attempt_at')->index();
            $table->timestamp('processed_at')->nullable();
        });
        Schema::create('payment_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->string('event');
            $table->timestamp('created_at');
            $table->timestamp('delivered_at')->nullable();
            $table->unique(['payment_id', 'event']);
        });
        Schema::create('payment_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 64)->unique();
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->string('code');
            $table->timestamp('created_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
        });
        Schema::create('payment_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action');
            $table->string('case_reference', 100);
            $table->timestamp('created_at');
        });
        DB::table('payments')->whereIn('local_state', ['completed', 'failed'])->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('payment_anomalies')->insertOrIgnore([
                    'dedupe_key' => hash('sha256', $row->id.'|legacy_confirmation_unverified|'),
                    'payment_id' => $row->id, 'code' => 'legacy_confirmation_unverified', 'created_at' => now(),
                ]);
            }
        });
        Schema::create('payment_rate_limits', function (Blueprint $table) {
            $table->string('bucket', 64)->primary();
            $table->bigInteger('next_available_ms')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['payment_rate_limits', 'payment_audit', 'payment_anomalies', 'payment_outbox', 'payment_inbox'] as $name) {
            Schema::dropIfExists($name);
        }
        DB::table('payments')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('payments')->where('id', $row->id)->update(['msisdn' => Crypt::decryptString($row->phone_encrypted)]);
            }
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['correlation_id']);
            $table->dropUnique(['active_key']);
            $table->dropIndex(['local_state']);
            $table->dropIndex(['next_check_at']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn([
            'provider', 'merchant_id', 'business_type', 'business_id', 'country', 'phone_encrypted', 'fingerprint', 'correlation_id',
            'local_state', 'provider_status', 'active_key', 'error_code', 'initiated_at', 'confirmed_at', 'last_checked_at',
            'next_check_at', 'check_lease_until', 'check_token', 'check_attempts',
        ]));
    }
};
