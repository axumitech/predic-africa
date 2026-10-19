<?php

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class SessionAccess
{
    public static function establish(Request $request, User $user): void
    {
        $request->session()->put('auth_version', $user->auth_version);
    }

    public static function revokeCurrent(Request $request): void
    {
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();
    }
}
