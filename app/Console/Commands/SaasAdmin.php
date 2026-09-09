<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SaasAdmin extends Command
{
    protected $signature = 'saas:admin {email}';
    protected $description = 'Create a separate SaaS platform administrator (password prompted securely)';

    public function handle(): int
    {
        if (!config('saas.enabled')) return 1;
        $lock = \App\Saas\MaintenanceGate::acquire(rtrim(config('saas.storage_root'), '/').'/_platform');
        try { return app()->call([$this, 'runWithinGate']); }
        finally { \App\Saas\MaintenanceGate::release($lock); }
    }

    public function runWithinGate(): int
    {
        if (!config('saas.enabled')) return 1;
        $email = strtolower($this->argument('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Invalid email.'); return 1; }
        $db = DB::connection('saas_central');
        if ($db->table('saas_admins')->where('email', $email)->exists()) { $this->error('Administrator already exists.'); return 1; }
        $password = $this->secret('Senha (mínimo 12 caracteres)');
        if (strlen((string) $password) < 12) return 1;
        $db->table('saas_admins')->insert(['email' => $email, 'password' => Hash::make($password), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->info('Administrador criado.');
        return 0;
    }
}
