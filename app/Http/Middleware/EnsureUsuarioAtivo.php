<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUsuarioAtivo
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        if (property_exists($user, 'ativo') && !$user->ativo) {
            return response()->json(['message' => 'Usuário inativo'], 403);
        }

        return $next($request);
    }
}
