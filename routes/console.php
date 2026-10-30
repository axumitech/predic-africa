<?php

use App\Models\User;
use App\Modules\Money\RateSync;
use App\Modules\Money\RateBook;
use App\Modules\Money\Money;
use App\Modules\Payments\Capabilities;
use App\Modules\Payments\Ndsgpay;
use App\Modules\Payments\Operations;
use App\Modules\Payments\Payments;
use App\Modules\Payments\Recovery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fx:refresh', function (RateSync $sync) {
    $ok = $sync->refresh();
    $this->line($ok ? 'Taux de change actualisés.' : 'Aucune actualisation : clé absente, délai de reprise ou source indisponible.');
    return $ok ? 0 : 1;
})->purpose('Récupérer les taux CurrencyAPI sans afficher la clé ni inventer un taux de secours');
// Hourly wakeup; RateSync permits only one provider request per 24 hours.
Schedule::command('fx:refresh')->hourly()->withoutOverlapping()->when(fn () => config('fx.refresh_enabled') && filled(config('fx.key')) && app(\App\Modules\Infrastructure\Settings::class)->current()['fx_refresh_enabled']);

Artisan::command('fx:status', function (RateBook $book) {
    $this->line('currencyapi_key: '.(config('fx.key') ? 'PRESENT' : 'MISSING'));
    foreach (array_keys(Money::SCALES) as $currency) {
        $rate = $book->view($currency);
        $this->line($currency.': '.($rate['available'] ? 'FRESH' : 'UNAVAILABLE').' · '.($rate['as_of'] ?? 'aucun taux'));
    }
    $this->line('last_error: '.(DB::table('fx_sync_state')->where('id', 1)->value('last_error') ?? 'none'));
})->purpose('Contrôler la fraîcheur réelle des taux sans afficher de secret');

Artisan::command('platform:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Créez le compte via /register avant de lui attribuer le rôle administrateur.');

        return 1;
    }
    if ($user->isSuperadmin()) { $this->error('Superadmin protégé.'); return 1; }
    $user->role = 'admin';
    $user->save();
    $this->info('Rôle administrateur attribué.');
})->purpose('Promouvoir un compte existant en administrateur');

Artisan::command('payments:reconcile', function (Recovery $recovery) {
    $this->info('Travaux de reprise programmés : '.$recovery->dispatch());
})->purpose('Reprendre inbox, outbox et consultations NDSGPay sans réémettre de création');

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('payments:attach {reference} {providerId} {--actor=} {--case=}', function (Operations $operations) {
    try {
        $operations->attach($this->argument('reference'), $this->argument('providerId'), (int) $this->option('actor'), (string) $this->option('case'));
        $this->info('Association auditée. La confirmation sera vérifiée par le rapprochement.');
    } catch (Throwable $exception) {
        $this->error('Association refusée ou fournisseur indisponible. Aucun détail sensible affiché.');

        return 1;
    }
})->purpose('Associer une opération incertaine après investigation opérateur et preuve de propriété');

Artisan::command('payments:health', function () {
    $checks = [
        'credentials_present' => app(Ndsgpay::class)->ready(),
        'confirmed_capabilities' => count(app(Capabilities::class)->enabled()) > 0,
        'async_queue' => ! in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background']),
        'production_debug_disabled' => ! app()->isProduction() || ! config('app.debug'),
        'public_https_url' => str_starts_with(config('app.url'), 'https://'),
    ];
    foreach ($checks as $name => $ok) {
        $this->line($name.': '.($ok ? 'OK' : 'MISSING'));
    }
    $open = DB::table('payment_anomalies')->whereNull('resolved_at')->count();
    $this->line('open_anomalies: '.$open);
    $this->line('pending_inbox: '.DB::table('payment_inbox')->whereNull('processed_at')->count());
    $this->line('pending_outbox: '.DB::table('payment_outbox')->whereNull('delivered_at')->count());

    return in_array(false, $checks, true) || $open ? 1 : 0;
})->purpose('Contrôler la configuration NDSGPay sans afficher de secret ni appeler le fournisseur');

Artisan::command('payments:audit', function (Payments $payments) {
    $mismatches = 0;
    DB::table('payment_wallets as w')->leftJoin('payment_movements as m', 'm.user_id', '=', 'w.user_id')
        ->select('w.id', 'w.balance')->selectRaw('COALESCE(SUM(m.amount), 0) as journal_balance')
        ->groupBy('w.id', 'w.balance')->orderBy('w.id')->chunk(100, function ($rows) use ($payments, &$mismatches) {
            foreach ($rows as $row) {
                if ((string) $row->balance !== (string) $row->journal_balance) {
                    $mismatches++;
                    $payments->anomaly(null, 'wallet_journal_mismatch', (string) $row->id);
                }
            }
        });
    $this->info('Écarts portefeuille/journal : '.$mismatches);

    return $mismatches ? 1 : 0;
})->purpose('Vérifier les soldes réels contre le journal sans modifier les fonds');

Schedule::command('payments:audit')->hourly()->withoutOverlapping();

Artisan::command('payments:accept-late-deposit {reference} {--actor=} {--case=}', function (Payments $payments) {
    try {
        $payments->acceptLateDeposit($this->argument('reference'), (int) $this->option('actor'), (string) $this->option('case'));
        $this->info('Succès tardif vérifié et crédit audité appliqué.');
    } catch (Throwable $exception) {
        $this->error('Résolution refusée ou vérification indisponible. Consulter les anomalies ; aucun détail sensible affiché.');

        return 1;
    }
})->purpose('Résoudre explicitement un dépôt FAILED devenu COMPLETED après investigation opérateur');

Schedule::call(fn () => \App\Modules\Infrastructure\Settings::heartbeat('scheduler'))->everyMinute()->evenInMaintenanceMode();

Artisan::command('platform:superadmin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user || $user->suspended) {
        $this->error('Créez et activez ce compte via /register avant sa promotion.');
        return 1;
    }
    DB::transaction(function () use ($user) {
        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        if ($locked->role === 'superadmin') return;
        DB::table('infrastructure_audit')->insert(['action' => 'role.superadmin.granted',
            'before' => json_encode(['user_id' => $locked->id, 'role' => $locked->role]),
            'after' => json_encode(['user_id' => $locked->id, 'role' => 'superadmin']),
            'reason' => 'Promotion explicite par opérateur via CLI', 'created_at' => now()]);
        $locked->role = 'superadmin';
        $locked->auth_version++;
        $locked->remember_token = \Illuminate\Support\Str::random(60);
        $locked->save();
    }, 3);
    $this->info('Superadmin attribué. Une nouvelle connexion est requise.');
});
