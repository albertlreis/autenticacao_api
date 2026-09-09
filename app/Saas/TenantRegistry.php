<?php

namespace App\Saas;

use Illuminate\Support\Facades\DB;

final class TenantRegistry
{
    public function byHost(string $host): ?object
    {
        if ($id = config('saas.dedicated_tenant_id')) {
            return in_array(strtolower($host), config('saas.dedicated_hosts', []), true) ? $this->byId($id) : null;
        }
        $base = strtolower((string) config('saas.base_domain'));
        $host = strtolower($host);
        if (!str_ends_with($host, '.'.$base)) return null;
        $slug = substr($host, 0, -strlen('.'.$base));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $slug)) return null;
        $tenant = DB::connection('saas_central')->table('saas_tenants')->where('slug', $slug)->first();
        return $tenant && ($tenant->installation_type ?? 'shared') === 'shared' ? $tenant : null;
    }

    public function byId(string $id): ?object
    {
        if (config('saas.dedicated_tenant_id') && $id !== config('saas.dedicated_tenant_id')) return null;
        $tenant = DB::connection('saas_central')->table('saas_tenants')->where('id', $id)->first();
        if (!config('saas.dedicated_tenant_id') && ($tenant->installation_type ?? 'shared') !== 'shared') return null;
        return $tenant;
    }
}
