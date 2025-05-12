<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class CachePermissoes
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $cacheKey = 'permissoes_usuario_' . $user->id;

            if (!Cache::has($cacheKey)) {
                $permissoes = $user->perfis()
                    ->with('permissoes')
                    ->get()
                    ->pluck('permissoes')
                    ->flatten()
                    ->pluck('slug')
                    ->unique()
                    ->toArray();

                Cache::put($cacheKey, $permissoes, now()->addHours(6));
            }
        }

        return $next($request);
    }
}
