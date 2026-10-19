<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_is_part_of_idempotency_for_existing_transactions(): void
    {
        $user = User::factory()->create();
        $reference = (string) Str::uuid();
        $ledger = new Ledger;
        $id = DB::transaction(fn () => $ledger->transfer($user->id, $reference, 'Correction', 100, true));
        $this->assertSame($id, DB::transaction(fn () => $ledger->transfer($user->id, $reference, 'Correction', 100, true)));
        try {
            DB::transaction(fn () => $ledger->transfer($user->id, $reference, 'Correction', 100, false));
            $this->fail('Opposite direction accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reference', $exception->errors());
        }
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('platform_notifications', 1);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_outer_failure_rolls_back_accounts_entries_and_notifications(): void
    {
        $user = User::factory()->create();
        try {
            DB::transaction(function () use ($user) {
                (new Ledger)->transfer($user->id, (string) Str::uuid(), 'Dépôt simulé', 100, true);
                throw new \RuntimeException('Downstream failure');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Downstream failure', $exception->getMessage());
        }
        foreach (['accounts', 'ledger_transactions', 'ledger_entries', 'platform_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_overflow_is_rejected_before_sqlite_converts_balance_to_float(): void
    {
        $user = User::factory()->create();
        DB::table('accounts')->insert(['user_id' => $user->id, 'code' => "user:$user->id", 'balance' => PHP_INT_MAX]);
        try {
            DB::transaction(fn () => (new Ledger)->transfer($user->id, (string) Str::uuid(), 'Dépôt simulé', 100, true));
            $this->fail('Overflow accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(PHP_INT_MAX, DB::table('accounts')->where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_reconciliation_detects_offsetting_corruption_and_missing_entries(): void
    {
        $user = User::factory()->create();
        DB::transaction(fn () => (new Ledger)->transfer($user->id, (string) Str::uuid(), 'Dépôt simulé', 100, true));
        DB::table('accounts')->where('user_id', $user->id)->increment('balance', 10);
        DB::table('accounts')->where('code', 'simulation')->decrement('balance', 10);
        $this->artisan('ledger:reconcile')->expectsOutput('Comptes incohérents : 2 ; transactions déséquilibrées ou incomplètes : 0.')->assertFailed();
        DB::table('ledger_entries')->delete();
        $this->artisan('ledger:reconcile')->expectsOutput('Comptes incohérents : 2 ; transactions déséquilibrées ou incomplètes : 1.')->assertFailed();
    }
}
