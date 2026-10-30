<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NdsgpayMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_confirmation_is_flagged_and_balances_are_preserved_on_upgrade(): void
    {
        $migration = require database_path('migrations/2026_10_29_000002_harden_ndsgpay.php');
        $migration->down();
        $user = User::factory()->create();
        DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 1000]);
        $id = DB::table('payments')->insertGetId([
            'user_id' => $user->id, 'reference' => (string) Str::uuid(), 'kind' => 'deposit', 'amount' => 1000,
            'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '24177000000',
            'provider_id' => 'legacy-provider', 'status' => 'COMPLETED', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payment_movements')->insert(['payment_id' => $id, 'effect' => 'credit', 'amount' => 1000, 'created_at' => now()]);
        $migration->up();
        $row = DB::table('payments')->find($id);
        $this->assertSame('24177000000', Crypt::decryptString($row->phone_encrypted));
        $this->assertSame('[encrypted]', $row->msisdn);
        $this->assertNull($row->confirmed_at);
        $this->assertSame('completed', $row->local_state);
        $this->assertDatabaseHas('payment_anomalies', ['payment_id' => $id, 'code' => 'legacy_confirmation_unverified']);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
        $this->assertDatabaseCount('payment_movements', 1);
        $this->assertDatabaseCount('payment_outbox', 0);
    }
}
