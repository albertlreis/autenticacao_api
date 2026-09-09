<?php

namespace App\Console\Commands;

use App\Saas\TenantContext;
use App\Saas\TenantRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\StringInput;

final class SaasRun extends Command
{
    protected $signature = 'saas:run {tenant} {command_line : Quoted Artisan command and options} {--provisioning : Allow an inactive tenant for setup}';
    protected $description = 'Run an Artisan command in one isolated tenant (including dedicated queue workers and schedules)';

    public function handle(TenantRegistry $registry, TenantContext $context): int
    {
        if (!config('saas.enabled')) { $this->error('SAAS_ENABLED must be true.'); return 1; }
        $tenant = $registry->byId($this->argument('tenant'));
        if (!$tenant) { $this->error('Tenant not found.'); return 1; }
        try {
            $context->enter($tenant, (bool) $this->option('provisioning'));
            $input = new StringInput($this->argument('command_line'));
            foreach (\App\Saas\BackgroundModules::command((string) $input->getFirstArgument()) as $module) {
                if (!$context->allows($module)) { $this->error('Module disabled: '.$module); return 1; }
            }
            return $this->getApplication()->run($input, $this->output);
        } finally { $context->leave(); }
    }
}
