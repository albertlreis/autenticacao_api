<?php

namespace App\Saas;

use Closure;
use Illuminate\Http\Request;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('saas.enabled')) return $next($request);
        if (config('saas.dedicated_tenant_id') && !in_array(strtolower($request->getHost()), config('saas.dedicated_hosts', []), true)) abort(404);
        if ($request->is('api/v1/control/*')) {
            abort_unless(config('saas_control.enabled') && $request->getHost() === config('saas_control.host'), 404);
            $lock = MaintenanceGate::acquire(rtrim(config('saas.storage_root'), '/').'/_platform');
            try { return $next($request); } finally { MaintenanceGate::release($lock); }
        }
        // Liveness never touches a tenant database.
        if ($request->is('api/v1/health')) return response()->json(['status' => 'ok']);
        if (strtolower($request->getHost()) === strtolower(config('saas.platform_host'))) {
            abort_if(config('saas_control.enabled'), 410, 'Acesse a administração pelo backoffice Webleap.');
            abort_unless($request->is('api/v1/platform/*'), 404);
            $lock = MaintenanceGate::acquire(rtrim(config('saas.storage_root'), '/').'/_platform');
            try { return $next($request); } finally { MaintenanceGate::release($lock); }
        }
        abort_if($request->is('api/v1/platform/*'), 404);
        try { $tenant = app(TenantRegistry::class)->byHost($request->getHost()); }
        catch (\Illuminate\Database\QueryException $e) { return response()->json(['code' => 'PLATFORM_UNAVAILABLE', 'message' => 'Autorização temporariamente indisponível.'], 503); }
        if (!$tenant) return response()->json(['code' => 'TENANT_NOT_FOUND', 'message' => 'Empresa não encontrada.'], 404);
        if ($tenant->status !== 'active') return response()->json(['code' => 'TENANT_UNAVAILABLE', 'message' => 'Ambiente indisponível. Contate a administração.'], 403);
        $context = app(TenantContext::class);
        try {
            $context->enter($tenant);
            return $next($request);
        } finally {
            $context->leave();
        }
    }
}
