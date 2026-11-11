<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Markets\MarketDraft;
use App\Modules\Markets\MarketWorkflow;
use App\Modules\Predictions\Infrastructure\OfficialAfricanCalendars;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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

    private function fakeOfficialCalendars(int $kickoffHour = 16, int $unionStatus = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $articlePath = '/fr/can2025/infos/programme-can/';
        $schedule = <<<HTML
            <article><time>mardi, 03 novembre 2026</time>
            <p>PROGRAMME DE LA 3E JOURNÉE (TOUS LES HORAIRES EN GMT)</p>
            <p>• Groupe E : RD Congo – Sierra Leone (12/11, {$kickoffHour}h00)</p>
            <p>• Groupe F : Match erroné – Autre équipe (31/11, 16h00)</p>
            </article>
            HTML;
        $union = <<<'HTML'
            <h1>Upcoming News &amp; Events</h1>
            <div class="views-row">
              <div class="views-field-field-short-title"><a href="/en/newsevents/20261113/executive-council">Extraordinary Session of the Executive Council</a></div>
              <div class="views-field-field-date"><time datetime="2026-11-13T06:00:00Z">13 November 2026</time></div>
            </div>
            <div class="views-row">
              <div class="views-field-field-short-title"><a href="/en/newsevents/20261116/youth-forum">Pan-African Youth Forum</a></div>
              <div class="views-field-field-date"><time datetime="2026-11-16T06:00:00Z">16 November 2026</time></div>
            </div>
            HTML;
        Http::fake([
            OfficialAfricanCalendars::CAF.'*' => Http::response(['items' => [
                ['title' => 'Éliminatoires de la CAN 2027', 'url' => $articlePath, 'date' => '2026-11-03T00:00:00Z'],
                ['title' => 'Éliminatoires de la CAN 2027', 'url' => 'https://foreign.example/calendar', 'date' => '2026-11-03T00:00:00Z'],
            ]]),
            'https://www.cafonline.com'.$articlePath => Http::response($schedule),
            OfficialAfricanCalendars::AU => Http::response($union, $unionStatus),
            OfficialAfricanCalendars::SARB => Http::response('<table><tr><th>2026/11/19&nbsp;-&nbsp;15:00</th><td>19 November 2026 press conference of the Monetary Policy Committee</td></tr></table>'),
        ]);
    }

    public function test_african_suggestions_notify_admins_and_require_review_before_publication(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10T12:00:00Z'));
        $this->fakeOfficialCalendars();
        config(['predictions.enabled' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'notifications_enabled' => false]);
        $reviewer = User::factory()->create(['role' => 'superadmin']);
        $trader = User::factory()->create();
        $suspended = User::factory()->create(['role' => 'admin', 'suspended' => true]);
        $this->actingAs($trader)->postJson('/admin/pipeline/generate-africa')->assertForbidden();

        $this->actingAs($admin)->post('/admin/pipeline/generate-africa')->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Sport', 'Économie', 'Politique', 'Société'],
            DB::table('markets')->where('status', 'pending')->distinct()->pluck('category')->all());
        $count = DB::table('markets')->count();
        $this->post('/admin/pipeline/generate-africa')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('markets', $count);
        $this->assertDatabaseCount('platform_notifications', $count * 2);
        $market = DB::table('markets')->where('pipeline_source', 'africa:caf-2027-3-rd-congo-sierra-leone')->sole();
        $this->assertSame('2026-11-12 15:55:00', $market->source_closes_at);
        $this->assertStringContainsString('https://www.cafonline.com/', $market->description);
        $this->assertDatabaseHas('platform_notifications', ['user_id' => $reviewer->id, 'market_id' => $market->id]);
        $this->assertDatabaseMissing('platform_notifications', ['user_id' => $suspended->id, 'market_id' => $market->id]);
        $this->actingAs($trader)->get("/markets/$market->id")->assertForbidden();
        $this->postJson("/admin/markets/$market->id/moderate", ['status' => 'open'])->assertForbidden();
        $this->actingAs($reviewer)->postJson("/admin/markets/$market->id/moderate", ['status' => 'open'])
            ->assertUnprocessable()->assertJsonValidationErrors('operation');

        $draft = [
            'revision' => MarketDraft::revision($market),
            'title' => 'La RD Congo gagnera-t-elle le match contre la Sierra Leone ?',
            'description' => $market->description, 'category' => $market->category,
            'closes_at' => $market->closes_at, 'yes_odds' => 175, 'no_odds' => 230,
        ];
        $this->post("/admin/markets/$market->id/moderate", ['status' => 'pending', ...$draft])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('markets', ['id' => $market->id, 'status' => 'pending', 'title' => $draft['title'], 'yes_odds' => 175]);
        $this->postJson("/admin/markets/$market->id/moderate", ['status' => 'open', ...$draft])
            ->assertUnprocessable()->assertJsonValidationErrors('operation');

        $draft['revision'] = MarketDraft::revision(DB::table('markets')->find($market->id));
        $this->postJson("/admin/markets/$market->id/moderate", ['status' => 'open', ...$draft, 'closes_at' => '2026-11-12T17:00:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors('closes_at');
        $this->post("/admin/markets/$market->id/moderate", ['status' => 'open', ...$draft, 'yes_odds' => 180])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('markets', ['id' => $market->id, 'status' => 'open', 'yes_odds' => 180]);
        $this->actingAs($trader)->get("/markets/$market->id")->assertOk();
    }

    public function test_generation_filters_domains_and_skips_past_events(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14T12:00:00Z'));
        $this->fakeOfficialCalendars();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/admin/pipeline/generate-africa', ['category' => 'Sport'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('markets', 0);
        $this->post('/admin/pipeline/generate-africa', ['category' => 'Économie'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('markets', 1);
        $this->assertDatabaseHas('markets', ['category' => 'Économie', 'status' => 'pending', 'source_closes_at' => '2026-11-19 12:55:00']);
    }

    public function test_discovery_command_survives_a_source_failure_and_never_publishes(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10T12:00:00Z'));
        $this->fakeOfficialCalendars(unionStatus: 503);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->artisan('markets:discover-africa', ['--dry-run' => true])->expectsOutputToContain('Union africaine')->assertFailed();
        $this->assertDatabaseCount('markets', 0);
        $this->artisan('markets:discover-africa')->expectsOutputToContain('Union africaine')->assertFailed();
        $this->assertDatabaseCount('markets', 2);
        $this->assertSame(2, DB::table('markets')->where('status', 'pending')->where('creator_id', $admin->id)->count());
        $this->assertDatabaseCount('platform_notifications', 2);
    }

    public function test_official_date_moving_earlier_tightens_cutoff_without_overwriting_edits(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10T12:00:00Z'));
        $this->fakeOfficialCalendars();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/pipeline/generate-africa', ['category' => 'Sport'])->assertSessionHasNoErrors();
        DB::table('markets')->update(['title' => 'Question déjà relue par un administrateur ?']);
        $this->fakeOfficialCalendars(kickoffHour: 15);
        $this->post('/admin/pipeline/generate-africa', ['category' => 'Sport'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('markets', 1);
        $this->assertDatabaseCount('platform_notifications', 1);
        $this->assertDatabaseHas('markets', [
            'title' => 'Question déjà relue par un administrateur ?', 'source_closes_at' => '2026-11-12 14:55:00',
            'closes_at' => '2026-11-12 15:55:00', 'status' => 'pending',
        ]);
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

    public function test_publishing_an_imported_suggestion_adds_it_to_the_market_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $trader = User::factory()->create();
        Http::fake(['*' => Http::response(['simulation' => false, 'data' => [$this->suggestion()]])]);
        $this->actingAs($admin)->post('/admin/pipeline/import', ['id' => 7])->assertSessionHasNoErrors();
        $market = DB::table('markets')->sole();
        $adminUrl = "/admin/markets/$market->id";

        $this->actingAs($trader)->get('/markets')->assertInertia(
            fn (Assert $page) => $page->has('markets.data', 0),
        );
        $this->actingAs($admin)->from($adminUrl)->post("$adminUrl/moderate", [
            'status' => 'open',
            'revision' => MarketDraft::revision($market),
            'title' => $market->title,
            'description' => $market->description,
            'category' => $market->category,
            'closes_at' => $market->closes_at,
            'yes_odds' => $market->yes_odds,
            'no_odds' => $market->no_odds,
        ])->assertRedirect($adminUrl)->assertSessionHas('success', 'Modération enregistrée. Marché publié.');

        $this->actingAs($trader)->get('/markets')->assertInertia(
            fn (Assert $page) => $page->has('markets.data', 1)
                ->where('markets.data.0.id', $market->id)
                ->where('markets.data.0.status', 'open'),
        );
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
