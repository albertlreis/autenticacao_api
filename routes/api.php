<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\PermissaoController;
use App\Http\Controllers\MonitoramentoController;

Route::prefix('v1')->group(function () {

    /* ============================================================
     * PÚBLICO / AUTH
     * ============================================================ */
//    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
//    });

    /* ============================================================
     * PROTEGIDO (SANCTUM)
     * ============================================================ */
    Route::middleware('auth:sanctum')->group(function () {

        // Sessão do usuário autenticado (não depende de cache de permissões)
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        /* ============================================================
         * ROTAS COM PERMISSÕES EM CACHE
         * ============================================================ */
        Route::middleware('cache.permissoes')->group(function () {

            // =========================
            // USUÁRIOS
            // =========================
            Route::apiResource('usuarios', UsuarioController::class)
                ->parameters(['usuarios' => 'usuario'])
                ->whereNumber('usuario')
                ->except(['create', 'edit']);

            // associação usuário <-> perfis
            Route::post('usuarios/{usuario}/perfis', [UsuarioController::class, 'assignPerfil'])
                ->whereNumber('usuario');

            Route::delete('usuarios/{usuario}/perfis/{perfil}', [UsuarioController::class, 'removePerfil'])
                ->whereNumber(['usuario', 'perfil']);

            // =========================
            // PERFIS
            // =========================
            Route::apiResource('perfis', PerfilController::class)
                ->parameters(['perfis' => 'perfil'])
                ->whereNumber('perfil')
                ->except(['create', 'edit']);

            // associação perfil <-> permissões
            Route::post('perfis/{perfil}/permissoes', [PerfilController::class, 'assignPermissao'])
                ->whereNumber('perfil');

            Route::delete('perfis/{perfil}/permissoes/{permissao}', [PerfilController::class, 'removePermissao'])
                ->whereNumber(['perfil', 'permissao']);

            // =========================
            // PERMISSÕES
            // =========================
            Route::apiResource('permissoes', PermissaoController::class)
                ->parameters(['permissoes' => 'permissao'])
                ->whereNumber('permissao')
                ->except(['create', 'edit']);

            // =========================
            // MONITORAMENTO
            // =========================
            Route::prefix('monitoramento')->group(function () {
                Route::get('cache', [MonitoramentoController::class, 'index']);
            });

        });
    });
});
