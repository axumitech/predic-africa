<?php

use App\Http\Controllers\PlatformController;
use App\Http\Middleware\ActiveAccount;
use App\Modules\Identity\Http\IdentityController;
use App\Modules\Markets\Http\MarketController;
use App\Modules\Payments\Http\PaymentController;
use App\Modules\Predictions\Http\PipelineAdminController;
use App\Modules\Predictions\Http\PredictionController;
use App\Modules\Settlement\Http\SettlementController;
use App\Modules\Support\Http\SupportController;
use App\Modules\Trading\Http\TradingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Platform', ['page' => 'home']))->name('home');
Route::get('/demo', fn () => Inertia::render('Prototype', ['mode' => 'demo']));
foreach (['terms', 'privacy', 'data', 'help'] as $page) {
    Route::get('/'.$page, fn () => Inertia::render('Platform', ['page' => $page]));
}
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('Platform', ['page' => 'register']));
    Route::post('/login', [IdentityController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', [IdentityController::class, 'register'])->middleware('throttle:6,1');
});
Route::post('/logout', [IdentityController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', ActiveAccount::class])->group(function () {
    foreach (['markets', 'positions', 'transactions', 'wallet', 'creator', 'support', 'notifications', 'profile', 'predictions', 'admin', 'admin/markets', 'admin/users', 'admin/pipeline'] as $path) {
        Route::get('/'.$path, fn (Request $request) => app(PlatformController::class)->page($request, str_replace('/', '-', $path)));
    }
    Route::get('/markets/{id}', fn (Request $request, int $id) => app(PlatformController::class)->page($request, 'market', $id))->whereNumber('id');
    Route::get('/support/{id}', fn (Request $request, int $id) => app(PlatformController::class)->page($request, 'ticket', $id))->whereNumber('id');
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/markets', [MarketController::class, 'store']);
        Route::post('/markets/{id}/positions', [TradingController::class, 'store'])->whereNumber('id');
        Route::post('/wallet', [PaymentController::class, 'store']);
        Route::post('/support', [SupportController::class, 'store']);
        Route::post('/support/{id}', [SupportController::class, 'reply'])->whereNumber('id');
        Route::post('/notifications/read', [PlatformController::class, 'readNotifications']);
        Route::post('/profile', [IdentityController::class, 'profile']);
        Route::post('/admin/markets/{id}/moderate', [MarketController::class, 'moderate'])->whereNumber('id');
        Route::post('/admin/markets/{id}/resolve', [SettlementController::class, 'store'])->whereNumber('id');
        Route::post('/admin/pipeline/generate', [PipelineAdminController::class, 'generate'])->middleware('throttle:3,1');
        Route::post('/admin/pipeline/import', [PipelineAdminController::class, 'import']);
        Route::post('/admin/users/{id}', [IdentityController::class, 'suspend'])->whereNumber('id');
    });
});
Route::prefix('api')->middleware('throttle:60,1')->group(function () {
    Route::get('/predictions', [PredictionController::class, 'predictions']);
    Route::get('/markets', [PredictionController::class, 'markets']);
});
