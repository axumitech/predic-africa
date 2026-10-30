<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Payments\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WalletPaymentOperatorsTest extends TestCase
{
    use RefreshDatabase;

    private function gabonAccount(): User
    {
        config(['payments.enabled' => true, 'payments.secret_key' => 'payment-secret', 'payments.webhook_secret' => 'hook-secret',
            'payments.merchant_id' => 'merchant-test', 'payments.capabilities' => [
                ['country' => 'GAB', 'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB', 'calling_code' => '241', 'national_lengths' => [8]],
                ['country' => 'CMR', 'currency' => 'XAF', 'correspondent' => 'ORANGE_CMR', 'calling_code' => '237', 'national_lengths' => [9]],
                ['country' => 'COD', 'currency' => 'CDF', 'correspondent' => 'AIRTEL_COD', 'calling_code' => '243', 'national_lengths' => [9]],
                ['country' => 'COD', 'currency' => 'CDF', 'correspondent' => 'ORANGE_COD', 'calling_code' => '243', 'national_lengths' => [9]],
            ]]);
        Http::preventStrayRequests();
        Queue::fake();
        $user = User::factory()->create(['country' => 'GAB']);
        DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 0]);
        DB::table('accounts')->insert(['user_id' => $user->id, 'code' => "user:$user->id", 'currency' => 'XAF', 'balance' => 0]);
        $this->actingAs($user)->post('/wallets', ['currency' => 'CDF'])->assertSessionHasNoErrors();
        return $user;
    }

    private function deposit(array $changes = []): array
    {
        return [...['kind' => 'deposit', 'amount' => '1234.56', 'reference' => (string) Str::uuid(),
            'correspondent' => 'AIRTEL_COD', 'msisdn' => '243810000000'], ...$changes];
    }

    public function test_operator_list_follows_wallet_currency_across_countries(): void
    {
        $this->gabonAccount();
        $this->get('/wallet')->assertInertia(fn (Assert $page) => $page
            ->where('auth.country', 'GAB')->where('ndsgpay.currency', 'CDF')
            ->where('ndsgpay.correspondents', ['AIRTEL_COD', 'ORANGE_COD'])
            ->where('ndsgpay.correspondent_labels.AIRTEL_COD', 'République démocratique du Congo — Airtel Money (+243)'));
        $this->post('/wallets/select', ['currency' => 'XAF'])->assertSessionHasNoErrors();
        $this->get('/wallet')->assertInertia(fn (Assert $page) => $page
            ->where('ndsgpay.correspondents', ['AIRTEL_GAB', 'ORANGE_CMR'])
            ->where('ndsgpay.correspondent_labels.AIRTEL_GAB', 'Gabon — Airtel Money (+241)')
            ->where('ndsgpay.correspondent_labels.ORANGE_CMR', 'Cameroun — Orange Money (+237)'));
    }

    public function test_gabon_user_can_deposit_cdf_via_rdc_and_confirmation_credits_original_wallet(): void
    {
        $user = $this->gabonAccount();
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'cross-border-cdf', 'status' => 'INITIATED']),
            '*/deposits/cross-border-cdf' => Http::response('{"success":true,"deposit":{"depositId":"cross-border-cdf","amount":1234.56,"currency":"CDF","status":"COMPLETED"}}', 200, ['Content-Type' => 'application/json'])]);
        // Browser country/currency must not override the selected operator's metadata.
        $body = $this->deposit(['country' => 'GAB', 'currency' => 'XAF']);
        $this->post('/wallet/ndsgpay', $body)->assertSessionHasNoErrors();
        $this->post('/wallet/ndsgpay', $body)->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['currency'] === 'CDF' && $request['amount'] === '1234.56' && $request['correspondent'] === 'AIRTEL_COD');
        $payment = DB::table('payments')->sole();
        $this->assertSame('COD', $payment->country);
        $this->post('/wallets/select', ['currency' => 'XAF'])->assertSessionHasNoErrors();
        app(Payments::class)->refresh($payment);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 123456]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 0]);
        $this->assertSame('GAB', $user->fresh()->country);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_gabon_user_can_use_cameroon_operator_with_xaf_wallet(): void
    {
        $this->gabonAccount();
        $this->post('/wallets/select', ['currency' => 'XAF'])->assertSessionHasNoErrors();
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'cmr-xaf', 'status' => 'INITIATED'])]);
        $this->post('/wallet/ndsgpay', $this->deposit(['amount' => '1000', 'correspondent' => 'ORANGE_CMR', 'msisdn' => '237690000000']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', ['country' => 'CMR', 'currency' => 'XAF', 'correspondent' => 'ORANGE_CMR']);
        Http::assertSentCount(1);
    }

    public function test_operator_currency_phone_and_activation_are_still_enforced(): void
    {
        $this->gabonAccount();
        $this->postJson('/wallet/ndsgpay', $this->deposit(['correspondent' => 'AIRTEL_GAB', 'msisdn' => '24177000000', 'amount' => '1000']))
            ->assertUnprocessable()->assertJsonValidationErrors('correspondent');
        $this->postJson('/wallet/ndsgpay', $this->deposit(['msisdn' => '24177000000']))
            ->assertUnprocessable()->assertJsonValidationErrors('msisdn');
        $this->postJson('/wallet/ndsgpay', $this->deposit(['correspondent' => 'VODACOM_MPESA_COD']))
            ->assertUnprocessable()->assertJsonValidationErrors('correspondent');
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }
}
