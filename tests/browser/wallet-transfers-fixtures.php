<?php

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-fx-transfers-') || app()->configurationIsCached()) {
    throw new RuntimeException('An isolated transfer testing database is required.');
}
$user = User::factory()->create(['email' => 'transfers@example.test', 'password' => 'transfer-password-123', 'country' => null, 'phone' => '+24177000000']);
DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 5000, true));
// Regression: a historical ledger account can exist before its wallet.
DB::table('accounts')->insert(['user_id' => $user->id, 'code' => "historical-cdf:$user->id", 'currency' => 'CDF', 'balance' => 0]);
DB::table('fx_snapshots')->insert(['provider' => config('fx.provider'), 'base_currency' => 'XAF',
    'rates' => json_encode(['XAF' => '1', 'CDF' => '4']), 'as_of' => now(), 'fetched_at' => now()]);

DB::table('markets')->insert(['creator_id' => $user->id, 'title' => 'Marché de test du portefeuille actif',
    'description' => 'Un marché isolé pour vérifier les devises affichées.', 'category' => 'Sport', 'status' => 'open',
    'yes_odds' => 200, 'no_odds' => 200, 'closes_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
