<?php

namespace App\Console\Commands;

use App\Saas\TenantRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\StringInput;

final class SaasProvision extends Command
{
    protected $signature = 'saas:provision {tenant?} {--resume : Resume an interrupted provisioner after acquiring its database lock} {--pending : Process provision requests from the platform} {--inventory-path= : Absolute path of the inventory API} {--prepared-database : Operations service has already created the database} {--defer-activation : Operations service must verify infrastructure before activation}';
    protected $description = 'Provision pending tenants, migrating both APIs and recording each result';

    public function handle(): int
    {
        if (!config('saas.enabled')) return 1;
        $lock = \App\Saas\MaintenanceGate::acquire(rtrim(config('saas.storage_root'), '/').'/_platform');
        try { return app()->call([$this, 'runWithinGate']); }
        finally { \App\Saas\MaintenanceGate::release($lock); }
    }

    public function runWithinGate(TenantRegistry $registry): int
    {
        if (!config('saas.enabled')) return 1;
        if (!$this->option('pending') && !$this->argument('tenant')) { $this->error('Specify a tenant or --pending.'); return 1; }
        $inventory = realpath($this->option('inventory-path') ?: config('saas.inventory_path'));
        if (!$inventory || !is_file($inventory.'/artisan')) { $this->error('Inventory API path is required.'); return 1; }
        $db = DB::connection('saas_central');
        $ids = $this->option('pending')
            ? $db->table('saas_tenants')->whereNotNull('provision_requested_at')->whereIn('status', ['pending', 'failed'])->pluck('id')->all()
            : [$this->argument('tenant')];
        $result = 0;
        foreach (array_filter($ids) as $id) {
            $tenant = $registry->byId($id);
            if (!$tenant) { $result = 1; continue; }
            if ($tenant->status === 'active' && $tenant->provisioned_at) { $this->info('Already provisioned: '.$id); continue; }
            $lock = 'saas-provision-'.$id;
            if (!(int) $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired) {
                $this->error('Another provisioner holds this tenant lock.'); $result = 1; continue;
            }
            $states = $this->option('resume') ? ['pending', 'failed', 'provisioning'] : ['pending', 'failed'];
            // Atomic state transition plus a connection-owned lock allows safe crash recovery.
            if (!$db->table('saas_tenants')->where('id', $id)->whereIn('status', $states)->update(['status' => 'provisioning', 'updated_at' => now()])) {
                $db->selectOne('SELECT RELEASE_LOCK(?)', [$lock]);
                $this->error('Tenant is already provisioning or suspended: '.$id); $result = 1; continue;
            }
            try {
                if (!preg_match('/^sierra_[a-z0-9_]+$/D', $tenant->database_name)) throw new \RuntimeException('Invalid database identifier.');
                $profiles = config('saas.profiles', []);
                if ($file = config('saas.profiles_file')) $profiles = array_replace($profiles, json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
                $profile = $profiles[$tenant->connection_profile] ?? null;
                if (!is_array($profile)) throw new \RuntimeException('Unknown database profile.');
                // Provisioner credentials are separate from runtime application credentials.
                $provision = array_merge($profile, [
                    'database' => null, 'username' => config('saas.provision_username'), 'password' => config('saas.provision_password'),
                ]);
                unset($provision['url']);
                config(['database.connections.saas_provision' => $provision]);
                DB::purge('saas_provision');
                if (!$this->option('prepared-database')) DB::connection('saas_provision')->statement('CREATE DATABASE IF NOT EXISTS `'.$tenant->database_name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                foreach ([base_path(), $inventory] as $path) {
                    $this->runStep($path, $id, 'migrate --force', 'migrations');
                }
                $this->runStep($inventory, $id, 'app:setup-initial-data', 'initial-data');
                $input = new StringInput('');
                $command = 'saas:bootstrap-admin '.$input->escapeToken($tenant->admin_email).' '.$input->escapeToken($tenant->admin_name);
                $this->runStep(base_path(), $id, $command, 'administrator');
                if ($this->option('defer-activation')) { $this->event($id, 'provision.application.ready'); continue; }
                $db->table('saas_tenants')->where('id', $id)->update(['status' => 'active', 'provisioned_at' => now(), 'provision_requested_at' => null, 'updated_at' => now()]);
                $this->event($id, 'provision.completed');
                $this->info('Provisioned: '.$tenant->slug);
            } catch (\Throwable $e) {
                $db->table('saas_tenants')->where('id', $id)->update(['status' => 'failed', 'provision_requested_at' => null, 'updated_at' => now()]);
                // Do not persist command output, SQL bindings or secrets in the public event log.
                $this->event($id, 'provision.failed', ['exception' => get_class($e)]);
                $this->error('Provision failed for '.$id.'. Inspect the restricted provisioner logs.');
                $result = 1;
            } finally { DB::purge('saas_provision'); $db->selectOne('SELECT RELEASE_LOCK(?)', [$lock]); }
        }
        return $result;
    }

    private function runStep(string $path, string $tenant, string $command, string $step): void
    {
        $this->event($tenant, 'provision.step.started', ['step' => $step, 'service' => basename($path)]);
        $process = new Process([PHP_BINARY, $path.'/artisan', 'saas:run', $tenant, $command, '--provisioning', '--no-interaction'], $path, ['SAAS_ENABLED' => 'true']);
        $process->setTimeout(1800);
        $process->run();
        if (!$process->isSuccessful()) {
            $diagnostic = $process->getOutput()."\n".$process->getErrorOutput();
            foreach (\Illuminate\Support\Arr::dot(config('saas')) + ['app.key' => config('app.key')] as $key => $value) {
                if (preg_match('/password|secret|key|token/i', $key) && is_string($value) && strlen($value) >= 4) {
                    $diagnostic = str_replace($value, '[REDACTED]', $diagnostic);
                }
            }
            $log = storage_path('logs/saas-provision-'.$tenant.'.log');
            file_put_contents($log, now()->toIso8601String().' '.$step."\n".$diagnostic."\n", FILE_APPEND | LOCK_EX);
            chmod($log, 0600);
            throw new \RuntimeException('Provisioning step failed: '.$step);
        }
        $this->event($tenant, 'provision.step.completed', ['step' => $step, 'service' => basename($path)]);
    }

    private function event(string $tenant, string $action, array $details = []): void
    {
        DB::connection('saas_central')->table('saas_events')->insert(['tenant_id' => $tenant, 'action' => $action,
            'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }
}
