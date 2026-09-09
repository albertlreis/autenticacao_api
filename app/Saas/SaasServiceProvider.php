<?php

namespace App\Saas;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

final class SaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->extend('queue', fn ($manager) => new TenantQueueManager($manager));
        $this->app->extend(\Illuminate\Cache\RateLimiter::class, fn ($limiter) => new TenantRateLimiter($limiter));
        if (!config('saas.enabled')) return;
        config(['database.connections.saas_central' => config('saas.central')]);
        if (config('saas.dedicated_tenant_id')) return;
        // CLI and requests without context cannot accidentally reach the old ERP database.
        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1,
            'database' => 'TENANT_CONTEXT_REQUIRED', 'username' => '', 'password' => '',
        ]]);
    }

    public function boot(): void
    {
        if (!config('saas.enabled')) return;
        \Illuminate\Support\Facades\Storage::extend('tenant-local', function ($app, $config) {
            $disk = $app['filesystem']->createLocalDriver(array_merge($config, ['driver' => 'local']));
            return new TenantFilesystem($disk->getDriver(), $disk->getAdapter(), $config);
        });
        \Illuminate\Queue\Queue::createPayloadUsing(function () {
            $tenant = app(TenantContext::class)->tenant();
            if (!$tenant) throw new RuntimeException('Queue dispatch requires tenant context.');
            return ['tenant_id' => $tenant->id];
        });
        // Daemon workers must not reserve jobs while a tenant is suspended or the registry is unavailable.
        Queue::looping(function () {
            try {
                $tenant = app(TenantContext::class)->tenant();
                return $tenant && app(TenantRegistry::class)->byId($tenant->id)?->status === 'active';
            } catch (\Throwable $e) { return false; }
        });
        Queue::before(function ($event) {
            $context = app(TenantContext::class);
            $tenant = $context->tenant();
            $current = $tenant ? app(TenantRegistry::class)->byId($tenant->id) : null;
            if ($current && $current->status !== 'active') {
                $event->job->release(60);
                throw new RuntimeException('Tenant suspended; job retained.');
            }
            $jobTenant = $event->job->payload()['tenant_id'] ?? config('saas.dedicated_tenant_id');
            if (!$current || $jobTenant !== $tenant->id) {
                $event->job->fail(new RuntimeException('Job tenant missing, mismatched or suspended.'));
                throw new RuntimeException('Job tenant rejected.');
            }
            // A worker belongs to exactly one tenant; refresh entitlements before every job.
            $tenant->modules = $current->modules;
            $required = BackgroundModules::job((string) ($event->job->payload()['displayName'] ?? ''));
            if ($required === null || array_filter($required, fn ($module) => !$context->allows($module))) {
                $event->job->fail(new RuntimeException('Job module disabled or unmapped.'));
                throw new RuntimeException('Job module rejected.');
            }
            \Illuminate\Support\Facades\Auth::forgetGuards();
        });
        Queue::after(fn () => \Illuminate\Support\Facades\Auth::forgetGuards());
        Queue::exceptionOccurred(fn () => \Illuminate\Support\Facades\Auth::forgetGuards());
    }
}
