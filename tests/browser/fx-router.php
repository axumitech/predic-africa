<?php

// Test-only PHP router. All provider traffic is intercepted inside the isolated app.
use App\Modules\Money\Money;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (getenv('APP_ENV') !== 'testing' || ! str_contains(getenv('DB_DATABASE') ?: '', 'predic-e2e-fx-')) {
    throw new RuntimeException('FX router requires explicit isolated test environment.');
}
if ($path !== '/' && is_file(__DIR__.'/../../public/'.$path)) return false;
$app->afterBootstrapping(BootProviders::class, function () use ($app) {
    config(['inertia.ssr.enabled' => false]);
    if (! app()->environment('testing') || config('database.default') !== 'sqlite'
        || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-fx-') || app()->configurationIsCached()) {
        throw new RuntimeException('FX test router cannot run against an application database.');
    }
    Http::preventStrayRequests();
    Http::fake([
        'https://ndsgpay-backend.vercel.app/api/pay/deposits' => function ($request) {
            $payment = DB::table('payments')->where('local_state', 'initiating')->latest('id')->firstOrFail();
            if ($request['currency'] !== 'CDF' || $request['amount'] !== '1234.56') throw new RuntimeException('Unexpected test payment.');
            return Http::response(['success' => true, 'depositId' => 'fx-deposit-'.$payment->id, 'status' => 'INITIATED']);
        },
        'https://ndsgpay-backend.vercel.app/api/deposits/*' => function ($request) {
            $id = basename($request->url());
            $payment = DB::table('payments')->where('provider_id', $id)->firstOrFail();
            return Http::response('{"success":true,"deposit":{"depositId":"'.$id.'","status":"COMPLETED","currency":"CDF","amount":'.Money::decimal((int) $payment->amount, 'CDF').'}}', 200, ['Content-Type' => 'application/json']);
        },
    ]);
});
$app->handleRequest(Request::capture());
