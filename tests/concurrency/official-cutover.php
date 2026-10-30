<?php

// Migration regression fixture. Always uses a fresh in-memory SQLite database.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
putenv('APP_CONFIG_CACHE=/private/tmp/predic-cutover-unused-config.php');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
DB::purge('sqlite');
$paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_contains($path, '000003_retire_demo')));
if (Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Isolated old-schema migrations failed.');
}
$user = User::factory()->create();
$account = DB::table('accounts')->insertGetId(['code' => 'user:'.$user->id, 'user_id' => $user->id, 'balance' => 9000]);
$bank = DB::table('accounts')->insertGetId(['code' => 'simulation', 'balance' => -9000]);
$old = DB::table('ledger_transactions')->insertGetId(['user_id' => $user->id, 'reference' => (string) Str::uuid(), 'kind' => 'Dépôt simulé', 'amount' => 9000, 'fingerprint' => 'legacy', 'created_at' => now()]);
DB::table('ledger_entries')->insert([['transaction_id' => $old, 'account_id' => $account, 'amount' => 9000], ['transaction_id' => $old, 'account_id' => $bank, 'amount' => -9000]]);
DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 1000]);
$payment = DB::table('payments')->insertGetId(['user_id' => $user->id, 'reference' => (string) Str::uuid(), 'kind' => 'deposit', 'amount' => 1000, 'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '[encrypted]', 'local_state' => 'completed', 'status' => 'COMPLETED', 'created_at' => now(), 'updated_at' => now()]);
DB::table('payment_movements')->insert(['payment_id' => $payment, 'effect' => 'credit', 'amount' => 1000, 'created_at' => now()]);
DB::table('markets')->insert(['creator_id' => $user->id, 'title' => 'Démo : scénario à retirer', 'description' => 'Ancien scénario', 'category' => 'Sport', 'status' => 'open', 'closes_at' => now()->addDay(), 'yes_odds' => 200, 'no_odds' => 200, 'created_at' => now(), 'updated_at' => now()]);
$migration = require database_path('migrations/2026_10_29_000003_retire_demo_and_unify_wallet.php');
$migration->up();
if ((int) DB::table('accounts')->where('user_id', $user->id)->value('balance') !== 1000
    || (int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') !== 1000
    || DB::table('retired_records')->where('source', 'ledger_transactions')->count() !== 1
    || DB::table('markets')->count() !== 0 || DB::table('positions')->count() !== 0
    || DB::table('ledger_transactions')->where('kind', 'Dépôt simulé')->exists()
    || Artisan::call('ledger:reconcile') !== 0 || Artisan::call('payments:audit') !== 0) {
    throw new RuntimeException('Cutover lost real funds, converted synthetic funds, or corrupted financial history.');
}
echo "PASS cutover: 9000 synthetic credits archived; 1000 real XAF preserved; both journals reconcile.\n";
