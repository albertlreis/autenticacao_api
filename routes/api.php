<?php

use App\Http\Controllers\MonitoramentoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\PermissaoController;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Rotas Públicas
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Rotas Protegidas por Autenticação (Sanctum)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Rotas básicas que não exigem cache de permissões
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Rotas que Exigem Permissões em Cache
        |--------------------------------------------------------------------------
        */
        Route::middleware('cache.permissoes')->group(function () {

            // CRUD de usuários
            Route::apiResource('usuarios', UsuarioController::class);

            // CRUD de perfis
            Route::apiResource('perfis', PerfilController::class);

            // CRUD de permissões
            Route::apiResource('permissoes', PermissaoController::class);

            // Associação de perfis a usuários
            Route::post('/usuarios/{usuario}/perfis', [UsuarioController::class, 'assignPerfil']);
            Route::delete('/usuarios/{usuario}/perfis/{perfil}', [UsuarioController::class, 'removePerfil']);

            // Associação de permissões a perfis
            Route::post('/perfis/{perfil}/permissoes', [PerfilController::class, 'assignPermissao']);
            Route::delete('/perfis/{perfil}/permissoes/{permissao}', [PerfilController::class, 'removePermissao']);

            Route::get('/monitoramento/cache', [MonitoramentoController::class, 'index']);

        });
    });
});
