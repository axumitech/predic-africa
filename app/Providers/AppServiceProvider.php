<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->role === 'admin' && ! $user->suspended);
        Gate::define('view-market', fn (User $user, object $market) => ! $user->suspended && (
            in_array($market->status, ['open', 'resolved', 'void'], true)
            || (int) $market->creator_id === $user->id || Gate::forUser($user)->allows('admin')
        ));
        Gate::define('access-ticket', fn (User $user, object $ticket) => ! $user->suspended && (
            (int) $ticket->user_id === $user->id || Gate::forUser($user)->allows('admin')
        ));
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return [
                Limit::perMinute(6)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('account:'.hash('sha256', is_string($email) ? strtolower(trim($email)) : 'invalid')),
            ];
        });
    }
}
