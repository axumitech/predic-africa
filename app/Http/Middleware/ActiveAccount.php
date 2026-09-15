<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->suspended, 403, 'Compte suspendu. Contactez le support.');

        return $next($request);
    }
}
