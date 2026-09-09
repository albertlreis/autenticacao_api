<?php
namespace App\Console\Commands;
use App\Models\AcessoPerfil;
use App\Models\AcessoUsuario;
use App\Saas\TenantContext;
use App\Saas\TenantRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SaasSupportProfile extends Command
{
    protected $signature = 'saas:support-profile {tenant} {user : ID do usuário} {action : grant ou revoke} {--reason= : Justificativa obrigatória}';
    protected $description = 'Concede/revoga perfil técnico por operação privada e auditada';
    public function handle(TenantRegistry $registry, TenantContext $context): int
    {
        if (!config('saas.enabled') || !in_array($this->argument('action'), ['grant','revoke'], true) || !trim((string) $this->option('reason'))) { $this->error('Informe grant/revoke e --reason.'); return 1; }
        $tenant = $registry->byId($this->argument('tenant'));
        if (!$tenant) { $this->error('Empresa não encontrada.'); return 1; }
        try {
            $context->enter($tenant);
            DB::transaction(function () {
                if (!\Illuminate\Support\Facades\Schema::hasTable('auditoria_logs')) throw new \RuntimeException('Auditoria indisponível.');
                $user = AcessoUsuario::findOrFail($this->argument('user'));
                $profile = AcessoPerfil::where('codigo', 'desenvolvedor')->firstOrFail();
                $before = $user->perfis()->pluck('acesso_perfis.id')->all();
                if ($this->argument('action') === 'grant') $user->perfis()->syncWithoutDetaching([$profile->id]);
                else $user->perfis()->detach($profile->id);
                app(\App\Services\PermissoesCacheService::class)->forget((int) $user->id);
                \Illuminate\Support\Facades\Cache::forget('perfis_usuario_'.$user->id);
                app(\App\Services\AuditoriaLogService::class)->registrar([
                    'occurred_at' => now(), 'tipo' => 'auditoria', 'categoria' => 'negocio', 'modulo' => 'acessos',
                    'acao' => 'support_profile.'.$this->argument('action'), 'label' => 'Perfil técnico alterado pela operação',
                    'message' => 'CLI: '.trim($this->option('reason')), 'entity_type' => AcessoUsuario::class, 'entity_id' => $user->id,
                    'actor_type' => 'cli', 'actor_name' => 'private-console',
                    'source_system' => 'auth', 'source_kind' => 'business_event', 'retention_days' => 365,
                ], [['campo' => 'perfis', 'old' => $before, 'new' => $user->perfis()->pluck('acesso_perfis.id')->all(), 'value_type' => 'json']]);
            });
            $this->info('Perfil técnico atualizado.'); return 0;
        } finally { $context->leave(); }
    }
}
