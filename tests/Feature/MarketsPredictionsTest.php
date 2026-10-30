<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Markets\MarketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketsPredictionsTest extends TestCase
{
    use RefreshDatabase;

    private function suggestion(array $changes = []): array
    {
        return array_replace(['id' => 7, 'title' => 'Une question de démonstration ?', 'description' => 'Règles explicites pour un scénario entièrement fictif.',
            'category' => 'crypto', 'options' => ['Oui', 'Non'], 'odds' => ['2.35', '1.70'], 'expiry' => now()->addDay()->toISOString(), 'status' => 'active'], $changes);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['predictions.enabled' => true]);
        Http::preventStrayRequests();
    }

    public function test_import_bypasses_display_cache_and_never_imports_expired_or_inactive_suggestions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fakeSequence()->push(['simulation' => false, 'data' => [$this->suggestion()]])->push(['simulation' => false, 'data' => [$this->suggestion(['expiry' => now()->subDay()->toISOString()])]]);
        $this->getJson('/api/markets')->assertOk();
        $this->postJson('/admin/pipeline/import', ['id' => 7])->assertUnprocessable();
        $this->assertDatabaseCount('markets', 0);
        Http::assertSentCount(2);
    }

    public function test_import_replay_works_during_outage_and_preserves_local_moderation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion()]])]);
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        DB::table('markets')->where('id', 1)->update(['status' => 'rejected']);
        Cache::flush();
        config(['predictions.enabled' => false]);
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('markets', ['id' => 1, 'status' => 'rejected']);
        Http::assertSentCount(1);
    }

    public function test_import_rejects_unsupported_precision_and_unknown_categories(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fakeSequence()->push(['simulation' => false, 'data' => [$this->suggestion(['odds' => ['2.345', '1.70']])]])->push(['simulation' => false, 'data' => [$this->suggestion(['category' => 'unrecognized'])]]);
        $this->postJson('/admin/pipeline/import', ['id' => 7])->assertUnprocessable();
        $this->postJson('/admin/pipeline/import', ['id' => 7])->assertUnprocessable();
        $this->assertDatabaseCount('markets', 0);
    }

    public function test_moderation_replay_has_no_duplicate_notification_and_conflict_is_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/markets', ['title' => 'Question de démonstration ?', 'description' => 'Règles fictives mais suffisamment précises.',
            'category' => 'Sport', 'closes_at' => now()->addDay()->toISOString(), 'yes_odds' => 235, 'no_odds' => 170])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/admin/markets/1/moderate', ['status' => 'open'])->assertRedirect();
        $this->postJson('/admin/markets/1/moderate', ['status' => 'open'])->assertRedirect();
        $this->postJson('/admin/markets/1/moderate', ['status' => 'rejected'])->assertUnprocessable();
        $this->assertDatabaseCount('platform_notifications', 1);
    }

    public function test_disabling_pipeline_hides_cached_results(): void
    {
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [['id' => 7]]])]);
        $this->getJson('/api/markets')->assertOk();
        config(['predictions.enabled' => false]);
        $this->getJson('/api/markets')->assertStatus(503);
    }

    public static function invalidSuggestions(): array
    {
        return [
            'inactive' => [['status' => 'closed']],
            'reversed outcomes' => [['options' => ['Non', 'Oui']]],
            'non binary' => [['options' => ['Oui', 'Non', 'Nul']]],
            'expired' => [['expiry' => '2000-01-01T00:00:00Z']],
            'invalid date' => [['expiry' => 'not-a-date']],
            'blank title' => [['title' => str_repeat(' ', 30)]],
            'missing rules' => [['description' => '']],
            'category array' => [['category' => ['crypto']]],
            'exponent string' => [['odds' => ['2e0', '1.7']]],
            'boolean odds' => [['odds' => [true, 1.7]]],
            'associative odds' => [['odds' => ['yes' => 2, 'no' => 2]]],
            'odds too low' => [['odds' => ['1.00', '2.00']]],
            'odds too high' => [['odds' => ['10.01', '2.00']]],
        ];
    }

    #[DataProvider('invalidSuggestions')]
    public function test_invalid_suggestions_never_create_local_markets(array $changes): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion($changes)]])]);
        $this->postJson('/admin/pipeline/import', ['id' => 7])->assertUnprocessable()->assertJsonValidationErrors('pipeline');
        $this->assertDatabaseCount('markets', 0);
    }

    public function test_import_uses_exact_server_odds_and_utc_and_ignores_privileged_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion(['odds' => ['1.01', '10.00'], 'expiry' => now()->addDay()->setTimezone('Africa/Kinshasa')->toIso8601String()])]])]);
        $this->actingAs($admin)->post('/admin/pipeline/import', ['id' => 7, 'status' => 'open', 'creator_id' => 9999, 'yes_odds' => 999, 'pipeline_source' => 'forged'])->assertRedirect('/markets/1');
        $this->assertDatabaseHas('markets', ['status' => 'pending', 'creator_id' => $admin->id, 'yes_odds' => 101, 'no_odds' => 1000]);
        $this->assertSame(now()->addDay()->utc()->format('Y-m-d H:i:s'), DB::table('markets')->value('closes_at'));
    }

    public function test_url_trailing_slash_does_not_duplicate_historical_source(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion()]])]);
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        DB::table('markets')->update(['pipeline_source' => hash('sha256', rtrim(config('predictions.url'), '/').'/').':7']);
        config(['predictions.url' => rtrim(config('predictions.url'), '/').'/']);
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        $this->assertDatabaseCount('markets', 1);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_duplicate_provider_ids_and_redirects_are_rejected(): void
    {
        Http::fakeSequence()->push(['simulation' => false, 'data' => [['id' => 7], ['id' => 7]]])->push(['simulation' => false, 'data' => [['id' => 7]]], 302);
        $this->getJson('/api/markets')->assertStatus(503);
        Cache::flush();
        $this->getJson('/api/markets')->assertStatus(503);
    }

    public function test_upstream_error_status_and_non_object_rows_are_not_successful_results(): void
    {
        Http::fakeSequence()->push(['status' => 'error', 'data' => [['id' => 7]]])->push(['simulation' => false, 'data' => [null]]);
        $this->getJson('/api/markets')->assertStatus(503);
        Cache::flush();
        $this->getJson('/api/markets')->assertStatus(503);
    }

    public function test_generation_invalidates_all_market_domain_caches_and_sends_configured_token(): void
    {
        config(['predictions.token' => 'test-only-token']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fakeSequence()->push(['simulation' => false, 'data' => [['id' => 1]]])->push(['simulation' => false, 'data' => [['id' => 2]]])
            ->push(['status' => 'success', 'simulation' => false])->push(['simulation' => false, 'data' => [['id' => 3]]])->push(['simulation' => false, 'data' => [['id' => 4]]]);
        $this->getJson('/api/markets?domain=crypto')->assertJsonPath('data.0.id', 1)->assertJsonStructure(['fetched_at']);
        $this->getJson('/api/markets')->assertJsonPath('data.0.id', 2);
        $this->post('/admin/pipeline/generate')->assertSessionHasNoErrors();
        $this->getJson('/api/markets?domain=crypto')->assertJsonPath('data.0.id', 3);
        $this->getJson('/api/markets')->assertJsonPath('data.0.id', 4);
        Http::assertSentCount(5);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('X-Pipeline-Token', 'test-only-token'));
    }

    public function test_publication_revalidates_corrupt_data_but_expired_proposal_can_be_rejected(): void
    {
        $creator = User::factory()->create();
        $id = app(MarketWorkflow::class)->submit($creator->id, ['title' => 'Une question fictive de test ?', 'description' => 'Règles fictives suffisamment détaillées.',
            'category' => 'Sport', 'closes_at' => now()->addDay()->toISOString(), 'yes_odds' => 200, 'no_odds' => 200]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        DB::table('markets')->where('id', $id)->update(['yes_odds' => 0]);
        $this->postJson("/admin/markets/$id/moderate", ['status' => 'open'])->assertUnprocessable();
        DB::table('markets')->where('id', $id)->update(['yes_odds' => 200, 'closes_at' => now()->subDay()]);
        $this->postJson("/admin/markets/$id/moderate", ['status' => 'open'])->assertUnprocessable();
        $this->postJson("/admin/markets/$id/moderate", ['status' => 'rejected'])->assertRedirect();
        $this->postJson("/admin/markets/$id/moderate", ['status' => 'rejected'])->assertRedirect();
        $this->assertDatabaseCount('platform_notifications', 1);
    }

    public function test_moderation_notification_failure_rolls_back_publication(): void
    {
        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $id = app(MarketWorkflow::class)->submit($creator->id, ['title' => 'Une question fictive de test ?', 'description' => 'Règles fictives suffisamment détaillées.',
            'category' => 'Sport', 'closes_at' => now()->addDay()->toISOString(), 'yes_odds' => 200, 'no_odds' => 200]);
        DB::connection()->beforeExecuting(function ($sql) {
            if (str_contains($sql, 'insert into "platform_notifications"')) {
                throw new \RuntimeException('Injected notification failure');
            }
        });
        try {
            app(MarketWorkflow::class)->moderate($admin->id, $id, 'open');
            $this->fail('Failure expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected notification failure', $exception->getMessage());
        }
        $this->assertDatabaseHas('markets', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('platform_notifications', 0);
    }

    public function test_ambiguous_historical_imports_are_preserved_for_manual_reconciliation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion()]])]);
        $this->post('/admin/pipeline/import', ['id' => 7])->assertRedirect('/markets/1');
        $copy = (array) DB::table('markets')->first();
        unset($copy['id']);
        $copy['pipeline_source'] = hash('sha256', rtrim(config('predictions.url'), '/').'/').':7';
        DB::table('markets')->insert($copy);
        $this->postJson('/admin/pipeline/import', ['id' => 7])->assertUnprocessable()->assertJsonValidationErrors('pipeline');
        $this->assertDatabaseCount('markets', 2);
        Http::assertSentCount(1);
    }
}
