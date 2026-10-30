<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    }

    public function test_password_change_revokes_another_session_and_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $old = $this->app['session']->all();
        $oldId = $this->app['session']->getId();
        $this->post('/profile', ['name' => 'Updated', 'email' => $user->email, 'current_password' => 'password',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasNoErrors();
        $this->assertNotSame($oldId, $this->app['session']->getId());
        $this->get('/wallet')->assertOk();
        $this->app['session']->flush();
        $this->withSession($old);
        $this->app['auth']->forgetGuards();
        $this->getJson('/wallet')->assertUnauthorized();
        $this->assertGuest();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_suspension_followed_by_reactivation_does_not_restore_an_old_session(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->login($user);
        $old = $this->app['session']->all();
        $this->actingAs($admin)->post('/admin/users/'.$user->id, ['suspended' => true])->assertSessionHasNoErrors();
        $this->post('/admin/users/'.$user->id, ['suspended' => false])->assertSessionHasNoErrors();
        $this->app['session']->flush();
        $this->withSession($old);
        $this->app['auth']->forgetGuards();
        $this->getJson('/positions')->assertUnauthorized();
    }

    public function test_suspended_public_response_does_not_expose_auth_balance_or_notifications(): void
    {
        $user = User::factory()->create();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
        $this->actingAs($user);
        DB::table('users')->where('id', $user->id)->update(['suspended' => true]);
        $this->get('/')->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->missing('auth')->missing('balance')->missing('unread'));
        $this->assertGuest();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth', null)->where('balance', 0)->where('unread', 0));
    }

    public function test_admin_demotion_takes_effect_without_a_new_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk();
        DB::table('users')->where('id', $admin->id)->update(['role' => 'trader']);
        $this->get('/admin')->assertForbidden();
        $this->postJson('/admin/pipeline/generate')->assertForbidden();
    }

    public function test_guest_cannot_read_or_mutate_private_endpoints(): void
    {
        foreach (['/dashboard', '/wallet', '/positions', '/transactions', '/profile', '/support', '/support/1', '/creator', '/notifications', '/markets', '/markets/1', '/predictions', '/admin', '/admin/users', '/admin/markets', '/admin/markets/1', '/admin/pipeline'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        foreach (['/wallet/ndsgpay', '/profile', '/markets', '/markets/1/positions', '/markets/1/quote', '/support', '/support/1', '/notifications/read', '/admin/markets/1/moderate', '/admin/markets/1/resolve', '/admin/users/1', '/admin/pipeline/import', '/admin/pipeline/generate'] as $path) {
            $this->postJson($path, [])->assertUnauthorized();
        }
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_trader_cannot_use_any_admin_read_or_mutation(): void
    {
        Http::preventStrayRequests();
        $trader = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($trader);
        foreach (['/admin', '/admin/users', '/admin/markets', '/admin/markets/123', '/admin/pipeline'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        foreach (['/admin/markets/123/moderate', '/admin/markets/123/resolve', '/admin/users/'.$other->id, '/admin/pipeline/import', '/admin/pipeline/generate'] as $path) {
            $this->postJson($path, [])->assertForbidden();
        }
        $this->assertFalse($other->fresh()->suspended);
        $this->assertDatabaseCount('markets', 0);
        Http::assertNothingSent();
    }

    public function test_profile_and_registration_never_accept_privileged_fields(): void
    {
        $this->post('/register', ['name' => 'Account', 'email' => 'identity@example.test', 'password' => 'password-123',
            'country' => 'GAB', 'currency' => 'XAF',
            'password_confirmation' => 'password-123', 'role' => 'admin', 'suspended' => true, 'auth_version' => 99,
            'email_verified_at' => now()->toISOString(), 'balance' => 10000])->assertSessionHasNoErrors();
        $user = User::firstOrFail();
        $this->assertSame('trader', $user->role);
        $this->assertFalse($user->suspended);
        $this->assertSame(0, $user->auth_version);
        $this->assertNull($user->email_verified_at);
        $this->post('/profile', ['name' => 'Changed', 'email' => $user->email, 'current_password' => 'password-123',
            'role' => 'admin', 'suspended' => true, 'auth_version' => 99])->assertSessionHasNoErrors();
        $this->assertSame('trader', $user->fresh()->role);
        $this->assertFalse($user->fresh()->suspended);
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'balance' => 0]);
    }

    public function test_wrong_password_duplicate_email_and_confirmation_do_not_change_profile(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $initial = $user->fresh()->getAttributes();
        $this->actingAs($user)->from('/profile')->post('/profile', ['name' => 'Changed', 'email' => 'changed@example.test',
            'current_password' => 'incorrect', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertSessionHasErrors('current_password');
        $this->assertFalse(session()->hasOldInput('current_password'));
        $this->assertFalse(session()->hasOldInput('password'));
        $this->assertFalse(session()->hasOldInput('password_confirmation'));
        $this->postJson('/profile', ['name' => 'Changed', 'email' => $other->email, 'current_password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/profile', ['name' => 'Changed', 'email' => $user->email, 'current_password' => 'password',
            'password' => 'new-password-123', 'password_confirmation' => 'different-password'])->assertUnprocessable();
        $this->assertSame($initial, $user->fresh()->getAttributes());
    }

    public function test_email_change_clears_verification_without_revoking_unchanged_password_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->remember_token;
        $this->actingAs($user)->post('/profile', ['name' => 'Updated', 'email' => 'updated@example.test', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertSame($token, $user->fresh()->remember_token);
        $this->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.email', 'updated@example.test'));
    }

    public function test_unknown_roles_and_suspended_accounts_cannot_authenticate(): void
    {
        $unknown = User::factory()->create(['role' => 'superadmin']);
        $suspended = User::factory()->create(['suspended' => true]);
        foreach ([$unknown, $suspended] as $user) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertGuest();
        }
        $this->actingAs($unknown)->getJson('/wallet')->assertForbidden();
        $this->assertGuest();
    }

    public function test_suspension_is_idempotent_and_self_suspension_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $this->actingAs($admin)->post('/admin/users/'.$user->id, ['suspended' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->auth_version);
        $token = $user->fresh()->remember_token;
        $this->post('/admin/users/'.$user->id, ['suspended' => true])->assertSessionHasNoErrors();
        $this->post('/admin/users/'.$user->id, ['suspended' => false])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertSame($token, $user->fresh()->remember_token);
        $this->postJson('/admin/users/'.$admin->id, ['suspended' => true])->assertUnprocessable();
        $this->assertFalse($admin->fresh()->suspended);
        $this->postJson('/admin/users/999999', ['suspended' => true])->assertNotFound();
    }

    public function test_private_collections_and_mutations_ignore_forged_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach ([$owner, $other] as $user) {
            DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
            $this->actingAs($user)->post('/support', ['subject' => 'Ticket privé '.$user->id, 'body' => 'Message privé du propriétaire'])->assertSessionHasNoErrors();
        }
        $otherTicket = DB::table('support_tickets')->where('user_id', $other->id)->value('id');
        $reference = DB::table('ledger_transactions')->where('user_id', $other->id)->value('reference');
        $this->actingAs($owner)->get('/transactions?q='.$reference.'&user_id='.$other->id)->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0));
        $this->get('/wallet?user_id='.$other->id)->assertInertia(fn (Assert $page) => $page->has('transactions.data', 1)->where('transactions.data.0.user_id', $owner->id));
        $this->get('/support/'.$otherTicket)->assertForbidden();
        $this->postJson('/support/'.$otherTicket, ['body' => 'Intrusion', 'status' => 'closed', 'user_id' => $other->id])->assertForbidden();
        $this->post('/notifications/read', ['user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('platform_notifications')->where('user_id', $other->id)->whereNotNull('read_at')->count());
        $this->assertDatabaseHas('support_tickets', ['id' => $otherTicket, 'status' => 'open']);
        $this->actingAs($admin)->get('/support/'.$otherTicket)->assertOk();
        $this->get('/transactions?user_id='.$other->id)->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0));
    }

    public function test_unpublished_markets_are_limited_to_creator_and_admin(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $id = DB::table('markets')->insertGetId(['creator_id' => $owner->id, 'title' => 'Marché privé de test', 'description' => 'Critères de test',
            'category' => 'Sport', 'status' => 'pending', 'closes_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->get('/markets/'.$id)->assertOk();
        $this->actingAs($other)->get('/markets/'.$id)->assertForbidden();
        $this->actingAs($admin)->get('/markets/'.$id)->assertOk();
        DB::table('markets')->where('id', $id)->update(['status' => 'rejected']);
        $this->actingAs($other)->get('/markets/'.$id)->assertForbidden();
        $this->actingAs($owner)->get('/markets/'.$id)->assertOk();
    }

    public function test_private_props_exclude_credentials_and_responses_disable_caching(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/profile');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->has('auth', 7)
            ->where('auth.country', null)->where('auth.wallet_currency', 'XAF')
            ->missing('auth.password')->missing('auth.remember_token')->missing('auth.auth_version'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['password', 'remember_token', 'auth_version'] as $field) {
            $this->assertArrayNotHasKey($field, $user->toArray());
        }
        $this->post('/logout')->assertRedirect('/');
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth', null)->where('balance', 0)->where('unread', 0));
        $this->assertGuest();
    }

    public function test_login_has_a_per_account_limit_across_ips_and_email_case_variations(): void
    {
        $user = User::factory()->create(['email' => 'limited@example.test']);
        for ($index = 1; $index <= 10; $index++) {
            $email = $index % 2 ? strtoupper($user->email) : $user->email;
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$index])->postJson('/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
        $this->travel(61)->seconds();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    }

    public function test_additive_migration_preserves_existing_users_and_can_be_reversed(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $migration = require database_path('migrations/2026_10_19_000001_add_auth_version_to_users.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));
        $migration->up();
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_login_ip_limit_also_bounds_attempts_against_different_accounts(): void
    {
        for ($index = 0; $index < 6; $index++) {
            $this->postJson('/login', ['email' => "unknown$index@example.test", 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'another@example.test', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_native_request_forgery_protection_is_active_for_auth_and_private_mutations(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        foreach (['/login', '/register', '/wallet/ndsgpay', '/profile', '/admin/users/1'] as $path) {
            $this->postJson($path, [])->assertStatus(419);
        }
    }

    public function test_legacy_version_zero_sessions_work_until_access_is_revoked(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        session()->forget('auth_version');
        $legacy = session()->all();
        $this->get('/wallet')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['auth_version' => 1]);
        session()->flush();
        $this->withSession($legacy);
        $this->app['auth']->forgetGuards();
        $this->getJson('/wallet')->assertUnauthorized();
    }
}
