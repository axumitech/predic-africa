<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, \Closure $next)
    {
        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => $request->user() ? [
                ...$request->user()->only('id', 'name', 'email', 'role', 'country'),
                'country_name' => config('regions.countries')[$request->user()->country]['name'] ?? null,
                'wallet_currency' => DB::table('payment_wallets')->where('user_id', $request->user()->id)->value('currency') ?? 'XAF',
            ] : null,
            'balance' => fn () => $request->user() ? (int) DB::table('accounts')->where('user_id', $request->user()->id)->value('balance') : 0,
            'balance_exact' => fn () => $request->user() ? (string) (DB::table('accounts')->where('user_id', $request->user()->id)->value('balance') ?? 0) : '0',
            'fx' => fn () => app(\App\Modules\Money\RateBook::class)->view($request->user()
                ? (DB::table('payment_wallets')->where('user_id', $request->user()->id)->value('currency') ?? 'XAF') : 'XAF'),
            'unread' => fn () => $request->user() ? DB::table('platform_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->count() : 0,
            'flash' => fn () => ['success' => $request->session()->get('success')],
        ];
    }
}
