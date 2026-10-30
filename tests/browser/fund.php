<?php

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-')) {
    throw new RuntimeException('Funding fixture requires the isolated browser-test database.');
}
$user = User::where('email', $argv[1] ?? '')->firstOrFail();
DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Fixture test', 1000, true));
