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
                'wallet_currency' => \App\Modules\Money\Wallets::currency($request->user()->id),
            ] : null,
            'wallets' => fn () => $request->user() ? DB::table('payment_wallets')->where('user_id', $request->user()->id)->orderBy('id')->get()->map(function ($wallet) { $wallet->balance = (string) $wallet->balance; return $wallet; }) : [],
            'walletCurrencies' => array_keys(\App\Modules\Money\Money::SCALES),
            'balance' => fn () => $request->user() ? (int) DB::table('accounts')->where('user_id', $request->user()->id)->where('currency', \App\Modules\Money\Wallets::currency($request->user()->id))->value('balance') : 0,
            'balance_exact' => fn () => $request->user() ? (string) (DB::table('accounts')->where('user_id', $request->user()->id)->where('currency', \App\Modules\Money\Wallets::currency($request->user()->id))->value('balance') ?? 0) : '0',
            'fx' => fn () => app(\App\Modules\Money\RateBook::class)->view($request->user()
                ? (\App\Modules\Money\Wallets::currency($request->user()->id)) : 'XAF'),
            'unread' => fn () => $request->user() ? DB::table('platform_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->count() : 0,
            'flash' => fn () => ['success' => $request->session()->get('success')],
        ];
    }
}
