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
            ->where('tenant_id', $tenant->id)->where('is_canonical', true)->where('active', true)
            ->where('verification_status', 'verified')->first();
        $tenant->canonical_host = $domain?->host;
        return $tenant;
    }

    public static function url(object $tenant): string
    {
        $host = $tenant->canonical_host ?? null;
        if (!$host) throw new InvalidArgumentException('Empresa sem domínio canônico ativo.');
        $scheme = strtolower((string) config('saas.scheme'));
        $port = (int) config('saas.url_port');
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        return $scheme.'://'.$host.($port > 0 && !$defaultPort ? ':'.$port : '');
    }

    public static function payload(string $tenantId): array
    {
        return DB::connection('saas_central')->table('saas_tenant_domains')->where('tenant_id', $tenantId)
            ->orderByDesc('is_canonical')->orderBy('host')->get()
            ->map(fn ($domain) => [
                'id' => (int) $domain->id,
                'host' => $domain->host,
                'canonical' => (bool) $domain->is_canonical,
                'active' => (bool) $domain->active,
                'verification_status' => $domain->verification_status,
                'verified_at' => $domain->verified_at,
                'verified_by' => $domain->verified_by,
                'verification_notes' => $domain->verification_notes,
            ])
            ->all();
    }
}
