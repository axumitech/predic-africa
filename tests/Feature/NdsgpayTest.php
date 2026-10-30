<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Payments\GatewayFailure;
use App\Modules\Payments\Inbox;
use App\Modules\Payments\Jobs\ProcessPayment;
use App\Modules\Payments\Ndsgpay;
use App\Modules\Payments\Operations;
use App\Modules\Payments\Outbox;
use App\Modules\Payments\Payments;
use App\Modules\Payments\ProviderBudget;
use App\Modules\Payments\Recovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NdsgpayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false, 'payments.enabled' => true, 'payments.secret_key' => 'test-secret',
            'payments.webhook_secret' => 'hook-secret', 'payments.merchant_id' => 'merchant-test',
            'payments.capabilities' => [['country' => 'GAB', 'correspondent' => 'AIRTEL_GAB', 'currency' => 'XAF', 'calling_code' => '241', 'national_lengths' => [8]]]]);
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function data(array $changes = []): array
    {
        return [...['kind' => 'deposit', 'amount' => 1000, 'reference' => (string) Str::uuid(), 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '+241 77 000 000'], ...$changes];
    }

    private function fakeProvider(string $status = 'COMPLETED', array $details = []): void
    {
        Http::fake([
            '*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'deposit-1', 'status' => 'INITIATED']),
            '*/deposits/deposit-1' => Http::response(['success' => true, 'deposit' => [...[
                'depositId' => 'deposit-1', 'amount' => 1000, 'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB',
                'msisdn' => '24177000000', 'status' => $status], ...$details]]),
        ]);
    }

    private function create(): object
    {
        return app(Payments::class)->create(User::factory()->create()->id, $this->data());
    }

    private function hook(string $id = 'deposit-1', string $status = 'COMPLETED', int $offset = 0, string $merchant = 'merchant-test', ?string $raw = null, ?string $signature = null)
    {
        $raw ??= json_encode(['event' => 'deposit.updated', 'data' => ['depositId' => $id, 'status' => $status, 'amount' => 999999, 'msisdn' => 'untrusted']]);
        $t = time() + $offset;
        $signature ??= 't='.$t.',sha256='.hash_hmac('sha256', $t.'.'.$raw, 'hook-secret');

        return $this->call('POST', '/webhooks/ndsgpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_NDSGPAY_SIGNATURE' => $signature, 'HTTP_X_NDSGPAY_MERCHANT' => $merchant], $raw);
    }

    private function processInbox(): void
    {
        foreach (DB::table('payment_inbox')->pluck('id') as $id) {
            app(Inbox::class)->process($id);
        }
    }

    public function test_creation_is_not_payment_and_browser_cannot_override_owner_or_currency(): void
    {
        $this->fakeProvider();
        $user = User::factory()->create();
        $data = $this->data(['currency' => 'CDF', 'user_id' => 9999, 'country' => 'COD']);
        $this->actingAs($user)->post('/wallet/ndsgpay', $data)->assertSessionHasNoErrors();
        $this->post('/wallet/ndsgpay', $data)->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://ndsgpay-backend.vercel.app/api/pay/deposits'
            && $r->hasHeader('Authorization', 'Bearer test-secret') && $r['amount'] === '1000'
            && $r['currency'] === 'XAF' && $r['msisdn'] === '24177000000' && ! isset($r['depositId']));
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'currency' => 'XAF', 'country' => 'GAB', 'local_state' => 'pending', 'msisdn' => '[encrypted]']);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'balance' => 0]);
        $payment = DB::table('payments')->first();
        $this->assertSame('24177000000', Crypt::decryptString($payment->phone_encrypted));
        $this->post('/wallet/ndsgpay', [...$data, 'amount' => 2000])->assertSessionHasErrors('reference');
        $this->post('/wallet/ndsgpay', $this->data())->assertSessionHasErrors('reference');
        $this->actingAs(User::factory()->create())->post('/wallet/ndsgpay', $data)->assertSessionHasErrors('reference');
    }

    public function test_webhook_is_durable_and_get_confirms_credit_and_outbox_once(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        $this->hook()->assertOk();
        $this->hook()->assertOk();
        $this->assertDatabaseCount('payment_inbox', 1);
        Http::assertSentCount(1); // Receipt does not call provider or dispatch a fragile after-commit job.
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->processInbox();
        $this->processInbox();
        app(Payments::class)->refresh(DB::table('payments')->find($payment->id));
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
        $this->assertDatabaseCount('payment_movements', 1);
        $this->assertDatabaseCount('payment_outbox', 1);
        app(Outbox::class)->deliver(DB::table('payment_outbox')->value('id'));
        app(Outbox::class)->deliver(DB::table('payment_outbox')->value('id'));
        $this->assertDatabaseCount('platform_notifications', 1);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseHas('payments', ['local_state' => 'completed']);
    }

    public function test_early_unknown_webhook_survives_until_association_is_saved(): void
    {
        $this->fakeProvider();
        $this->hook()->assertOk();
        $this->processInbox();
        $this->assertDatabaseHas('payment_inbox', ['processed_at' => null]);
        $this->create();
        DB::table('payment_inbox')->update(['next_attempt_at' => now()]);
        $this->processInbox();
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
    }

    public function test_disordered_and_contradictory_final_events_never_reverse_credit(): void
    {
        $this->fakeProvider();
        $this->create();
        $this->hook()->assertOk();
        $this->processInbox();
        $this->hook(status: 'FAILED')->assertOk();
        $this->processInbox();
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
        $this->assertDatabaseCount('payment_movements', 1);
        $this->assertDatabaseHas('payment_anomalies', ['code' => 'final_contradiction']);
    }

    #[DataProvider('badCreationResponses')]
    public function test_bad_creation_response_is_unknown_and_never_retried(mixed $body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        $user = User::factory()->create();
        $data = $this->data();
        $payment = app(Payments::class)->create($user->id, $data);
        $this->assertSame('initiation_unknown', $payment->local_state);
        app(Payments::class)->create($user->id, $data);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
    }

    public static function badCreationResponses(): array
    {
        return [
            'false success' => [['success' => false], 200], 'invalid json' => ['{', 200],
            'missing id' => [['success' => true, 'status' => 'INITIATED'], 200],
            'unsafe id' => [['success' => true, 'depositId' => '../escape', 'status' => 'INITIATED'], 200],
            'unknown status' => [['success' => true, 'depositId' => 'valid-id', 'status' => 'PAID'], 200],
            '400' => [['success' => false], 400], '401' => [[], 401], '403' => [[], 403],
            '429' => [[], 429], '500' => [[], 500], '503' => [[], 503], 'redirect' => [[], 302],
        ];
    }

    public function test_network_interruption_is_not_retried_and_new_uuid_is_blocked(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('sensitive network details');
        });
        $user = User::factory()->create();
        $data = $this->data();
        app(Payments::class)->create($user->id, $data);
        app(Payments::class)->create($user->id, $data);
        $this->assertSame(1, $calls);
        $this->actingAs($user)->post('/wallet/ndsgpay', $this->data())->assertSessionHasErrors('reference');
        $this->assertDatabaseHas('payments', ['local_state' => 'initiation_unknown', 'error_code' => 'network']);
    }

    #[DataProvider('badSignatureInputs')]
    public function test_invalid_signatures_are_rejected(string $header, int $offset, string $merchant): void
    {
        $this->hook(offset: $offset, merchant: $merchant, signature: $header === 'auto' ? null : $header)->assertStatus(400);
        $this->assertDatabaseCount('payment_inbox', 0);
        Http::assertNothingSent();
    }

    public static function badSignatureInputs(): array
    {
        return [
            ['', 0, 'merchant-test'], ['t=1,sha256=abcd', 0, 'merchant-test'],
            ['t=1,sha256='.str_repeat('0', 64).',extra=1', 0, 'merchant-test'],
            ['auto', -301, 'merchant-test'], ['auto', 301, 'merchant-test'], ['auto', 0, 'other-merchant'],
            ['t=99999999999999999999999999999,sha256='.str_repeat('0', 64), 0, 'merchant-test'],
        ];
    }

    public function test_signed_malformed_and_oversized_payloads_are_rejected(): void
    {
        $this->hook(raw: '{')->assertStatus(400);
        $this->hook(raw: str_repeat('x', 16385))->assertStatus(413);
        $this->hook(status: 'SUBMITTED')->assertStatus(400);
        $this->hook(raw: '{"event":"arbitrary","data":{}}')->assertStatus(400);
        $raw = '{"event":"deposit.updated","data":{"depositId":"deposit-1","status":"COMPLETED"}}';
        $t = time();
        $signature = 't='.$t.',sha256='.hash_hmac('sha256', $t.'.'.$raw.' ', 'hook-secret');
        $this->hook(raw: $raw, signature: $signature)->assertStatus(400);
        $this->assertDatabaseCount('payment_inbox', 0);
    }

    public function test_deposit_without_merchant_id_requires_signed_hook_and_authenticated_get_before_one_credit(): void
    {
        config(['payments.merchant_id' => '']);
        $this->fakeProvider();
        $payment = $this->create();
        $this->assertNull($payment->merchant_id);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->hook(merchant: '', signature: 'invalid')->assertStatus(400);
        $this->hook(merchant: '', offset: -301)->assertStatus(400);
        $this->assertDatabaseCount('payment_inbox', 0);
        $this->hook(merchant: '')->assertOk();
        $this->hook(merchant: 'untrusted-header')->assertOk();
        $this->assertDatabaseCount('payment_inbox', 1);
        $this->assertDatabaseHas('payment_inbox', ['merchant_id' => '']);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->processInbox();
        $this->processInbox();
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_ends_with($request->url(), '/deposits/deposit-1')
            && $request->hasHeader('Authorization', 'Bearer test-secret'));
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
        $this->assertDatabaseCount('payment_movements', 1);
    }

    #[DataProvider('badDetails')]
    public function test_without_merchant_id_a_signed_webhook_cannot_override_mismatched_server_details(array $details): void
    {
        config(['payments.merchant_id' => null]);
        $this->fakeProvider(details: $details);
        $this->create();
        $this->hook(merchant: '')->assertOk();
        $this->processInbox();
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->assertDatabaseCount('payment_movements', 0);
        $this->assertDatabaseHas('payment_inbox', ['processed_at' => null]);
    }

    public function test_without_merchant_id_a_signed_unknown_transaction_never_creates_or_credits_a_payment(): void
    {
        config(['payments.merchant_id' => null]);
        $this->hook(id: 'unknown', merchant: '')->assertOk();
        $this->processInbox();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_movements', 0);
        Http::assertNothingSent();
    }

    public function test_without_merchant_id_failed_server_authentication_never_credits_a_signed_event(): void
    {
        config(['payments.merchant_id' => null]);
        Http::fake([
            '*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'deposit-1', 'status' => 'INITIATED']),
            '*/deposits/deposit-1' => Http::response([], 403),
        ]);
        $this->create();
        $this->hook(merchant: '')->assertOk();
        $this->processInbox();
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->assertDatabaseCount('payment_movements', 0);
        $this->assertDatabaseHas('payment_inbox', ['processed_at' => null]);
    }

    public function test_optional_merchant_id_does_not_make_credentials_or_activation_optional(): void
    {
        config(['payments.merchant_id' => null]);
        $this->assertTrue(app(\App\Modules\Payments\Ndsgpay::class)->ready());
        foreach (['payments.secret_key', 'payments.webhook_secret', 'payments.enabled'] as $key) {
            $previous = config($key);
            config([$key => null]);
            $this->assertFalse(app(\App\Modules\Payments\Ndsgpay::class)->ready());
            if ($key === 'payments.webhook_secret') $this->hook(merchant: '')->assertStatus(400);
            config([$key => $previous]);
        }
    }

    #[DataProvider('badDetails')]
    public function test_mismatch_or_unknown_get_status_preserves_balance(array $details): void
    {
        $this->fakeProvider(details: $details);
        $payment = $this->create();
        app(Payments::class)->refresh($payment);
        $this->assertDatabaseHas('payments', ['local_state' => 'pending']);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->assertDatabaseCount('payment_movements', 0);
        $this->assertDatabaseCount('payment_anomalies', 1);
    }

    public static function badDetails(): array
    {
        return [[['amount' => 9999]], [['amount' => 1000.5]], [['currency' => 'CDF']], [['depositId' => 'another']], [['status' => 'PAID']]];
    }

    public function test_get_404_is_anomaly_and_preserves_previous_state(): void
    {
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'deposit-1', 'status' => 'INITIATED']), '*/deposits/deposit-1' => Http::response([], 404)]);
        $payment = $this->create();
        $this->assertFalse(app(Payments::class)->refresh($payment));
        $this->assertDatabaseHas('payments', ['local_state' => 'pending', 'error_code' => 'not_found']);
        $this->assertDatabaseHas('payment_anomalies', ['code' => 'not_found']);
    }

    public function test_late_confirmation_after_thirty_days_is_credited(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        DB::table('payments')->where('id', $payment->id)->update(['initiated_at' => now()->subDays(30)]);
        app(Payments::class)->refresh($payment);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
    }

    public function test_get_unavailable_keeps_webhook_unprocessed_without_credit(): void
    {
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'deposit-1', 'status' => 'INITIATED']), '*/deposits/deposit-1' => Http::response([], 500)]);
        $this->create();
        $this->hook()->assertOk();
        $this->processInbox();
        $this->assertDatabaseHas('payments', ['local_state' => 'awaiting_confirmation']);
        $this->assertDatabaseHas('payment_inbox', ['processed_at' => null]);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
    }

    public function test_recovery_rediscovers_inbox_outbox_and_crashed_creation_without_post(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        $this->hook()->assertOk();
        app(Recovery::class)->dispatch();
        Queue::assertPushed(ProcessPayment::class, fn ($job) => $job->work === 'inbox');
        $this->processInbox();
        app(Recovery::class)->dispatch();
        Queue::assertPushed(ProcessPayment::class, fn ($job) => $job->work === 'outbox');
        DB::table('payments')->where('id', $payment->id)->update(['local_state' => 'initiating', 'provider_id' => null, 'initiated_at' => now()->subMinutes(3)]);
        app(Recovery::class)->dispatch();
        $this->assertDatabaseHas('payments', ['local_state' => 'initiation_unknown']);
        Http::assertSentCount(2); // One creation, one verified GET, recovery never sends a POST.
    }

    public function test_provider_budget_is_sql_shared_and_retry_after_is_persisted(): void
    {
        app(ProviderBudget::class)->acquire('GET');
        try {
            app(ProviderBudget::class)->acquire('GET');
            $this->fail('Budget must be shared.');
        } catch (GatewayFailure $e) {
            $this->assertSame('local_rate_limit', $e->reason);
        }
        DB::table('payment_rate_limits')->delete();
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '120'])]);
        try {
            app(Ndsgpay::class)->request('GET', '/deposits/deposit-1');
            $this->fail('429 must throw.');
        } catch (GatewayFailure $e) {
            $this->assertSame(120, $e->retryAfter);
        }
        $this->assertGreaterThan((int) floor(microtime(true) * 1000) + 119000, DB::table('payment_rate_limits')->value('next_available_ms'));
    }

    public function test_payouts_and_unconfirmed_currencies_are_closed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/wallet/ndsgpay', $this->data(['kind' => 'withdraw']))->assertForbidden();
        config(['payments.capabilities' => [['country' => 'COD', 'correspondent' => 'INVENTED', 'currency' => 'USD', 'calling_code' => '243', 'national_lengths' => [9]]]]);
        $this->post('/wallet/ndsgpay', $this->data(['correspondent' => 'INVENTED']))->assertSessionHasErrors('correspondent');
        Http::assertNothingSent();
    }

    public function test_invalid_amount_phone_configuration_and_access_are_rejected(): void
    {
        $user = User::factory()->create();
        foreach ([0, -100, 1000001, '100.50'] as $amount) {
            $this->actingAs($user)->post('/wallet/ndsgpay', $this->data(['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $this->post('/wallet/ndsgpay', $this->data(['msisdn' => '243990000000']))->assertSessionHasErrors('msisdn');
        $this->post('/wallet/ndsgpay', $this->data(['msisdn' => '241abc00000000']))->assertSessionHasErrors('msisdn');
        config(['payments.enabled' => false]);
        $this->postJson('/wallet/ndsgpay', $this->data())->assertStatus(503);
        Http::assertNothingSent();
        config(['payments.enabled' => true]);
        $this->fakeProvider();
        $payment = $this->create();
        $this->actingAs(User::factory()->create())->postJson('/wallet/ndsgpay/'.$payment->id.'/refresh')->assertNotFound();
        $response = $this->get('/wallet');
        $response->assertDontSee('test-secret')->assertDontSee('hook-secret')->assertDontSee('24177000000');
    }

    public function test_operator_attachment_is_authorized_verified_and_audited(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        DB::table('payments')->where('id', $payment->id)->update(['provider_id' => null, 'local_state' => 'initiation_unknown']);
        $admin = User::factory()->create(['role' => 'admin']);
        app(Operations::class)->attach($payment->reference, 'deposit-1', $admin->id, 'SUPPORT-123');
        $this->assertDatabaseHas('payment_audit', ['payment_id' => $payment->id, 'actor_id' => $admin->id, 'action' => 'attach_provider_id']);
        $this->assertDatabaseHas('payments', ['local_state' => 'awaiting_confirmation']);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
    }

    public function test_url_paths_redirects_and_description_contract(): void
    {
        $this->assertSame('Ete Paiement client 12', Ndsgpay::description('Été : Paiement client 12 €'));
        config(['payments.base_url' => 'http://untrusted.test/api']);
        try {
            app(Ndsgpay::class)->request('POST', '/pay/deposits');
            $this->fail('HTTP must be rejected.');
        } catch (GatewayFailure $e) {
            $this->assertSame('configuration', $e->reason);
        }
        Http::assertNothingSent();
    }

    public function test_crashed_lease_cannot_apply_a_stale_get_result(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        Http::fake(['*/deposits/deposit-1' => function () use ($payment) {
            DB::table('payments')->where('id', $payment->id)->update(['check_token' => (string) Str::uuid()]);

            return Http::response(['success' => true, 'deposit' => ['depositId' => 'deposit-1', 'amount' => 1000, 'currency' => 'XAF', 'status' => 'COMPLETED']]);
        }]);
        app(Payments::class)->refresh($payment);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->assertDatabaseCount('payment_movements', 0);
    }

    public function test_inbox_persistence_failure_is_not_acknowledged(): void
    {
        DB::statement('DROP TABLE payment_inbox');
        $this->hook()->assertStatus(500);
    }

    public function test_health_and_audit_do_not_contact_provider(): void
    {
        $this->artisan('payments:audit')->assertSuccessful();
        config(['payments.enabled' => false]);
        $this->artisan('payments:health')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_sensitive_error_details_are_never_logged(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['error' => 'test-secret hook-secret 24177000000'], 401)]);
        $payment = $this->create();
        Log::shouldHaveReceived('warning')->once()->with('payment_anomaly', ['payment_id' => $payment->id, 'code' => 'authentication']);
        $this->assertSame('authentication', $payment->error_code);
    }

    public function test_operator_late_success_is_verified_audited_and_credited_once(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        DB::table('payments')->where('id', $payment->id)->update(['local_state' => 'failed', 'status' => 'FAILED', 'confirmed_at' => now(), 'active_key' => null]);
        $admin = User::factory()->create(['role' => 'admin']);
        app(Payments::class)->acceptLateDeposit($payment->reference, $admin->id, 'SUPPORT-LATE');
        $this->assertDatabaseHas('payment_wallets', ['balance' => 1000]);
        $this->assertDatabaseHas('payment_audit', ['action' => 'accept_late_deposit', 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('payment_outbox', ['event' => 'LATE_COMPLETED']);
        $this->artisan('payments:accept-late-deposit', ['reference' => $payment->reference, '--actor' => $admin->id, '--case' => 'SUPPORT-LATE'])->assertFailed();
        $this->assertDatabaseCount('payment_movements', 1);
    }

    public function test_non_admin_cannot_attach_or_resolve_an_operation(): void
    {
        $this->fakeProvider();
        $payment = $this->create();
        $user = User::factory()->create();
        $this->artisan('payments:attach', ['reference' => $payment->reference, 'providerId' => 'deposit-1', '--actor' => $user->id, '--case' => 'SUPPORT-1'])->assertFailed();
        $this->artisan('payments:accept-late-deposit', ['reference' => $payment->reference, '--actor' => $user->id, '--case' => 'SUPPORT-1'])->assertFailed();
        $this->assertDatabaseCount('payment_audit', 0);
        Http::assertSentCount(1);
    }

    public function test_completed_creation_response_does_not_credit_without_get(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'depositId' => 'already-completed', 'status' => 'COMPLETED'])]);
        $payment = $this->create();
        $this->assertSame('pending', $payment->local_state);
        $this->assertDatabaseHas('payment_wallets', ['balance' => 0]);
        $this->assertDatabaseCount('payment_movements', 0);
    }

    #[DataProvider('readErrors')]
    public function test_read_errors_do_not_regress_state_and_are_scheduled(int $status, string $reason): void
    {
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'deposit-1', 'status' => 'SUBMITTED']), '*/deposits/deposit-1' => Http::response([], $status)]);
        $payment = $this->create();
        $this->assertFalse(app(Payments::class)->refresh($payment));
        $row = DB::table('payments')->find($payment->id);
        $this->assertSame('pending', $row->local_state);
        $this->assertSame('SUBMITTED', $row->provider_status);
        $this->assertSame($reason, $row->error_code);
        $this->assertNotNull($row->next_check_at);
        Http::assertSentCount(2);
        $this->assertFalse(app(Payments::class)->refresh($row));
        Http::assertSentCount(2);
    }

    public static function readErrors(): array
    {
        return [[400, 'rejected'], [401, 'authentication'], [403, 'activation_or_permission'], [429, 'rate_limited'], [500, 'unavailable'], [503, 'unavailable']];
    }
}
