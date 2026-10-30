<?php

namespace App\Modules\Infrastructure;

use Closure;
use Illuminate\Http\Request;

class Maintenance
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isSuperadmin()
            || $request->is('login', 'logout', 'superadmin', 'superadmin/*', 'webhooks/ndsgpay', 'up')) {
            return $next($request);
        }
        abort_if(app(Settings::class)->current()['app_maintenance'], 503, 'La plateforme est en maintenance. Réessayez plus tard.');
        return $next($request);
    }
}
