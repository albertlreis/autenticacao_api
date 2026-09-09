<?php

namespace App\Saas;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class TenantContext
{
    private ?object $tenant = null;
    private array $snapshot = [];
    private bool $dedicated = false;
    private string $storagePath = '';
    private $maintenanceLock = null;

    public function tenant(): ?object { return $this->tenant; }
    public function modules(): array { return $this->tenant ? ModuleCatalog::validate(json_decode($this->tenant->modules, true, 512, JSON_THROW_ON_ERROR)) : []; }
    public function allows(string $module): bool { return $module === 'base' || in_array($module, $this->modules(), true); }
    public function payload(): array
    {
        return $this->tenant ? ['tenant' => ['id' => $this->tenant->id, 'nome' => $this->tenant->name, 'slug' => $this->tenant->slug], 'modules' => $this->modules()] : [];
    }

    public function enter(object $tenant, bool $provisioning = false): void
    {
        if ($this->tenant) throw new RuntimeException('Tenant context already initialized.');
        if ($tenant->status !== 'active' && !($provisioning && in_array($tenant->status, ['pending', 'provisioning', 'failed', 'suspended'], true))) {
            throw new RuntimeException('Tenant is not active.');
        }
        if ($id = config('saas.dedicated_tenant_id')) {
            if ($tenant->id !== $id || ($tenant->installation_type ?? '') !== 'dedicated' || $tenant->database_name !== config('database.connections.mysql.database')) throw new RuntimeException('Dedicated tenant mismatch.');
            ModuleCatalog::validate(json_decode($tenant->modules, true, 512, JSON_THROW_ON_ERROR));
            $this->dedicated = true;
            $this->tenant = $tenant;
            $this->maintenanceLock = MaintenanceGate::acquire(storage_path());
            Log::withContext(['tenant_id' => $tenant->id]);
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $tenant->id) || !preg_match('/^sierra_[a-z0-9_]+$/D', $tenant->database_name)) {
            throw new RuntimeException('Invalid tenant database or identifier.');
        }
        ModuleCatalog::validate(json_decode($tenant->modules, true, 512, JSON_THROW_ON_ERROR));
        $profiles = config('saas.profiles', []);
        if ($file = config('saas.profiles_file')) {
            $profiles = array_replace($profiles, json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
        }
        $profile = $profiles[$tenant->connection_profile] ?? null;
        if (!is_array($profile)) throw new RuntimeException('Unknown connection profile.');
        $this->storagePath = storage_path();
        foreach (['database', 'cache', 'filesystems', 'session', 'app.url', 'queue', 'auth', 'frontend', 'acesso', 'services', 'conta_azul', 'google_calendar', 'banco_do_brasil'] as $key) {
            $this->snapshot[$key] = config($key);
        }
        $this->tenant = $tenant;
        $root = rtrim(config('saas.storage_root'), '/').'/'.$tenant->id;
        $this->maintenanceLock = MaintenanceGate::acquire($root);
        $url = config('saas.scheme').'://'.$tenant->slug.'.'.config('saas.base_domain');
        if (config('saas.url_port')) $url .= ':'.(int) config('saas.url_port');
        \Illuminate\Support\Facades\URL::forceRootUrl($url);
        \Illuminate\Support\Facades\URL::forceScheme(config('saas.scheme'));
        $connection = array_merge($profile, ['database' => $tenant->database_name]);
        // Replace both implicit and legacy explicit mysql connections. Never inherit a DB URL.
        unset($connection['url']);
        if (($profile['driver'] ?? '') === 'sqlite' && app()->environment('testing')) $connection['database'] = $profile['directory'].'/'.$tenant->database_name; 
        config(['database.default' => 'mysql', 'database.connections.mysql' => $connection,
            'acesso.password_reset_frontend_url' => $url, 'acesso.allowed_origins' => [$url],
            'acesso.refresh_cookie.domain' => null, 'acesso.refresh_cookie.same_site' => 'Lax',
            'app.url' => $url, 'session.domain' => null, 'session.cookie' => 'sierra_'.$tenant->id,
            'session.files' => $root.'/framework/sessions', 'session.connection' => 'mysql',
            'cache.default' => 'file', 'cache.limiter' => 'file', 'cache.prefix' => 'tenant:'.$tenant->id.':',
            'cache.stores.file.path' => $root.'/framework/cache/data',
            'queue.connections.database.connection' => 'mysql', 'queue.failed.database' => 'mysql',
        ]);
        $integrations = config('saas.integrations.'.$tenant->id, []);
        // Never reuse the original company's outbound credentials for a new customer.
        config(['services.comms' => $integrations['comms'] ?? ['base_url' => '', 'api_key' => '', 'api_secret' => '']]);
        foreach (['conta_azul', 'google_calendar'] as $service) {
            config(["$service.client_id" => null, "$service.client_secret" => null]);
            foreach (($integrations[$service] ?? []) as $key => $value) config(["$service.$key" => $value]);
        }
        config(['banco_do_brasil' => $integrations['banco_do_brasil'] ?? []]);
        config(['conta_azul.redirect_uri' => $url.'/api/estoque/v1/integrations/conta-azul/callback',
            'conta_azul.oauth_front_redirect' => $url.'/integracoes/conta-azul',
            'google_calendar.redirect_uri' => $url.'/api/estoque/v1/integrations/google-calendar/callback',
            'google_calendar.oauth_front_redirect' => $url.'/integracoes/google-agenda']);
        app()->useStoragePath($root);
        foreach (config('filesystems.disks', []) as $name => $disk) {
            if (($disk['driver'] ?? '') === 'local') {
                config(["filesystems.disks.$name.driver" => "tenant-local", "filesystems.disks.$name.tenant_disk" => $name]);
                config(["filesystems.disks.$name.root" => $root.'/app'.($name === 'public' ? '/public' : '')]);
            } elseif (($disk['driver'] ?? '') === 's3') {
                // Cloud disks require their own signed adapter; fail closed if selected in this pilot.
                config(["filesystems.disks.$name.driver" => 'saas-cloud-not-configured']);
            } else {
                throw new RuntimeException('Unsupported tenant filesystem.');
            }
            config(["filesystems.disks.$name.url" => $url.'/storage']);
            Storage::forgetDisk($name);
        }
        foreach ([$root.'/app/public', $root.'/framework/cache/data', $root.'/framework/sessions', $root.'/logs'] as $path) {
            if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) throw new RuntimeException('Cannot create tenant storage.');
        }
        $this->resetResolvedServices();
        Log::withContext(['tenant_id' => $tenant->id]);
    }

    public function leave(): void
    {
        if (!$this->tenant) return;
        if ($this->dedicated) {
            $this->tenant = null; $this->dedicated = false;
            MaintenanceGate::release($this->maintenanceLock); $this->maintenanceLock = null;
            Auth::forgetGuards(); Log::withoutContext(); return;
        }
        foreach (array_keys(config('filesystems.disks', [])) as $name) Storage::forgetDisk($name);
        config($this->snapshot);
        app()->useStoragePath($this->storagePath);
        \Illuminate\Support\Facades\URL::forceRootUrl(null);
        \Illuminate\Support\Facades\URL::forceScheme(null);
        $this->tenant = null;
        $this->snapshot = [];
        $this->resetResolvedServices();
        MaintenanceGate::release($this->maintenanceLock);
        $this->maintenanceLock = null;
        Log::withoutContext();
    }

    private function resetResolvedServices(): void
    {
        app()->forgetScopedInstances();
        DB::purge('mysql');
        app('queue')->resetConnections();
        app()->forgetInstance('queue.connection');
        Auth::forgetGuards();
        Cache::forgetDriver();
        app()->forgetInstance('cache.store');
        app(\Illuminate\Cache\RateLimiter::class)->useStore(app('cache')->store(config('cache.limiter')));
        app('session')->forgetDrivers();
    }
}
