<?php

namespace App\Saas;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlController
{
    private function db() { return DB::connection('saas_central'); }

    public function index()
    {
        return response()->json(['catalog' => ModuleCatalog::MODULES, 'can_provision' => (bool) config('saas_control.provision_enabled'),
            'tenants' => $this->db()->table('saas_tenants')->orderBy('name')->get()->map(fn ($tenant) => $this->payload($tenant))]);
    }

    public function show(string $id)
    {
        $tenant = $this->tenant($id);
        return response()->json(['tenant' => $this->payload($tenant),
            'events' => $this->db()->table('saas_events')->where('tenant_id', $id)->orderByDesc('id')->limit(100)->get(),
            'steps' => $this->db()->table('saas_provision_steps')->where('tenant_id', $id)->orderBy('id')->get()]);
    }

    public function operation(Request $request, string $id)
    {
        $operation = $this->db()->table('saas_control_operations')->where('id', $id)
            ->where('actor', $request->attributes->get('control_actor'))->first();
        abort_unless($operation, 404);
        return response()->json(['id' => $operation->id, 'state' => $operation->state, 'http_status' => $operation->http_status,
            'response' => json_decode($operation->response ?? 'null', true)]);
    }

    public function store(Request $request)
    {
        abort_unless(config('saas_control.provision_enabled'), 409, 'Criação de empresas ainda não habilitada.');
        $data = $request->validate($this->rules(true));
        $data['modules'] = $this->modules($data['modules']);
        if (in_array($data['slug'], config('saas_control.reserved_slugs'), true) || $this->db()->table('saas_tenants')->where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'Subdomínio reservado ou já cadastrado.']);
        }
        $id = (string) Str::uuid();
        $domains = $this->domains($data['slug'], $data['domains'] ?? null);
        unset($data['domains']);
        $data['admin_email'] = strtolower($data['admin_email']);
        $data['modules'] = json_encode($data['modules']);
        try {
            $this->db()->transaction(function () use ($data, $domains, $id) {
                $this->db()->table('saas_tenants')->insert($data + ['id' => $id, 'database_name' => 'sierra_'.str_replace('-', '', $id),
                    'connection_profile' => $id, 'installation_type' => 'shared', 'status' => 'pending', 'contract_version' => 1,
                    'created_at' => now(), 'updated_at' => now()]);
                $this->replaceDomains($id, $domains);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') throw ValidationException::withMessages(['domains' => 'Um dos domínios já pertence a outra empresa.']);
            throw $e;
        }
        $tenant = $this->tenant($id);
        $this->event($request, $id, 'tenant.created', null, $this->payload($tenant));
        return response()->json(['tenant' => $this->payload($tenant)], 201);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate($this->rules(false));
        abort_unless(array_key_exists('modules', $data) || isset($data['status']) || array_key_exists('domains', $data), 422, 'Nenhuma alteração informada.');
        if (array_key_exists('modules', $data)) $data['modules'] = $this->modules($data['modules']);
        try {
            [$before, $after] = $this->db()->transaction(function () use ($data, $id) {
                $tenant = $this->db()->table('saas_tenants')->where('id', $id)->lockForUpdate()->first();
                abort_unless($tenant, 404);
                abort_unless((int) $tenant->contract_version === $data['version'], 409, 'Contrato alterado por outro administrador. Atualize a página.');
                abort_unless(in_array($tenant->status, ['active', 'suspended'], true) && $tenant->provisioned_at, 409, 'Ambiente ainda não preparado.');
                $before = $this->payload($tenant);
                if (array_key_exists('domains', $data)) $this->replaceDomains($id, $this->domains($tenant->slug, $data['domains']));
                $changes = ['contract_version' => $tenant->contract_version + 1, 'updated_at' => now()];
                if (isset($data['status'])) $changes['status'] = $data['status'];
                if (isset($data['modules'])) $changes['modules'] = json_encode($data['modules']);
                $this->db()->table('saas_tenants')->where('id', $id)->update($changes);
                return [$before, $this->payload($this->tenant($id))];
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') throw ValidationException::withMessages(['domains' => 'Um dos domínios já pertence a outra empresa.']);
            throw $e;
        }
        $this->event($request, $id, 'tenant.updated', $before, $after, $data['reason']);
        return response()->json(['tenant' => $after]);
    }

    public function provision(Request $request, string $id)
    {
        abort_unless(config('saas_control.provision_enabled'), 409, 'Provisionamento ainda não habilitado.');
        $tenant = $this->db()->table('saas_tenants')->where('id', $id)->lockForUpdate()->first();
        abort_unless($tenant, 404);
        abort_unless($tenant->installation_type === 'shared' && in_array($tenant->status, ['pending', 'failed'], true) && !$tenant->provision_requested_at, 409, 'Preparação já solicitada ou não permitida.');
        $this->db()->table('saas_tenants')->where('id', $id)->update(['provision_requested_at' => now(), 'updated_at' => now()]);
        $this->event($request, $id, 'provision.requested', null, null);
        return response()->json(['state' => 'requested'], 202);
    }

    private function rules(bool $create): array
    {
        $rules = ['domains' => 'sometimes|array|min:1|max:20', 'domains.*.host' => 'required|string|max:253|distinct',
            'domains.*.canonical' => 'required|boolean', 'domains.*.active' => 'required|boolean'];
        if ($create) return $rules + ['name' => 'required|string|max:191', 'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'admin_name' => 'required|string|max:191', 'admin_email' => 'required|email|max:100', 'modules' => 'present|array', 'modules.*' => 'string|distinct'];
        return $rules + ['version' => 'required|integer|min:1', 'modules' => 'sometimes|array', 'modules.*' => 'string|distinct',
            'status' => 'sometimes|in:active,suspended', 'reason' => 'required|string|max:500'];
    }

    private function domains(string $slug, ?array $input): array
    {
        $standard = TenantDomains::normalize($slug.'.'.config('saas.base_domain'));
        $domains = $input ?? [['host' => $standard, 'canonical' => true, 'active' => true]];
        $normalized = [];
        foreach ($domains as $domain) {
            try { $host = TenantDomains::normalize($domain['host']); }
            catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['domains' => $e->getMessage()]); }
            if (in_array($host, [strtolower(config('saas.base_domain')), strtolower(config('saas.platform_host')), strtolower(config('saas_control.host'))], true)) {
                throw ValidationException::withMessages(['domains' => 'Domínio reservado.']);
            }
            $normalized[$host] = ['host' => $host, 'canonical' => (bool) $domain['canonical'], 'active' => (bool) $domain['active']];
        }
        if (!isset($normalized[$standard])) $normalized[$standard] = ['host' => $standard, 'canonical' => false, 'active' => true];
        $canonical = array_filter($normalized, fn ($domain) => $domain['canonical'] && $domain['active']);
        if (count($canonical) !== 1) throw ValidationException::withMessages(['domains' => 'Informe exatamente um domínio canônico ativo.']);
        return array_values($normalized);
    }

    private function replaceDomains(string $tenantId, array $domains): void
    {
        $this->db()->table('saas_tenant_domains')->where('tenant_id', $tenantId)->delete();
        foreach ($domains as $domain) $this->db()->table('saas_tenant_domains')->insert(['tenant_id' => $tenantId, 'host' => $domain['host'],
            'is_canonical' => $domain['canonical'], 'active' => $domain['active'], 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tenant(string $id) { $tenant = $this->db()->table('saas_tenants')->where('id', $id)->first(); abort_unless($tenant, 404); return $tenant; }
    private function modules(array $modules): array { try { return ModuleCatalog::validate($modules); } catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['modules' => $e->getMessage()]); } }
    private function payload(object $tenant): array
    {
        $tenant = TenantDomains::attachCanonical($tenant);
        return ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status,
            'modules' => json_decode($tenant->modules, true), 'version' => (int) $tenant->contract_version,
            'installation_type' => $tenant->installation_type, 'url' => TenantDomains::url($tenant),
            'domains' => TenantDomains::payload($tenant->id), 'provision_requested' => (bool) $tenant->provision_requested_at];
    }
    private function event(Request $request, string $id, string $action, $before, $after, ?string $reason = null): void
    {
        $this->db()->table('saas_events')->insert(['tenant_id' => $id, 'action' => $action,
            'details' => json_encode(['actor' => $request->attributes->get('control_actor'), 'operation_id' => $request->header('Idempotency-Key'),
                'before' => $before, 'after' => $after, 'reason' => $reason]), 'created_at' => now(), 'updated_at' => now()]);
    }
}
