<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Settlement\CafMatchReport;
use App\Modules\Settlement\OfficialResult;
use App\Modules\Settlement\ProcessMarket;
use App\Modules\Settlement\ResolveMarket;
use App\Modules\Trading\PlacePosition;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function market(User $user, array $providers = []): int
    {
        config(['market_resolution.automatic_enabled' => true, 'market_resolution.providers' => ['one' => 'https://one.test/result', 'two' => 'https://two.test/result']]);

        return DB::table('markets')->insertGetId([
            'creator_id' => $user->id, 'title' => 'Le résultat officiel correspond-il au contrat ?',
            'description' => 'Contrat utilisé pour les tests de résolution.', 'category' => 'Sport',
            'status' => 'open', 'closes_at' => now()->addMinute(), 'event_ends_at' => now()->addMinutes(2),
            'resolution_rule' => $providers === [] ? null : json_encode([
                'providers' => $providers, 'event' => 'finale-1', 'condition' => 'vainqueur-temps-reglementaire',
                'yes_value' => 'A', 'no_value' => 'B', 'on_cancel' => 'void', 'on_tie' => 'review',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function place(User $user, int $market, string $outcome = 'yes'): void
    {
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt de test', 1000, true));
        app(PlacePosition::class)->place($user->id, $market, $outcome, 100, (string) Str::uuid());
    }

    private function attestation(string $value, string $status = 'final'): array
    {
        return ['event' => 'finale-1', 'condition' => 'vainqueur-temps-reglementaire',
            'status' => $status, 'value' => $value, 'official_url' => 'https://official.test/finale-1',
            'verified_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
    }

    public function test_caf_pilot_settles_once_from_the_official_match_report(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-12T15:00:00Z'));
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->place($user, $market);
        DB::table('markets')->where('id', $market)->update([
            'event_ends_at' => '2026-11-12 18:30:00',
            'resolution_rule' => json_encode([
                'providers' => ['caf_match_report'], 'event' => CafMatchReport::EVENT,
                'condition' => CafMatchReport::CONDITION, 'yes_value' => 'home_win', 'no_value' => 'no_home_win',
                'on_cancel' => 'void', 'on_tie' => 'no',
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-13T12:00:00Z'));
        if (getenv('PREDIC_CAF_STAGING_LIVE') !== '1') {
            Http::preventStrayRequests();
            Http::fake([CafMatchReport::URL => Http::response(
                '<time>Thursday, 12 November 2026</time><h2>DR Congo 3-1 Sierra Leone: Match report</h2>'
            )]);
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->artisan('markets:process')->assertSuccessful();
        }
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'settled', 'result' => 'yes']);
        $evidence = json_decode(DB::table('market_transitions')->where('market_id', $market)
            ->where('to_status', 'resolved')->value('evidence'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3-1', $evidence[0]['score']);
        $this->assertSame(CafMatchReport::URL, $evidence[0]['url']);
        $this->assertDatabaseCount('position_settlements', 1);
        $this->assertDatabaseHas('position_settlements', ['amount' => 200, 'status' => 'won']);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_caf_report_with_ambiguous_scores_is_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake([CafMatchReport::URL => Http::response(
            '<time>Thursday, 12 November 2026</time><h2>DR Congo 3-1 Sierra Leone: Report</h2><h2>DR Congo 1-3 Sierra Leone: Report</h2>'
        )]);
        $this->expectExceptionMessage('Date ou score CAF absent ou ambigu.');
        app(CafMatchReport::class)->attest(CafMatchReport::EVENT, CafMatchReport::CONDITION);
    }

    public function test_scheduler_closes_betting_before_event_ends_without_inventing_a_result(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->place($user, $market);
        $this->travel(1)->minute();
        $this->artisan('markets:process')->assertSuccessful();
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'closed', 'result' => null]);
        $this->assertSame(1, DB::table('platform_notifications')->where('market_id', $market)->count());
        $this->actingAs($user)->postJson("/markets/$market/positions", [
            'outcome' => 'yes', 'amount' => 100, 'reference' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->travel(1)->minute();
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'pending_resolution', 'result' => null]);
        $this->assertDatabaseHas('positions', ['market_id' => $market, 'status' => 'open']);
    }

    public static function results(): array
    {
        return ['oui' => ['A', 'final', 'yes', 'won', 'lost', 200],
            'non' => ['B', 'final', 'no', 'lost', 'won', 200],
            'annulation' => ['', 'cancelled', 'void', 'refunded', 'refunded', 100]];
    }

    #[DataProvider('results')]
    public function test_verified_provider_result_is_reconciled_once(string $value, string $state, string $result, string $yes, string $no, int $amount): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $market = $this->market($a, ['one', 'two']);
        $this->place($a, $market);
        $this->place($b, $market, 'no');
        $this->travel(3)->minutes();
        Http::preventStrayRequests();
        Http::fake(['*.test/*' => Http::response($this->attestation($value, $state))]);
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'result' => $result]);
        $this->assertDatabaseCount('position_settlements', 0);
        $this->artisan('markets:process')->assertSuccessful();
        $this->artisan('markets:process')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => $result === 'void' ? 'cancelled' : 'settled']);
        $this->assertDatabaseHas('positions', ['user_id' => $a->id, 'status' => $yes]);
        $this->assertDatabaseHas('positions', ['user_id' => $b->id, 'status' => $no]);
        $this->assertDatabaseCount('position_settlements', 2);
        $this->assertSame($amount * ($result === 'void' ? 2 : 1), (int) DB::table('position_settlements')->sum('amount'));
        $this->assertSame($result === 'void' ? 2 : 1, DB::table('platform_notifications')->where(fn ($q) => $q
            ->where('message', 'like', 'Gain :%')->orWhere('message', 'like', 'Remboursement :%'))->count());
        $this->assertDatabaseHas('market_transitions', ['market_id' => $market, 'to_status' => $result === 'void' ? 'settling' : 'resolved']);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_provider_failure_backs_off_then_contradiction_freezes_automatic_processing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $market = $this->market($admin, ['one', 'two']);
        $this->travel(3)->minutes();
        Http::preventStrayRequests();
        $unavailable = true;
        Http::fake([
            'one.test/*' => function () use (&$unavailable) {
                return $unavailable ? Http::response([], 503) : Http::response($this->attestation('A'));
            },
            'two.test/*' => fn () => Http::response($this->attestation('B')),
        ]);
        $this->artisan('markets:process')->assertSuccessful();
        $this->artisan('markets:process')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'pending_resolution',
            'processing_failures' => 1, 'processing_error' => 'verification_failed', 'result' => null]);
        $this->travel(3)->minutes();
        $unavailable = false;
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'disputed', 'result' => null]);
        $this->assertDatabaseCount('position_settlements', 0);
        $this->actingAs($user)->postJson("/admin/markets/$market/review-resolution", ['action' => 'retry', 'reason' => 'Vérifier la source officielle'])->assertForbidden();
        $this->actingAs($admin)->postJson("/admin/markets/$market/review-resolution", ['action' => 'retry', 'reason' => 'Vérifier la source officielle'])->assertRedirect();
        $this->assertDatabaseHas('market_transitions', ['market_id' => $market, 'actor_id' => $admin->id, 'to_status' => 'pending_resolution']);
    }

    public function test_result_waits_for_matching_fresh_attestations(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user, ['one', 'two']);
        $this->travel(3)->minutes();
        Http::preventStrayRequests();
        Http::fake([
            'one.test/*' => fn () => Http::response($this->attestation('A')),
            'two.test/*' => Http::sequence()->push(['event' => 'finale-1', 'condition' => 'vainqueur-temps-reglementaire', 'status' => 'postponed'])
                ->push([...$this->attestation('A'), 'verified_at' => now()->subHour()->format('Y-m-d\TH:i:s\Z')]),
        ]);
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'pending_resolution', 'result' => null, 'processing_error' => null]);
        $this->travel(16)->minutes();
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'pending_resolution', 'result' => null, 'processing_error' => 'verification_failed']);
        $this->assertDatabaseCount('position_settlements', 0);
    }

    public function test_historical_terminal_market_with_open_positions_is_reported_without_automatic_credit(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->place($user, $market);
        DB::table('markets')->where('id', $market)->update(['status' => 'resolved', 'result' => 'yes']);
        $this->travel(3)->minutes();
        $this->artisan('markets:process')->assertSuccessful();
        $this->artisan('markets:audit')->assertExitCode(1);
        $this->assertDatabaseHas('positions', ['market_id' => $market, 'status' => 'open']);
        $this->assertDatabaseCount('position_settlements', 0);
        $this->assertDatabaseMissing('ledger_transactions', ['kind' => 'Gain']);
    }

    public function test_committed_batch_survives_failure_and_only_open_positions_resume(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $market = $this->market($admin);
        $users = User::factory()->count(105)->create();
        foreach ($users as $user) {
            $this->place($user, $market);
        }
        $this->travel(3)->minutes();
        config(['queue.default' => 'database']);
        $this->actingAs($admin)->postJson("/admin/markets/$market/resolve", [
            'result' => 'yes', 'source' => 'Source officielle vérifiée par l’administrateur.',
        ])->assertRedirect();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'resolved']);
        $this->artisan('markets:process')->assertSuccessful();
        $this->assertDatabaseHas('jobs', ['queue' => 'markets']);
        $this->artisan('queue:work', ['--queue' => 'markets', '--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'settling']);
        $this->assertDatabaseCount('position_settlements', 100);
        $ledger = \Mockery::mock(Ledger::class)->makePartial();
        $ledger->shouldReceive('transfer')->once()->andThrow(new \RuntimeException('Interruption simulée'));
        (new ProcessMarket($market))->handle(app(OfficialResult::class), new ResolveMarket($ledger));
        $this->assertDatabaseCount('position_settlements', 100);
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'settling', 'processing_error' => 'settlement_failed']);
        $this->travel(3)->minutes();
        (new ProcessMarket($market))->handle(app(OfficialResult::class), app(ResolveMarket::class));
        (new ProcessMarket($market))->handle(app(OfficialResult::class), app(ResolveMarket::class));
        $this->assertDatabaseCount('position_settlements', 105);
        $this->assertSame(105, DB::table('ledger_transactions')->where('kind', 'Gain')->count());
        $this->assertSame(105, DB::table('platform_notifications')->where('message', 'like', 'Gain :%')->count());
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'settled', 'processing_error' => null]);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }
}
