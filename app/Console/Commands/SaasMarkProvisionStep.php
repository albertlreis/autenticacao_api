<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SaasMarkProvisionStep extends Command
{
    protected $signature = 'saas:mark-provision-step {tenant} {step} {state=completed} {--error-code=}';
    protected $description = 'Record an operator-verified infrastructure provisioning step';

    private const STEPS = ['capacity', 'storage', 'https', 'validation', 'backup', 'restore'];

    public function handle(): int
    {
        $tenant = (string) $this->argument('tenant');
        $step = (string) $this->argument('step');
        $state = (string) $this->argument('state');
        if (!in_array($step, self::STEPS, true) || !in_array($state, ['running', 'completed', 'failed'], true)) {
            $this->error('Unsupported provisioning step or state.');
            return self::FAILURE;
        }
        $db = DB::connection('saas_central');
        $record = $db->table('saas_tenants')->where('id', $tenant)->first();
        if (!$record || $record->status !== 'provisioning') {
            $this->error('Tenant is not awaiting operational validation.');
            return self::FAILURE;
        }
        $key = ['tenant_id' => $tenant, 'step' => $step];
        $values = ['state' => $state, 'error_code' => $this->option('error-code') ?: null, 'updated_at' => now()];
        $table = $db->table('saas_provision_steps');
        $table->where($key)->exists() ? $table->where($key)->update($values) : $table->insert($key + $values + ['created_at' => now()]);
        $db->table('saas_events')->insert(['tenant_id' => $tenant, 'action' => 'provision.step.'.$state,
            'details' => json_encode(['step' => $step, 'actor' => 'operator:cli']), 'created_at' => now(), 'updated_at' => now()]);
        return self::SUCCESS;
    }
}
