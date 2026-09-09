<?php

namespace App\Console\Commands;

use App\Enums\PerfilEnum;
use App\Models\AcessoUsuario;
use App\Saas\TenantContext;
use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SaasBootstrapAdmin extends Command
{
    protected $signature = 'saas:bootstrap-admin {email} {name}';
    protected $description = 'Initialize tenant access roles and first administrator without demo users';

    public function handle(TenantContext $context, AccessInitialDataService $initial): int
    {
        if (!$context->tenant()) return 1;
        $initial->seedPerfis();
        $initial->seedPermissoes();
        if (!AcessoUsuario::query()->exists()) $initial->seedAssociacoes();
        $email = strtolower($this->argument('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 1;
        if (AcessoUsuario::where('email', $email)->exists()) { $this->info('Administrador já cadastrado; senha preservada.'); return 0; }
        // Initial access uses the existing password-reset flow; no shared default password.
        DB::transaction(function () use ($email) {
            $user = AcessoUsuario::create(['nome' => $this->argument('name'), 'email' => $email,
                'senha' => Hash::make(bin2hex(random_bytes(48))), 'ativo' => true, 'forcar_troca_senha' => true]);
            $profile = DB::table('acesso_perfis')->where('nome', PerfilEnum::ADMINISTRADOR->value)->value('id');
            $user->perfis()->syncWithoutDetaching([$profile]);
            $permissions = DB::table('acesso_permissoes')->get()->filter(fn ($p) => \App\Saas\TenantAccess::permissionAllowed($p->slug))->pluck('id')->map(fn ($id) => [
                'id_perfil' => $profile, 'id_permissao' => $id, 'created_at' => now(), 'updated_at' => now(),
            ])->all();
            DB::table('acesso_perfil_permissao')->insertOrIgnore($permissions);
        });
        $this->info('Administrador criado. Use Esqueci minha senha no subdomínio da empresa para definir o primeiro acesso.');
        return 0;
    }
}
