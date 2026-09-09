<?php

namespace App\Saas;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformController
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'platform-login:'.hash('sha256', $request->ip().'|'.strtolower($data['email']));
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429);
        RateLimiter::hit($key, 300);
        $admin = DB::connection('saas_central')->table('saas_admins')->where('email', strtolower($data['email']))->where('active', true)->first();
        abort_unless($admin && Hash::check($data['password'], $admin->password), 401, 'Credenciais inválidas.');
        RateLimiter::clear($key);
        $token = Str::random(80);
        DB::connection('saas_central')->table('saas_admin_tokens')->insert([
            'admin_id' => $admin->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(8), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['token' => $token, 'expires_in' => 28800]);
    }

    public function logout(Request $request)
    {
        DB::connection('saas_central')->table('saas_admin_tokens')->where('id', $request->attributes->get('platform_token_id'))->delete();
        return response()->noContent();
    }

    public function index()
    {
        return response()->json([
            'catalog' => ModuleCatalog::MODULES,
            'tenants' => DB::connection('saas_central')->table('saas_tenants')->orderBy('name')->get()->map(fn ($tenant) => $this->publicTenant($tenant)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'admin_name' => 'required|string|max:191', 'admin_email' => 'required|email|max:191',
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D'],
            'modules' => 'present|array', 'modules.*' => 'required|string|distinct',
        ]);
        abort_if($data['slug'].'.'.config('saas.base_domain') === config('saas.platform_host'), 422, 'Subdomínio reservado.');
        $data['modules'] = $this->validateModules($data['modules']);
        $id = (string) Str::uuid();
        $db = DB::connection('saas_central');
        if ($db->table('saas_tenants')->where('slug', $data['slug'])->exists()) throw ValidationException::withMessages(['slug' => 'Subdomínio já cadastrado.']);
        $db->transaction(function () use ($db, $request, $data, $id) {
            $db->table('saas_tenants')->insert([
                'id' => $id, 'name' => $data['name'], 'admin_name' => $data['admin_name'], 'admin_email' => strtolower($data['admin_email']), 'slug' => $data['slug'],
                'database_name' => 'sierra_'.str_replace('-', '', $id), 'connection_profile' => 'default',
                'status' => 'pending', 'modules' => json_encode($data['modules']), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, $request, 'tenant.created', ['modules' => $data['modules']]);
        });
        return response()->json($this->publicTenant($db->table('saas_tenants')->where('id', $id)->first()), 201);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'modules' => 'sometimes|array', 'modules.*' => 'required|string|distinct',
            'status' => 'sometimes|in:active,suspended',
        ]);
        if (isset($data['modules'])) $data['modules'] = $this->validateModules($data['modules']);
        $db = DB::connection('saas_central');
        $db->transaction(function () use ($db, $id, $request, $data) {
            $tenant = $db->table('saas_tenants')->where('id', $id)->lockForUpdate()->first();
            abort_unless($tenant, 404);
            abort_if(in_array($tenant->status, ['pending', 'provisioning', 'failed'], true), 409, 'Conclua o provisionamento primeiro.');
            abort_if(($data['status'] ?? null) === 'active' && !$tenant->provisioned_at, 409, 'Ambiente não provisionado.');
            $changes = $data;
            if (isset($changes['modules'])) $changes['modules'] = json_encode($changes['modules']);
            $db->table('saas_tenants')->where('id', $id)->update($changes + ['updated_at' => now()]);
            $this->event($id, $request, 'tenant.updated', ['before' => ['status' => $tenant->status, 'provision_requested' => !empty($tenant->provision_requested_at), 'modules' => json_decode($tenant->modules)], 'after' => $data]);
        });
        return response()->json($this->publicTenant($db->table('saas_tenants')->where('id', $id)->first()));
    }

    public function provision(Request $request, string $id)
    {
        $changed = DB::connection('saas_central')->table('saas_tenants')->where('id', $id)
            ->whereIn('status', ['pending', 'failed'])->update(['provision_requested_at' => now(), 'updated_at' => now()]);
        abort_unless($changed, 409, 'Ambiente não pode ser provisionado neste estado.');
        $this->event($id, $request, 'provision.requested', []);
        return response()->json(['status' => 'queued'], 202);
    }

    public function events(string $id)
    {
        return response()->json(DB::connection('saas_central')->table('saas_events')->where('tenant_id', $id)->orderByDesc('id')->limit(100)->get());
    }

    private function validateModules(array $modules): array
    {
        try { return ModuleCatalog::validate($modules); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['modules' => $e->getMessage()]); }
    }

    private function publicTenant(object $tenant): array
    {
        return ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug,
            'status' => $tenant->status, 'provision_requested' => !empty($tenant->provision_requested_at), 'modules' => json_decode($tenant->modules, true),
            'url' => config('saas.scheme').'://'.$tenant->slug.'.'.config('saas.base_domain').(config('saas.url_port') ? ':'.(int) config('saas.url_port') : '')];
    }

    private function event(string $id, Request $request, string $action, array $details): void
    {
        DB::connection('saas_central')->table('saas_events')->insert([
            'tenant_id' => $id, 'admin_id' => $request->attributes->get('platform_admin_id'), 'action' => $action,
            'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
