<?php

namespace App\Http\Middleware;

use App\Support\Logging\SierraLog;
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
                try {
                    $permissoes = $user->perfis()
                        ->with('permissoes')
                        ->get()
                        ->pluck('permissoes')
                        ->flatten()
                        ->pluck('slug')
                        ->unique()
                        ->toArray();

                    Cache::put($cacheKey, $permissoes, now()->addHours(6));
                } catch (\Throwable $e) {
                    SierraLog::auth('auth.permissions.cache_write_failed', [
                        'user_id' => $user->id,
                        'operation' => 'middleware_cache',
                        'exception' => $e,
                    ], 'error');
                }
            }
        }

        return $next($request);
    }
}
