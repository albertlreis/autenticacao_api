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
        try { $host = TenantDomains::normalize($host); } catch (\InvalidArgumentException) { return null; }
        $tenant = DB::connection('saas_central')->table('saas_tenant_domains as d')
            ->join('saas_tenants as t', 't.id', '=', 'd.tenant_id')
            ->where('d.host', $host)->where('d.active', true)->select('t.*')->first();
        return $tenant && ($tenant->installation_type ?? 'shared') === 'shared' ? TenantDomains::attachCanonical($tenant) : null;
    }

    public function byId(string $id): ?object
    {
        if (config('saas.dedicated_tenant_id') && $id !== config('saas.dedicated_tenant_id')) return null;
        $tenant = DB::connection('saas_central')->table('saas_tenants')->where('id', $id)->first();
        if (!config('saas.dedicated_tenant_id') && ($tenant->installation_type ?? 'shared') !== 'shared') return null;
        return $tenant ? TenantDomains::attachCanonical($tenant) : null;
    }
}
