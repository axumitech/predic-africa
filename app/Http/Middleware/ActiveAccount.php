<?php

namespace App\Http\Middleware;

use App\Modules\Identity\SessionAccess;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($authenticated = $request->user()) {
            // Re-read permissions, including for public pages with shared props.
            $user = $authenticated->fresh();
            if (! $user) {
                SessionAccess::revokeCurrent($request);
                throw new AuthenticationException;
            }
            Auth::setUser($user);
            if ($user->suspended || ! in_array($user->role, ['trader', 'admin'], true)) {
                SessionAccess::revokeCurrent($request);
                abort(403, 'Accès suspendu ou rôle non autorisé. Contactez le support.');
            }
            // Existing sessions predate versioning and belong to version zero.
            // Never initialize a missing version from the current user record:
            // doing so would revive an old session after suspension/reactivation.
            if ((int) $request->session()->get('auth_version', 0) !== $user->auth_version) {
                SessionAccess::revokeCurrent($request);
                throw new AuthenticationException;
            }
        }

        return $next($request);
    }
}
