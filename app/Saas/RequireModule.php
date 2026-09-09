<?php

namespace App\Saas;

use Closure;
use Illuminate\Http\Request;

final class RequireModule
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('saas.enabled') || $request->is('api/v1/platform/*')) return $next($request);
        $action = $request->route()?->getActionName();
        $map = config('saas_routes', []);
        $controller = explode('@', (string) $action)[0];
        $modules = $map[$action] ?? $map[$controller] ?? null;
        if ($modules === null) return response()->json(['code' => 'MODULE_UNMAPPED', 'message' => 'Recurso ainda não disponível no SaaS.'], 403);
        foreach ((array) $modules as $module) {
            if (!app(TenantContext::class)->allows($module)) {
                return response()->json(['code' => 'MODULE_DISABLED', 'module' => $module, 'message' => 'Módulo não contratado.'], 403);
            }
        }
        return $next($request);
    }
}
