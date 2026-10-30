<?php

// Explicit isolated PostgreSQL database only. Never run against an application DB.
use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Money\PositionQuotes;
use App\Modules\Money\RateBook;
use App\Modules\Payments\Payments;
use App\Modules\Settlement\ResolveMarket;
use App\Modules\Trading\PlacePosition;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = getenv('PREDIC_TEST_PG_DATABASE') ?: '';
if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'pgsql'
    || ! preg_match('/\Apredic_activation_[a-f0-9]{16}\z/D', $expected)
    || DB::connection()->getDatabaseName() !== $expected
    || DB::selectOne('select current_database() as name')->name !== $expected) {
    throw new RuntimeException('Refusing to migrate or test a non-isolated PostgreSQL database.');
}
function verify(bool $value, string $message): void { if (! $value) throw new RuntimeException($message); }
Http::preventStrayRequests();
verify(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated PostgreSQL migrations failed.');
echo "PASS PostgreSQL isolated migrations\n";
$id = bin2hex(random_bytes(6));
$user = User::create(['name' => 'Validation CDF isolée', 'email' => "cdf-$id@example.invalid", 'password' => Str::random(32), 'country' => 'COD']);
DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
config(['payments.enabled' => true, 'payments.secret_key' => 'test-only', 'payments.webhook_secret' => 'test-hook',
    'payments.merchant_id' => null, 'payments.capabilities' => [['country' => 'COD', 'currency' => 'CDF',
        'correspondent' => 'AIRTEL_COD', 'calling_code' => '243', 'national_lengths' => [9]]]]);
$calls = 0;
Http::fake(['*/pay/deposits' => function ($request) use (&$calls) {
    $calls++;
    verify($request['amount'] === '1234.56' && $request['currency'] === 'CDF', 'Provider amount scaling mismatch.');
    return Http::response(['success' => true, 'depositId' => 'pg-isolated-1', 'status' => 'INITIATED']);
}, '*/deposits/pg-isolated-1' => Http::response('{"success":true,"deposit":{"depositId":"pg-isolated-1","amount":1234.56,"currency":"CDF","status":"COMPLETED"}}')]);
$intent = ['kind' => 'deposit', 'amount' => '1234.56', 'reference' => (string) Str::uuid(), 'correspondent' => 'AIRTEL_COD', 'msisdn' => '243810000000'];
$payment = app(Payments::class)->create($user->id, $intent);
app(Payments::class)->create($user->id, $intent);
verify($calls === 1, 'Duplicate provider initiation.');
$super = User::create(['name' => 'Superadmin isolé', 'email' => "super-$id@example.invalid", 'password' => 'isolated-control-password']);
$super->role = 'superadmin'; $super->save();
Illuminate\Support\Facades\Auth::setUser($super);
$settings = app(App\Modules\Infrastructure\Settings::class);
$control = app(App\Modules\Infrastructure\ControlController::class);
$change = [...$settings->current(), 'deposits_enabled' => false, 'current_password' => 'isolated-control-password', 'reason' => 'Validation PostgreSQL de la pause des dépôts'];
$controlRequest = Illuminate\Http\Request::create('/superadmin/settings', 'POST', $change);
$controlRequest->setUserResolver(fn () => $super);
$control->update($controlRequest, $settings);
verify(! $settings->acceptingDeposits() && $settings->current()['revision'] === 1, 'PostgreSQL control update failed.');
verify(DB::table('infrastructure_audit')->where('action', 'settings.updated')->count() === 1, 'Control update not audited.');
$raw = json_encode(['event' => 'deposit.updated', 'data' => ['depositId' => 'pg-isolated-1', 'status' => 'COMPLETED']]);
$timestamp = time();
$request = Illuminate\Http\Request::create('/webhooks/ndsgpay', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_NDSGPAY_SIGNATURE' => 't='.$timestamp.',sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, 'test-hook'),
], $raw);
$controller = app(App\Modules\Payments\Http\WebhookController::class);
verify($controller($request, app(App\Modules\Payments\WebhookSignature::class))->getStatusCode() === 200, 'Signed webhook without merchant ID rejected.');
verify((int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') === 0, 'Webhook credited without GET.');
$event = DB::table('payment_inbox')->where('provider_id', 'pg-isolated-1')->sole();
app(App\Modules\Payments\Inbox::class)->process($event->id);
app(Payments::class)->refresh($payment);
verify((int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') === 123456, 'CDF deposit mismatch.');
echo "PASS PostgreSQL superadmin settings, audit and confirmation while deposits paused\n";
echo "PASS PostgreSQL exact CDF credit / idempotent provider initiation and completion\n";
$market = DB::table('markets')->insertGetId(['creator_id' => $user->id, 'title' => 'Validation PostgreSQL isolée',
    'description' => 'Source de test hors production.', 'category' => 'Sport', 'status' => 'open',
    'closes_at' => now()->addHour(), 'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now()]);
DB::table('fx_snapshots')->insert(['provider' => 'currencyapi', 'base_currency' => 'XAF',
    'rates' => json_encode(['XAF' => '1', 'CDF' => '4']), 'as_of' => now(), 'fetched_at' => now()]);
$reference = (string) Str::uuid();
$quote = app(PositionQuotes::class)->create($user, $market, 'yes', '400.01', $reference);
app(PlacePosition::class)->place($user->id, $market, 'yes', '400.01', $reference, $quote->id);
app(PlacePosition::class)->place($user->id, $market, 'yes', '400.01', $reference, $quote->id);
$xaf = User::create(['name' => 'Validation XAF isolée', 'email' => "xaf-$id@example.invalid", 'password' => Str::random(32), 'country' => 'GAB']);
DB::transaction(fn () => app(Ledger::class)->transfer($xaf->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
app(PlacePosition::class)->place($xaf->id, $market, 'yes', 100, (string) Str::uuid());
DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
foreach (DB::table('fx_snapshots')->get() as $snapshot) {
    DB::table('fx_snapshots')->where('id', $snapshot->id)->update(['as_of' => now()->subSeconds(config('fx.max_age_seconds') + 1000 + $snapshot->id)]);
}
verify(! app(RateBook::class)->view('CDF')['available'], 'Stale rate accepted.');
app(ResolveMarket::class)->resolve($market, 'yes', 'Résultat du scénario isolé PostgreSQL');
app(ResolveMarket::class)->resolve($market, 'yes', 'Rejeu du résultat isolé PostgreSQL');
verify((int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') === 177457, 'CDF settlement mismatch.');
verify((int) DB::table('payment_wallets')->where('user_id', $xaf->id)->value('balance') === 1135, 'XAF settlement mismatch.');
verify(Artisan::call('ledger:reconcile') === 0, 'PostgreSQL ledger mismatch.');
verify(Artisan::call('payments:audit') === 0, 'PostgreSQL wallet mismatch.');
echo "PASS PostgreSQL mixed-currency placement, stale-rate settlement, replay and reconciliation\n";
