<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\PermissaoController;

/*
|--------------------------------------------------------------------------
| Rotas Públicas
|--------------------------------------------------------------------------
|
| Rotas para registro e login não requerem autenticação.
|
*/
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Rotas Protegidas por Autenticação (Sanctum)
|--------------------------------------------------------------------------
|
| Essas rotas requerem que o usuário esteja autenticado.
|
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // CRUD de usuários (gestão de acesso)
    Route::apiResource('usuarios', UsuarioController::class);

    // CRUD de perfis
    Route::apiResource('perfis', PerfilController::class);

    // CRUD de permissões
    Route::apiResource('permissoes', PermissaoController::class);

    /*
    |--------------------------------------------------------------------------
    | Rotas para Associações
    |--------------------------------------------------------------------------
    |
    | Essas rotas gerenciam as associações entre usuários e perfis, e entre
    | perfis e permissões.
    |
    */
    // Associação de perfis a um usuário
    Route::post('/usuarios/{usuario}/perfis', [UsuarioController::class, 'assignPerfil']);
    Route::delete('/usuarios/{usuario}/perfis/{perfil}', [UsuarioController::class, 'removePerfil']);

    // Associação de permissões a um perfil
    Route::post('/perfis/{perfil}/permissoes', [PerfilController::class, 'assignPermissao']);
    Route::delete('/perfis/{perfil}/permissoes/{permissao}', [PerfilController::class, 'removePermissao']);
});
