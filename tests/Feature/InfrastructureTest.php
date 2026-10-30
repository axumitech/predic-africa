<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Infrastructure\Settings;
use App\Modules\Payments\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config(['payments.enabled' => true, 'payments.secret_key' => 'private-api-secret', 'payments.webhook_secret' => 'private-hook-secret',
            'payments.merchant_id' => null, 'queue.default' => 'database', 'app.debug' => false,
            'payments.capabilities' => [['country' => 'GAB', 'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB', 'calling_code' => '241', 'national_lengths' => [8]]]]);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function changes(array $changes = []): array
    {
        return [...app(Settings::class)->current(), 'current_password' => 'password', 'reason' => 'Maintenance opérateur planifiée', ...$changes];
    }

    private function store(array $settings): void
    {
        DB::table('infrastructure_settings')->where('id', 1)->update(['settings' => json_encode($settings)]);
    }

    private function deposit(): array
    {
        return ['kind' => 'deposit', 'amount' => '1000', 'reference' => (string) Str::uuid(), 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '24177000000'];
    }

    public function test_only_superadmin_can_read_or_change_infrastructure(): void
    {
        $this->get('/superadmin')->assertRedirect('/login');
        foreach (['trader', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/superadmin')->assertForbidden();
            $this->postJson('/superadmin/settings', $this->changes())->assertForbidden();
            $this->postJson('/superadmin/operations/reconcile', ['current_password' => 'password', 'reason' => 'Test refus'])->assertForbidden();
        }
        $this->assertDatabaseCount('infrastructure_audit', 0);
        $this->assertSame(0, app(Settings::class)->current()['revision']);
    }

    public function test_superadmin_can_login_and_read_controls_without_secrets_or_payment_phone(): void
    {
        $super = $this->superadmin();
        $this->post('/login', ['email' => $super->email, 'password' => 'password'])->assertRedirect('/superadmin');
        $this->get('/superadmin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Infrastructure')
            ->where('auth.role', 'superadmin')->has('catalogue', 24)->where('health.api_key_present', true)
            ->missing('health.secret_key')->missing('auth.phone'))
            ->assertDontSee('private-api-secret')->assertDontSee('private-hook-secret');
        $this->get('/admin')->assertOk();
    }

    public function test_configuration_changes_are_atomic_audited_and_revision_guarded(): void
    {
        $super = $this->superadmin();
        $data = $this->changes(['deposits_enabled' => false, 'operator_codes' => ['AIRTEL_COD', 'ORANGE_COD']]);
        $this->actingAs($super)->post('/superadmin/settings', $data)->assertSessionHasNoErrors();
        $settings = app(Settings::class)->current();
        $this->assertFalse($settings['deposits_enabled']);
        $this->assertSame(1, $settings['revision']);
        $this->assertSame(['AIRTEL_COD', 'ORANGE_COD'], $settings['operator_codes']);
        $audit = DB::table('infrastructure_audit')->sole();
        $this->assertSame($super->id, $audit->actor_id);
        $this->assertStringNotContainsString('current_password', $audit->after);
        $this->assertStringNotContainsString('private-api-secret', $audit->after);
        $this->postJson('/superadmin/settings', $data)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertDatabaseCount('infrastructure_audit', 1);
    }

    public function test_wrong_password_unrecognized_operator_and_invalid_limits_cannot_change_settings(): void
    {
        $this->actingAs($this->superadmin());
        foreach ([['current_password' => 'wrong'], ['operator_codes' => ['ARBITRARY_PROVIDER']], ['min_amount' => '1000', 'max_amount' => '10'], ['max_amount' => '1e9'], ['min_amount' => '-1']] as $bad) {
            $this->postJson('/superadmin/settings', $this->changes($bad))->assertUnprocessable();
        }
        $this->assertDatabaseCount('infrastructure_audit', 0);
        $this->assertSame(0, app(Settings::class)->current()['revision']);
        Http::assertNothingSent();
    }

    public function test_operator_updates_are_immediate_and_pause_new_deposits_without_breaking_confirmation(): void
    {
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'infra-1', 'status' => 'INITIATED']),
            '*/deposits/infra-1' => Http::response(['success' => true, 'deposit' => ['depositId' => 'infra-1', 'amount' => '1000', 'currency' => 'XAF', 'status' => 'COMPLETED']])]);
        $trader = User::factory()->create(['country' => 'GAB']);
        $payment = app(Payments::class)->create($trader->id, $this->deposit());
        $this->store(['deposits_enabled' => false, 'operator_codes' => []]);
        $this->actingAs($trader)->postJson('/wallet/ndsgpay', $this->deposit())->assertStatus(503);
        $this->get('/wallet')->assertInertia(fn (Assert $page) => $page->where('ndsgpay.enabled', false)->has('ndsgpay.correspondents', 0));
        $this->assertTrue(app(Payments::class)->refresh($payment));
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $trader->id, 'balance' => 1000]);
        $this->assertDatabaseCount('payment_movements', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_general_maintenance_keeps_login_superadmin_and_webhooks_accessible(): void
    {
        $this->store(['app_maintenance' => true]);
        $this->get('/register')->assertStatus(503);
        $this->get('/login')->assertOk();
        $this->postJson('/webhooks/ndsgpay', [])->assertStatus(400);
        $trader = User::factory()->create();
        $this->actingAs($trader)->getJson('/wallet')->assertStatus(503);
        $this->actingAs($this->superadmin())->get('/superadmin')->assertOk();
        $this->post('/superadmin/settings', $this->changes(['app_maintenance' => false]))->assertSessionHasNoErrors();
        $this->actingAs($trader)->get('/wallet')->assertOk();
    }

    public function test_disabled_operator_and_native_limits_block_creation_without_network_calls(): void
    {
        $trader = User::factory()->create(['country' => 'GAB']);
        $this->actingAs($trader);
        $this->store(['operator_codes' => ['MOOV_GAB']]);
        $this->postJson('/wallet/ndsgpay', $this->deposit())->assertUnprocessable()->assertJsonValidationErrors('correspondent');
        $this->store(['min_amount' => '2000']);
        $this->postJson('/wallet/ndsgpay', $this->deposit())->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_admin_cannot_suspend_a_superadmin_or_mass_assign_privilege(): void
    {
        $super = $this->superadmin();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/admin/users/'.$super->id, ['suspended' => true])->assertForbidden();
        $this->assertFalse($super->fresh()->suspended);
        $user = User::create(['name' => 'Ordinary', 'email' => 'ordinary@example.invalid', 'password' => 'safe-password-123', 'role' => 'superadmin']);
        $this->assertSame('trader', $user->fresh()->role);
    }

    public function test_superadmin_promotion_is_explicit_audited_and_revokes_old_sessions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_version' => 0]);
        $this->artisan('platform:superadmin', ['email' => $user->email])->assertSuccessful();
        $this->assertSame('superadmin', $user->fresh()->role);
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertDatabaseHas('infrastructure_audit', ['action' => 'role.superadmin.granted']);
        $this->getJson('/superadmin')->assertUnauthorized();
        $this->artisan('platform:admin', ['email' => $user->email])->assertFailed();
        $this->assertSame('superadmin', $user->fresh()->role);
        $this->artisan('platform:superadmin', ['email' => 'missing@example.invalid'])->assertFailed();
    }

    public function test_maintenance_actions_require_password_are_audited_and_never_create_a_payment(): void
    {
        $this->actingAs($this->superadmin());
        $this->postJson('/superadmin/operations/reconcile', ['current_password' => 'wrong', 'reason' => 'Vérification manuelle'])->assertUnprocessable();
        $this->post('/superadmin/operations/reconcile', ['current_password' => 'password', 'reason' => 'Vérification manuelle'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('infrastructure_audit', ['action' => 'operation.reconcile']);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }
}
