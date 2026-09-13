<?php

namespace App\Console\Commands;

use App\Saas\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SaasActivateTenant extends Command
{
    protected $signature = 'saas:activate-tenant {tenant} {--confirm : Confirm the reviewed operational activation} {--health-max-age=600}';
    protected $description = 'Activate a prepared tenant only after infrastructure, health, restore and invitation gates';

    private const STEPS = ['connection-profile', 'database', 'authentication-migrations', 'inventory-migrations',
        'initial-data', 'administrator', 'capacity', 'storage', 'https', 'validation', 'backup', 'restore'];

    public function handle(TenantContext $context, SaasInviteAdmin $invite): int
    {
        if (!$this->option('confirm')) {
            $this->error('Activation requires --confirm after operator review.');
            return self::FAILURE;
        }
        $db = DB::connection('saas_central');
        $id = (string) $this->argument('tenant');
        $tenant = $db->table('saas_tenants')->where('id', $id)->first();
        if (!$tenant || $tenant->status !== 'provisioning' || $tenant->provisioned_at) {
            $this->error('Tenant is not ready for activation.');
            return self::FAILURE;
        }
        $completed = $db->table('saas_provision_steps')->where('tenant_id', $id)->where('state', 'completed')->pluck('step')->all();
        if (array_diff(self::STEPS, $completed)) {
            $this->error('Required provisioning steps are incomplete.');
            return self::FAILURE;
        }
        $health = $db->table('saas_tenant_health')->where('tenant_id', $id)->first();
        $fresh = $health && strtotime((string) $health->observed_at) >= time() - max(30, (int) $this->option('health-max-age'));
        if (!$fresh || !$health->api_ok || !$health->worker_ok || !$health->scheduler_ok || !$health->backup_ok) {
            $this->error('Fresh healthy API, worker, scheduler and backup measurements are required.');
            return self::FAILURE;
        }
        $domain = $db->table('saas_tenant_domains')->where('tenant_id', $id)->where('is_canonical', true)
            ->where('active', true)->where('verification_status', 'verified')->exists();
        if (!$domain) {
            $this->error('An active verified canonical domain is required.');
            return self::FAILURE;
        }
        try {
            $context->enter($tenant, true);
            if ($invite->handle($context) !== self::SUCCESS) {
                $this->error('Administrator invitation requires review; tenant remains inactive.');
                return self::FAILURE;
            }
        } finally {
            if ($context->tenant()) $context->leave();
        }
        $db->transaction(function () use ($db, $id) {
            $updated = $db->table('saas_tenants')->where('id', $id)->where('status', 'provisioning')->whereNull('provisioned_at')
                ->update(['status' => 'active', 'provisioned_at' => now(), 'provision_requested_at' => null, 'updated_at' => now()]);
            if ($updated !== 1) throw new \RuntimeException('Tenant activation raced with another operation.');
            $db->table('saas_provision_steps')->insert(['tenant_id' => $id, 'step' => 'invitation', 'state' => 'completed',
                'error_code' => null, 'created_at' => now(), 'updated_at' => now()]);
            $db->table('saas_events')->insert(['tenant_id' => $id, 'action' => 'provision.completed',
                'details' => json_encode(['actor' => 'operator:cli']), 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->info('Tenant activated.');
        return self::SUCCESS;
    }
}
