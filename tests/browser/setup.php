<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Invoked by platform.mjs against its own temporary SQLite database only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-')) {
    throw new RuntimeException('This fixture requires the isolated E2E database.');
}
$user = User::create(['name' => 'Admin E2E', 'email' => 'admin@example.test', 'password' => 'e2e-password-123']);
$user->role = 'admin';
$user->save();
