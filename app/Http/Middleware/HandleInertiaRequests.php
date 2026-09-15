<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => $request->user()?->only('id', 'name', 'email', 'role'),
            'balance' => fn () => $request->user() ? (int) DB::table('accounts')->where('user_id', $request->user()->id)->value('balance') : 0,
            'unread' => fn () => $request->user() ? DB::table('platform_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->count() : 0,
            'flash' => fn () => ['success' => $request->session()->get('success')],
        ];
    }
}
