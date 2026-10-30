<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultipleWalletsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_switching_and_currency_isolation(): void
    {
        $user = User::factory()->create();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 5000, true));
        $this->actingAs($user)->post('/wallets', ['currency' => 'CDF'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active_wallet_currency' => 'CDF']);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 12345, true));
        $this->post('/wallets/select', ['currency' => 'XAF'])->assertRedirect();
        // Delayed gain or payment in CDF must still credit CDF after switching.
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Gain', 200, true, currency: 'CDF'));
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 5000]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 12545]);
        $this->get('/wallet')->assertOk();
        $this->artisan('ledger:reconcile')->assertSuccessful();
        $this->artisan('payments:audit')->assertSuccessful();
        $this->post('/wallets', ['currency' => 'CDF'])->assertSessionHasErrors('currency');
        $this->post('/wallets', ['currency' => 'BAD'])->assertSessionHasErrors('currency');
        $this->post('/wallets/select', ['currency' => 'KES'])->assertNotFound();
        $this->assertDatabaseCount('payment_wallets', 2);
    }

    public function test_creation_reuses_an_empty_historical_account_without_a_wallet(): void
    {
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId(['user_id' => $user->id, 'code' => "user:$user->id", 'currency' => 'XAF', 'balance' => 0]);
        $this->actingAs($user)->post('/wallets', ['currency' => 'XAF'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'balance' => 0]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 0]);
        $this->assertSame(1, DB::table('accounts')->where('user_id', $user->id)->count());
        $this->post('/wallets', ['currency' => 'XAF'])->assertSessionHasErrors('currency');
        $this->get('/wallet')->assertOk();
    }

    public function test_recovering_a_missing_wallet_preserves_funds_and_ledger_history(): void
    {
        $user = User::factory()->create();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 12345, true));
        $accountId = DB::table('accounts')->where('user_id', $user->id)->value('id');
        // Reproduce a historical account whose payment wallet is missing.
        DB::table('payment_wallets')->where('user_id', $user->id)->delete();
        $this->actingAs($user)->post('/wallets', ['currency' => 'XAF'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'balance' => 12345]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 12345]);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(1, DB::table('accounts')->where('user_id', $user->id)->count());
        $this->artisan('ledger:reconcile')->assertSuccessful();
        $this->artisan('payments:audit')->assertSuccessful();
    }

    public function test_inconsistent_historical_accounts_return_validation_errors_without_overwriting_funds(): void
    {
        $user = User::factory()->create();
        $accountId = DB::table('accounts')->insertGetId(['user_id' => $user->id, 'code' => "user:$user->id", 'currency' => 'XAF', 'balance' => 100]);
        $this->actingAs($user)->postJson('/wallets', ['currency' => 'XAF'])->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'balance' => 100]);
        $this->assertDatabaseCount('payment_wallets', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active_wallet_currency' => null]);
    }

    public function test_guests_cannot_create_or_switch_wallets(): void
    {
        $this->post('/wallets', ['currency' => 'CDF'])->assertRedirect('/login');
        $this->post('/wallets/select', ['currency' => 'CDF'])->assertRedirect('/login');
    }
}
