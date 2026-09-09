<?php

use App\Saas\PlatformController;
use App\Saas\PlatformAuth;
use Illuminate\Support\Facades\Route;

if (config('saas.enabled')) {
    Route::prefix('v1/platform')->withoutMiddleware('throttle:api')->group(function () {
        Route::post('login', [PlatformController::class, 'login']);
        Route::middleware(PlatformAuth::class)->group(function () {
            Route::post('logout', [PlatformController::class, 'logout']);
            Route::get('tenants', [PlatformController::class, 'index']);
            Route::post('tenants', [PlatformController::class, 'store']);
            Route::patch('tenants/{id}', [PlatformController::class, 'update']);
            Route::post('tenants/{id}/provision', [PlatformController::class, 'provision']);
            Route::get('tenants/{id}/events', [PlatformController::class, 'events']);
        });
    });
}
