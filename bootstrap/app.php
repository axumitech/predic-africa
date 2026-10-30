<?php

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/dashboard');
        $middleware->preventRequestForgery(except: ['webhooks/ndsgpay']);
        $middleware->web(append: [ActiveAccount::class, \App\Modules\Infrastructure\Maintenance::class, HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $exception, $request) {
            $status = $response->getStatusCode();
            $response->headers->set('Cache-Control', 'private, no-store');
            if ($request->expectsJson()) {
                return $response;
            }
            if ($status === 422 && ! $request->isMethod('GET')) {
                return back()->withErrors(['operation' => $exception->getMessage() ?: 'Opération refusée. Vérifiez les informations.'])->header('Cache-Control', 'private, no-store');
            }
            if (in_array($status, [403, 404, 419, 429, 500, 503])) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status)->header('Cache-Control', 'private, no-store');
            }

            return $response;
        });
    })->create();
