<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create();
        if ($admin) {
            $user->role = 'admin';
            $user->save();
        }

        return $user;
    }

    private function market(User $creator, string $status = 'open'): int
    {
        return DB::table('markets')->insertGetId([
            'creator_id' => $creator->id, 'title' => 'Marché de test vérifiable',
            'description' => 'Un résultat fictif déterminé pour tester le moteur.',
            'category' => 'Sport', 'status' => $status, 'closes_at' => now()->addDay(),
            'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function deposit(User $user, int $amount = 1000, ?string $reference = null)
    {
        return $this->actingAs($user)->post('/wallet', ['kind' => 'deposit', 'amount' => $amount, 'reference' => $reference ?? (string) Str::uuid()]);
    }

    private function bet(User $user, int $market, int $amount = 100, ?string $reference = null)
    {
        return $this->actingAs($user)->post("/markets/$market/positions", ['outcome' => 'yes', 'amount' => $amount, 'reference' => $reference ?? (string) Str::uuid()]);
    }

    private function balance(User $user): int
    {
        return (int) DB::table('accounts')->where('user_id', $user->id)->value('balance');
    }

    public function test_registration_authenticates_and_never_accepts_admin_role(): void
    {
        $this->post('/register', ['name' => 'Trader', 'email' => 'trader@example.test', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123', 'role' => 'admin'])->assertRedirect('/markets');
        $user = User::first();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('trader', $user->role);
        $this->assertTrue(Hash::check('test-password-123', $user->password));
        $this->assertSame(0, $this->balance($user));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'test-password-123'])->assertRedirect('/markets');
    }

    public function test_private_pages_require_login_and_admin_permissions_are_server_side(): void
    {
        $this->get('/wallet')->assertRedirect('/login');
        $user = $this->user();
        $id = $this->market($user, 'pending');
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->post("/admin/markets/$id/moderate", ['status' => 'open'])->assertForbidden();
        $this->post("/admin/markets/$id/resolve", ['result' => 'void', 'source' => 'Annulation de test'])->assertForbidden();
        $this->post('/admin/users/'.$user->id, ['suspended' => true])->assertForbidden();
    }

    public function test_all_pages_render_with_persistent_data(): void
    {
        $user = $this->user(true);
        foreach (['/', '/login', '/register', '/help', '/terms', '/privacy', '/data', '/demo'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->actingAs($user);
        foreach (['markets', 'positions', 'transactions', 'wallet', 'creator', 'support', 'notifications', 'profile', 'admin', 'admin/markets', 'admin/users'] as $page) {
            $this->get('/'.$page)->assertOk()->assertInertia(fn (Assert $a) => $a->component('Platform')->where('page', str_replace('/', '-', $page)));
        }
        $id = $this->market($user);
        $this->get('/markets/'.$id)->assertOk();
        $this->get('/markets/99999')->assertNotFound();
    }

    public function test_deposits_withdrawals_and_idempotency_keep_ledger_balanced(): void
    {
        $user = $this->user();
        $reference = (string) Str::uuid();
        $this->deposit($user, 1000, $reference)->assertSessionHasNoErrors();
        $this->deposit($user, 1000, $reference)->assertSessionHasNoErrors();
        $this->assertSame(1000, $this->balance($user));
        $this->deposit($user, 1100, $reference)->assertSessionHasErrors('reference');
        $this->post('/wallet', ['kind' => 'withdraw', 'amount' => 1500, 'reference' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->post('/wallet', ['kind' => 'withdraw', 'amount' => 1000, 'reference' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->balance($user));
        $this->assertSame(0, (int) DB::table('ledger_entries')->sum('amount'));
        $this->assertSame(0, (int) DB::table('accounts')->sum('balance'));
        $this->assertDatabaseCount('ledger_transactions', 2);
        foreach (DB::table('ledger_transactions')->get() as $tx) {
            $this->assertSame(0, (int) DB::table('ledger_entries')->where('transaction_id', $tx->id)->sum('amount'));
        }
    }

    public function test_invalid_financial_inputs_and_cross_user_reference_reuse_are_rejected(): void
    {
        $user = $this->user();
        $reference = (string) Str::uuid();
        $this->deposit($user, 1000, $reference);
        foreach ([-100, 0, 1.5, 1000001] as $amount) {
            $this->post('/wallet', ['kind' => 'deposit', 'amount' => $amount, 'reference' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        }
        $other = $this->user();
        $this->deposit($other, 1000, $reference)->assertSessionHasErrors('reference');
        $this->assertSame(0, $this->balance($other));
        $this->assertSame(1000, $this->balance($user));
    }

    public function test_position_uses_server_odds_and_duplicate_requests_do_not_charge_twice(): void
    {
        $user = $this->user();
        $id = $this->market($user);
        $this->deposit($user);
        $reference = (string) Str::uuid();
        $this->post("/markets/$id/positions", ['outcome' => 'yes', 'amount' => 101, 'odds' => 999999, 'payout' => 999999, 'reference' => $reference])->assertRedirect('/positions');
        $this->bet($user, $id, 101, $reference)->assertRedirect('/positions');
        $this->assertSame(899, $this->balance($user));
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseHas('positions', ['odds' => 235, 'payout' => 237]);
        $this->bet($user, $id, 1000)->assertSessionHasErrors('amount');
        DB::table('markets')->where('id', $id)->update(['closes_at' => now()->subMinute()]);
        $this->bet($user, $id)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_market_creation_moderation_and_visibility(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/markets', ['title' => 'Question fictive de test ?', 'description' => 'Critères objectifs et source publique de résolution.', 'category' => 'Sport', 'closes_at' => now()->addDay()->toISOString(), 'yes_odds' => 200, 'no_odds' => 200, 'status' => 'open'])->assertSessionHasNoErrors();
        $market = DB::table('markets')->first();
        $this->assertSame('pending', $market->status);
        $other = $this->user();
        $this->actingAs($other)->get('/markets/'.$market->id)->assertForbidden();
        $this->get('/markets')->assertInertia(fn (Assert $a) => $a->has('markets.data', 0));
        $admin = $this->user(true);
        $this->actingAs($admin)->post('/admin/markets/'.$market->id.'/moderate', ['status' => 'open'])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/markets/'.$market->id)->assertOk();
        $this->get('/markets?q=absent')->assertInertia(fn (Assert $a) => $a->has('markets.data', 0));
    }

    public function test_settlement_pays_winners_once_and_rejects_early_or_conflicting_results(): void
    {
        $user = $this->user();
        $loser = $this->user();
        $admin = $this->user(true);
        $id = $this->market($admin);
        $this->deposit($user);
        $this->bet($user, $id);
        $this->deposit($loser);
        $this->post("/markets/$id/positions", ['outcome' => 'no', 'amount' => 100, 'reference' => (string) Str::uuid()]);
        $this->actingAs($admin)->postJson("/admin/markets/$id/resolve", ['result' => 'yes', 'source' => 'Source de résolution test'])->assertStatus(422);
        DB::table('markets')->where('id', $id)->update(['closes_at' => now()->subMinute()]);
        $this->post("/admin/markets/$id/resolve", ['result' => 'yes', 'source' => 'Source de résolution test'])->assertSessionHasNoErrors();
        $this->post("/admin/markets/$id/resolve", ['result' => 'yes', 'source' => 'Source de résolution test'])->assertSessionHasNoErrors();
        $this->assertSame(1135, $this->balance($user));
        $this->assertSame(900, $this->balance($loser));
        $this->assertDatabaseHas('positions', ['user_id' => $user->id, 'status' => 'won']);
        $this->assertDatabaseHas('positions', ['user_id' => $loser->id, 'status' => 'lost']);
        $this->postJson("/admin/markets/$id/resolve", ['result' => 'no', 'source' => 'Autre résultat non autorisé'])->assertStatus(422);
        $this->assertSame(0, (int) DB::table('ledger_entries')->sum('amount'));
    }

    public function test_void_refunds_all_positions_once(): void
    {
        $user = $this->user();
        $admin = $this->user(true);
        $id = $this->market($admin);
        $this->deposit($user);
        $this->bet($user, $id, 300);
        $this->actingAs($admin)->post("/admin/markets/$id/resolve", ['result' => 'void', 'source' => 'Annulation fictive de test']);
        $this->post("/admin/markets/$id/resolve", ['result' => 'void', 'source' => 'Annulation fictive de test']);
        $this->assertSame(1000, $this->balance($user));
        $this->assertDatabaseHas('positions', ['status' => 'refunded']);
        $this->assertSame(1, DB::table('ledger_transactions')->where('kind', 'Remboursement')->count());
    }

    public function test_support_ownership_replies_notifications_and_closure(): void
    {
        $user = $this->user();
        $other = $this->user();
        $admin = $this->user(true);
        $this->actingAs($user)->post('/support', ['subject' => 'Question de test', 'body' => 'Comment fonctionne le portefeuille ?'])->assertRedirect('/support/1');
        $this->actingAs($other)->get('/support/1')->assertForbidden();
        $this->post('/support/1', ['body' => 'Intrusion', 'status' => 'closed'])->assertForbidden();
        $this->actingAs($admin)->post('/support/1', ['body' => 'Il fonctionne en simulation.', 'status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_tickets', ['status' => 'closed']);
        $this->assertDatabaseHas('platform_notifications', ['user_id' => $user->id, 'read_at' => null]);
        $this->actingAs($other)->post('/notifications/read');
        $this->assertDatabaseHas('platform_notifications', ['user_id' => $user->id, 'read_at' => null]);
        $this->actingAs($user)->post('/notifications/read');
        $this->assertSame(0, DB::table('platform_notifications')->whereNull('read_at')->count());
        $this->post('/support/1', ['body' => 'Merci, une autre question.', 'status' => 'open'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_tickets', ['status' => 'open']);
    }

    public function test_suspension_blocks_existing_sessions_and_profile_requires_password(): void
    {
        $user = $this->user();
        $admin = $this->user(true);
        $this->actingAs($user)->post('/profile', ['name' => 'Changed', 'email' => $user->email, 'current_password' => 'bad'])->assertSessionHasErrors('current_password');
        $this->assertSame($user->name, $user->fresh()->name);
        $this->actingAs($admin)->post('/admin/users/'.$user->id, ['suspended' => true])->assertSessionHasNoErrors();
        $this->actingAs($user->fresh())->get('/markets')->assertForbidden();
        $this->deposit($user->fresh())->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/users/'.$admin->id, ['suspended' => true])->assertStatus(422);
    }

    public function test_pipeline_import_is_admin_only_validated_and_idempotent(): void
    {
        $user = $this->user();
        $admin = $this->user(true);
        $this->actingAs($user)->post('/admin/pipeline/import', ['id' => 7])->assertForbidden();
        Http::fake(['*/api/markets' => Http::response(['data' => [[
            'id' => 7, 'title' => 'Un marché généré pour les tests ?', 'description' => 'Une description assez détaillée pour la modération.',
            'category' => 'crypto', 'options' => ['Oui', 'Non'], 'odds' => [2.35, 1.7], 'expiry' => now()->addDay()->toISOString(),
        ]]])]);
        $this->actingAs($admin)->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        $this->assertDatabaseCount('markets', 1);
        $this->assertDatabaseHas('markets', ['status' => 'pending', 'yes_odds' => 235, 'category' => 'Crypto']);
        $this->get('/admin/pipeline')->assertOk();
        $this->post('/admin/pipeline/import', ['id' => 99])->assertSessionHasErrors('pipeline');
    }

    public function test_pipeline_unavailability_has_a_recoverable_page_and_generation_error(): void
    {
        $this->actingAs($this->user(true));
        Http::fake(['*' => Http::failedConnection()]);
        $this->get('/predictions')->assertOk()->assertInertia(fn (Assert $a) => $a->where('pipeline.status', 'unavailable'));
        $this->get('/admin/pipeline')->assertOk()->assertInertia(fn (Assert $a) => $a->where('pipeline.status', 'unavailable'));
        $this->post('/admin/pipeline/generate')->assertSessionHasErrors('pipeline');
    }

    public function test_notifications_and_transactions_are_scoped_to_the_current_account(): void
    {
        $owner = $this->user();
        $this->deposit($owner);
        $this->actingAs($this->user())->get('/transactions')->assertInertia(fn (Assert $a) => $a->has('transactions.data', 0));
        $this->get('/notifications')->assertInertia(fn (Assert $a) => $a->has('notifications.data', 0)->where('balance', 0));
    }

    public function test_business_errors_return_to_the_form_and_unknown_pages_have_an_error_screen(): void
    {
        $admin = $this->user(true);
        $id = $this->market($admin);
        $this->actingAs($admin)->from('/markets/'.$id)->post("/admin/markets/$id/resolve", ['result' => 'yes', 'source' => 'Résultat trop précoce'])->assertRedirect('/markets/'.$id)->assertSessionHasErrors('operation');
        $this->get('/unknown-page')->assertNotFound()->assertInertia(fn (Assert $a) => $a->component('Error')->where('status', 404));
    }
}
