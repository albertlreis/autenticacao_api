<?php

namespace App\Saas;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TenantDomains
{
    public static function normalize(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if ($host === '' || strlen($host) > 253 || !str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) throw new InvalidArgumentException('Domínio inválido.');
        foreach (explode('.', $host) as $label) {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) throw new InvalidArgumentException('Domínio inválido.');
        }
        return $host;
    }

    public static function attachCanonical(object $tenant): object
    {
        $domain = DB::connection('saas_central')->table('saas_tenant_domains')
            ->where('tenant_id', $tenant->id)->where('is_canonical', true)->where('active', true)->first();
        $tenant->canonical_host = $domain?->host;
        return $tenant;
    }

    public static function url(object $tenant): string
    {
        $host = $tenant->canonical_host ?? null;
        if (!$host) throw new InvalidArgumentException('Empresa sem domínio canônico ativo.');
        return config('saas.scheme').'://'.$host.(config('saas.url_port') ? ':'.(int) config('saas.url_port') : '');
    }

    public static function payload(string $tenantId): array
    {
        return DB::connection('saas_central')->table('saas_tenant_domains')->where('tenant_id', $tenantId)
            ->orderByDesc('is_canonical')->orderBy('host')->get()
            ->map(fn ($domain) => ['host' => $domain->host, 'canonical' => (bool) $domain->is_canonical, 'active' => (bool) $domain->active])
            ->all();
    }
}
