<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Money\WalletTransfers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletTransfersTest extends TestCase
{
    use RefreshDatabase;

    private function setupWallets(): User
    {
        $user = User::factory()->create();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 5000, true));
        $this->actingAs($user)->post('/wallets', ['currency' => 'CDF'])->assertRedirect();
        $this->post('/wallets', ['currency' => 'KES'])->assertRedirect();
        DB::table('fx_snapshots')->insert(['provider' => config('fx.provider'), 'base_currency' => 'XAF',
            'rates' => json_encode(['CDF' => '4', 'KES' => '0.3']), 'as_of' => now(), 'fetched_at' => now()]);
        return $user;
    }

    private function quote(string $source = 'XAF', string $target = 'CDF', string $amount = '100'): string
    {
        return $this->postJson('/wallets/transfers/quote', ['source_currency' => $source, 'target_currency' => $target, 'amount' => $amount])
            ->assertOk()->json('id');
    }

    public function test_quote_confirmation_and_retry_preserve_both_ledgers(): void
    {
        $user = $this->setupWallets();
        $id = $this->quote();
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 5000]);
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'source_amount' => 100, 'target_amount' => 40000, 'confirmed_at' => null]);
        // Neither modified rates nor browser amounts change the confirmed quote.
        DB::table('fx_snapshots')->update(['rates' => json_encode(['CDF' => '8', 'KES' => '0.6'])]);
        $this->post('/wallets/transfers', ['quote_id' => $id, 'target_amount' => 999999])->assertRedirect();
        $this->travel(5)->minutes();
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertRedirect();
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 4900]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 40000]);
        $this->assertSame(2, DB::table('ledger_transactions')->whereIn('kind', ['Transfert sortant', 'Transfert entrant'])->count());
        $this->artisan('ledger:reconcile')->assertSuccessful();
        $this->artisan('payments:audit')->assertSuccessful();
        $this->get('/wallet')->assertOk();
    }

    public function test_cross_rate_rounds_once_and_converts_back_to_base(): void
    {
        $user = $this->setupWallets();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 10000, true, currency: 'CDF'));
        $id = $this->quote('CDF', 'KES', '1.33');
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'target_amount' => 9]);
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertRedirect();
        $id = $this->quote('CDF', 'XAF', '8.01');
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'target_amount' => 2]);
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertRedirect();
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_expired_and_unavailable_rates_are_rejected(): void
    {
        $this->setupWallets();
        $id = $this->quote();
        $this->travel(5)->minutes();
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertSessionHasErrors('amount');
        DB::table('fx_snapshots')->update(['as_of' => now()->subDays(10)]);
        $this->postJson('/wallets/transfers/quote', ['source_currency' => 'XAF', 'target_currency' => 'CDF', 'amount' => '100'])->assertUnprocessable();
        $this->assertSame(0, DB::table('ledger_transactions')->where('kind', 'Transfert sortant')->count());
    }

    public function test_insufficient_balance_at_confirmation_does_not_credit_target(): void
    {
        $user = $this->setupWallets();
        $id = $this->quote();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Mise', 5000, false, currency: 'XAF'));
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'confirmed_at' => null]);
    }

    public function test_credit_overflow_rolls_back_debit_and_all_entries(): void
    {
        $user = $this->setupWallets();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', PHP_INT_MAX, true, currency: 'CDF'));
        $id = $this->quote();
        $this->post('/wallets/transfers', ['quote_id' => $id])->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 5000]);
        $this->assertSame(0, DB::table('ledger_transactions')->where('kind', 'Transfert sortant')->count());
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'confirmed_at' => null]);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_wallet_ownership_input_validation_and_quote_ownership(): void
    {
        $this->setupWallets();
        foreach ([['XAF', 'XAF', '1'], ['XAF', 'UGX', '1'], ['XAF', 'CDF', '0'], ['XAF', 'CDF', '-1'], ['XAF', 'CDF', '0.01'], ['XAF', 'CDF', '99999'], ['CDF', 'XAF', '0.01']] as [$from, $to, $amount]) {
            $this->postJson('/wallets/transfers/quote', ['source_currency' => $from, 'target_currency' => $to, 'amount' => $amount])->assertUnprocessable();
        }
        $id = $this->quote();
        $other = User::factory()->create();
        $this->actingAs($other)->post('/wallets/transfers', ['quote_id' => $id])->assertNotFound();
        $this->postJson('/wallets/transfers/quote', ['source_currency' => 'XAF', 'target_currency' => 'CDF', 'amount' => '1'])->assertUnprocessable();
        $this->assertDatabaseHas('wallet_transfer_quotes', ['id' => $id, 'confirmed_at' => null]);
    }
}
