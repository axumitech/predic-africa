<?php

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-fx-') || app()->configurationIsCached()) {
    throw new RuntimeException('FX fixtures require their own isolated temporary testing database.');
}
$action = $argv[1] ?? 'seed';
if ($action === 'close') {
    DB::table('markets')->where('title', 'Marché du test FX isolé')->update(['closes_at' => now()->subSecond()]);
    exit;
}
if ($action === 'stale') {
    DB::table('fx_snapshots')->update(['as_of' => now()->subSeconds(config('fx.max_age_seconds') + 1)]);
    exit;
}
if ($action === 'assert') {
    $user = User::where('email', 'fx-cdf@example.test')->firstOrFail();
    if ((int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') !== 177457
        || DB::table('payments')->count() !== 1 || DB::table('positions')->count() !== 1
        || DB::table('ledger_transactions')->count() !== 3) throw new RuntimeException('FX E2E accounting mismatch.');
    exit;
}
$admin = User::factory()->create(['name' => 'Admin FX', 'email' => 'fx-admin@example.test', 'password' => 'fx-password-123', 'role' => 'admin', 'country' => 'GAB']);
$user = User::factory()->create(['name' => 'Compte RDC', 'email' => 'fx-cdf@example.test', 'password' => 'fx-password-123', 'country' => 'COD']);
DB::table('accounts')->insert(['user_id' => $user->id, 'code' => 'user:'.$user->id, 'currency' => 'CDF', 'balance' => 0]);
DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
$market = DB::table('markets')->insertGetId(['creator_id' => $admin->id, 'title' => 'Marché du test FX isolé',
    'description' => 'Source de test isolée pour valider le règlement multidevise.', 'category' => 'Sport', 'status' => 'open',
    'closes_at' => now()->addHour(), 'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now()]);
// Deliberately fictitious rates, confined to this temporary test database.
DB::table('fx_snapshots')->insert(['provider' => 'currencyapi', 'base_currency' => 'XAF',
    'rates' => json_encode(['XAF' => '1', 'CDF' => '4', 'XOF' => '1', 'KES' => '0.25', 'RWF' => '2', 'UGX' => '7', 'ZMW' => '0.05', 'SLE' => '0.04']),
    'as_of' => now(), 'fetched_at' => now()]);
echo json_encode(['market' => $market, 'user' => $user->id, 'admin' => $admin->id]);
