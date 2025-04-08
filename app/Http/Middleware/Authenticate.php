<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Não redireciona, retornando nulo.
     */
    protected function redirectTo($request)
    {
        // Para APIs, não vamos redirecionar
        return null;
    }

    /**
     * Retorna uma resposta JSON para usuários não autenticados.
     */
    protected function unauthenticated($request, array $guards)
    {
        return response()->json([
            'error'   => true,
            'message' => 'Token não fornecido ou inválido.'
        ], 401);
    }
}
