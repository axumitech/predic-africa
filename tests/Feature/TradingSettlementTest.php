<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Settlement\ResolveMarket;
use App\Modules\Trading\PlacePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TradingSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function market(User $user): int
    {
        return DB::table('markets')->insertGetId([
            'creator_id' => $user->id, 'title' => 'Marché de régression', 'description' => 'Règles de test explicites.',
            'category' => 'Sport', 'status' => 'open', 'closes_at' => now()->addDay(), 'yes_odds' => 235, 'no_odds' => 170,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fund(User $user, int $amount = 1000): void
    {
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', $amount, true));
    }

    private function place(User $user, int $market, string $outcome = 'yes', int $amount = 101, ?string $reference = null): void
    {
        app(PlacePosition::class)->place($user->id, $market, $outcome, $amount, $reference ?? (string) Str::uuid());
    }

    private function balance(User $user): int
    {
        return (int) DB::table('accounts')->where('user_id', $user->id)->value('balance');
    }

    public function test_missing_position_is_never_recreated_from_an_orphaned_debit_after_closure(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->fund($user);
        $reference = (string) Str::uuid();
        $this->place($user, $market, reference: $reference);
        DB::table('positions')->delete();
        DB::table('markets')->where('id', $market)->update(['status' => 'void', 'result' => 'void']);
        $this->actingAs($user)->postJson("/markets/$market/positions", ['amount' => 101, 'outcome' => 'yes', 'reference' => $reference])->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('positions', 0);
        $this->assertSame(899, $this->balance($user));
        $this->assertDatabaseCount('ledger_transactions', 2);
    }

    public function test_replay_after_refund_is_a_no_op_and_changed_intent_is_refused(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->fund($user);
        $reference = (string) Str::uuid();
        $this->place($user, $market, reference: $reference);
        app(ResolveMarket::class)->resolve($market, 'void', 'Annulation du scénario de test');
        $this->place($user, $market, reference: $reference);
        $this->assertSame(1000, $this->balance($user));
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseCount('ledger_transactions', 3);
        $this->actingAs($user)->postJson("/markets/$market/positions", ['amount' => 101, 'outcome' => 'no', 'reference' => $reference])->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseHas('positions', ['status' => 'refunded']);
    }

    public function test_exact_deadline_refuses_bets_and_allows_resolution_with_locked_odds(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $market = $this->market($admin);
        $this->fund($user);
        $this->place($user, $market);
        $deadline = now()->addHour()->startOfSecond();
        DB::table('markets')->where('id', $market)->update(['closes_at' => $deadline, 'yes_odds' => 1000]);
        $this->travelTo($deadline);
        $this->actingAs($user)->postJson("/markets/$market/positions", ['amount' => 100, 'outcome' => 'yes', 'reference' => (string) Str::uuid()])->assertUnprocessable();
        $this->actingAs($admin)->postJson("/admin/markets/$market/resolve", ['result' => 'yes', 'source' => 'Source publique de résolution'])->assertRedirect();
        $this->assertSame(1136, $this->balance($user)); // 1000 - 101 + floor(101 * 235 / 100)
        $this->assertDatabaseHas('positions', ['odds' => 235, 'payout' => 237, 'status' => 'won']);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_invalid_server_odds_roll_back_debit_and_notification(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->fund($user);
        DB::table('markets')->where('id', $market)->update(['yes_odds' => 0]);
        $this->actingAs($user)->postJson("/markets/$market/positions", ['amount' => 100, 'outcome' => 'yes', 'reference' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(1000, $this->balance($user));
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('platform_notifications', 1);
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_insertion_failure_rolls_back_the_entire_bet(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->fund($user);
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with($query, 'insert into "positions"')) {
                throw new \RuntimeException('Injected position failure');
            }
        });
        try {
            $this->place($user, $market);
            $this->fail('Failure expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected position failure', $exception->getMessage());
        }
        $this->assertSame(1000, $this->balance($user));
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('platform_notifications', 1);
    }

    public static function settlementResults(): array
    {
        return ['gain' => ['yes', 1136, 'Gain'], 'remboursement' => ['void', 1000, 'Remboursement']];
    }

    #[DataProvider('settlementResults')]
    public function test_failure_after_first_transfer_rolls_back_every_payout_and_can_be_retried(string $result, int $expected, string $kind): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $market = $this->market($first);
        foreach ([$first, $second] as $user) {
            $this->fund($user);
            $this->place($user, $market);
        }
        DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
        $calls = 0;
        $ledger = \Mockery::mock(Ledger::class)->makePartial();
        $ledger->shouldReceive('transfer')->andReturnUsing(function (...$arguments) use (&$calls) {
            if (++$calls === 2) {
                throw new \RuntimeException('Injected second payout failure');
            }

            return (new Ledger)->transfer(...$arguments);
        });
        try {
            (new ResolveMarket($ledger))->resolve($market, $result, 'Source publique de résolution');
            $this->fail('Failure expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected second payout failure', $exception->getMessage());
        }
        $this->assertSame(899, $this->balance($first));
        $this->assertSame(899, $this->balance($second));
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'open', 'result' => null]);
        $this->assertSame(2, DB::table('positions')->where('status', 'open')->count());
        $this->assertDatabaseCount('ledger_transactions', 4);
        $this->assertDatabaseCount('platform_notifications', 4);
        app(ResolveMarket::class)->resolve($market, $result, 'Source publique de résolution');
        app(ResolveMarket::class)->resolve($market, $result, 'Nouvelle source ignorée au rejeu');
        $this->assertSame($expected, $this->balance($first));
        $this->assertSame($expected, $this->balance($second));
        $this->assertSame(2, DB::table('ledger_transactions')->where('kind', $kind)->count());
        $this->assertDatabaseHas('markets', ['resolution_source' => 'Source publique de résolution']);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_void_refunds_both_outcomes_and_rejects_later_resolution(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $market = $this->market($admin);
        $this->fund($user);
        $this->place($user, $market, 'yes', 101);
        $this->place($user, $market, 'no', 103);
        app(ResolveMarket::class)->resolve($market, 'void', 'Annulation publique du marché');
        app(ResolveMarket::class)->resolve($market, 'void', 'Annulation publique du marché');
        $this->assertSame(1000, $this->balance($user));
        $this->assertSame(2, DB::table('positions')->where('status', 'refunded')->count());
        $this->assertSame(2, DB::table('ledger_transactions')->where('kind', 'Remboursement')->count());
        $this->actingAs($admin)->postJson("/admin/markets/$market/resolve", ['result' => 'yes', 'source' => 'Source de résultat contradictoire'])->assertUnprocessable();
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_corrupted_payout_or_debit_prevents_settlement_without_partial_credits(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $market = $this->market($admin);
        $this->fund($user);
        $this->place($user, $market);
        DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
        DB::table('positions')->update(['payout' => 999999]);
        $this->actingAs($admin)->postJson("/admin/markets/$market/resolve", ['result' => 'yes', 'source' => 'Source publique de résolution'])->assertUnprocessable();
        DB::table('positions')->update(['payout' => 237]);
        DB::table('ledger_transactions')->where('kind', 'Mise')->update(['fingerprint' => 'corrupted']);
        $this->postJson("/admin/markets/$market/resolve", ['result' => 'void', 'source' => 'Annulation publique du marché'])->assertUnprocessable();
        $this->assertSame(899, $this->balance($user));
        $this->assertDatabaseHas('markets', ['id' => $market, 'status' => 'open']);
        $this->assertDatabaseHas('positions', ['status' => 'open']);
        $this->assertDatabaseCount('ledger_transactions', 2);
    }

    public function test_suspended_user_is_rechecked_inside_the_application_operation(): void
    {
        $user = User::factory()->create();
        $market = $this->market($user);
        $this->fund($user);
        DB::table('users')->where('id', $user->id)->update(['suspended' => true]);
        try {
            $this->place($user, $market);
            $this->fail('Suspended user accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(1000, $this->balance($user));
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_inconsistent_terminal_states_and_blank_source_are_refused(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $market = $this->market($admin);
        $this->fund($user);
        $this->place($user, $market);
        $this->actingAs($admin)->postJson("/admin/markets/$market/resolve", ['result' => 'void', 'source' => str_repeat(' ', 20)])->assertUnprocessable();
        DB::table('positions')->update(['status' => 'won']);
        $this->postJson("/admin/markets/$market/resolve", ['result' => 'void', 'source' => 'Annulation publique du marché'])->assertUnprocessable();
        DB::table('positions')->update(['status' => 'open']);
        DB::table('markets')->where('id', $market)->update(['status' => 'void', 'result' => 'void']);
        $this->postJson("/admin/markets/$market/resolve", ['result' => 'void', 'source' => 'Annulation publique du marché'])->assertUnprocessable();
        $this->assertSame(899, $this->balance($user));
        $this->assertDatabaseCount('ledger_transactions', 2);
    }
}
