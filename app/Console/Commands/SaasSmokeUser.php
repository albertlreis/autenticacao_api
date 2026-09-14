<?php

namespace App\Console\Commands;

use App\Models\AcessoUsuario;
use App\Saas\TenantContext;
use App\Saas\TenantRegistry;
use App\Services\AuditoriaLogService;
use App\Services\PermissoesCacheService;
use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SaasSmokeUser extends Command
{
    protected $signature = 'saas:smoke-user
        {tenant : Tenant UUID or slug}
        {--email= : Dedicated technical identity; defaults to sierra-smoke+{slug}@webleap.dev}
        {--password-env=SIERRA_SMOKE_PASSWORD : Environment variable containing the password}
        {--rotate : Replace the password when the identity already exists}';

    protected $description = 'Create or reconcile the least-privilege authenticated smoke identity';

    public function handle(TenantRegistry $registry, TenantContext $context): int
    {
        $passwordEnv = (string) $this->option('password-env');
        if (! preg_match('/^[A-Z][A-Z0-9_]{2,80}$/D', $passwordEnv)) {
            $this->error('Invalid password environment variable name.');

            return self::FAILURE;
        }

        $password = getenv($passwordEnv);
        if (! is_string($password) || strlen($password) < 32) {
            $this->error("{$passwordEnv} must contain at least 32 characters.");

            return self::FAILURE;
        }

        $tenantArgument = strtolower(trim((string) $this->argument('tenant')));
        if ($currentTenant = $context->tenant()) {
            if (($currentTenant->status ?? null) !== 'active') {
                $this->error('Current tenant is not active.');

                return self::FAILURE;
            }
            $currentId = strtolower(trim((string) ($currentTenant->id ?? '')));
            $currentSlug = strtolower(trim((string) ($currentTenant->slug ?? '')));
            if (! in_array($tenantArgument, [$currentId, $currentSlug], true)) {
                $this->error('Current tenant context does not match the requested tenant.');

                return self::FAILURE;
            }

            return $this->reconcile($currentSlug ?: $this->tenantSlug($currentId), $password);
        }

        try {
            $tenant = $registry->byId($tenantArgument);
        } catch (Throwable) {
            $tenant = null;
        }
        if (! $tenant) {
            try {
                $id = DB::connection('saas_central')->table('saas_tenants')
                    ->whereRaw('LOWER(slug) = ?', [$tenantArgument])->value('id');
                $tenant = $id ? $registry->byId((string) $id) : null;
            } catch (Throwable) {
                $tenant = null;
            }
        }
        if (! $tenant) {
            $this->error('Tenant not found or not eligible for this runtime.');

            return self::FAILURE;
        }

        try {
            $context->enter($tenant);

            return $this->reconcile((string) $tenant->slug, $password);
        } catch (Throwable) {
            $this->error('Unable to reconcile smoke identity. Review the sanitized application log.');

            return self::FAILURE;
        } finally {
            $context->leave();
        }
    }

    private function reconcile(string $slug, string $password): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: "sierra-smoke+{$slug}@webleap.dev")));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid smoke identity e-mail.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('auditoria_logs')) {
            $this->error('Audit storage is required.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $password, $slug): void {
            app(AccessInitialDataService::class)->seedPermissoes();
            $permissionId = DB::table('acesso_permissoes')->where('slug', 'produtos.visualizar')->value('id');
            if (! $permissionId) {
                throw new \RuntimeException('Required permission produtos.visualizar is unavailable.');
            }

            $now = now();
            $profiles = DB::table('acesso_perfis')
                ->where('codigo', 'monitoramento')
                ->orWhereRaw('LOWER(TRIM(nome)) = ?', ['monitoramento'])
                ->get();
            if ($profiles->count() > 1) {
                throw new \RuntimeException('Ambiguous monitoramento profile.');
            }
            $profile = $profiles->first();
            if ($profile) {
                $profileId = $profile->id;
                DB::table('acesso_perfis')->where('id', $profileId)->update([
                    'codigo' => 'monitoramento',
                    'nome' => 'Monitoramento',
                    'descricao' => 'Smoke autenticado de disponibilidade',
                    'updated_at' => $now,
                ]);
            } else {
                $profileId = DB::table('acesso_perfis')->insertGetId([
                    'codigo' => 'monitoramento',
                    'nome' => 'Monitoramento',
                    'descricao' => 'Smoke autenticado de disponibilidade',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('acesso_perfil_permissao')->where('id_perfil', $profileId)->delete();
            DB::table('acesso_perfil_permissao')->insert([
                'id_perfil' => $profileId,
                'id_permissao' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $user = AcessoUsuario::query()->where('email', $email)->first();
            $created = ! $user;
            if (! $user) {
                $user = AcessoUsuario::query()->create([
                    'nome' => 'Sierra Smoke '.$slug,
                    'email' => $email,
                    'senha' => Hash::make($password),
                    'ativo' => true,
                    'forcar_troca_senha' => false,
                    'senha_alterada_em' => $now,
                ]);
            } else {
                $changes = ['ativo' => true, 'forcar_troca_senha' => false];
                if ((bool) $this->option('rotate')) {
                    $changes += ['senha' => Hash::make($password), 'senha_alterada_em' => $now];
                }
                $user->forceFill($changes)->save();
            }

            $user->perfis()->sync([$profileId]);
            app(PermissoesCacheService::class)->forget((int) $user->id);
            Cache::forget('perfis_usuario_'.$user->id);
            app(AuditoriaLogService::class)->registrar([
                'occurred_at' => $now,
                'tipo' => 'auditoria',
                'categoria' => 'tecnico',
                'modulo' => 'acessos',
                'acao' => $created ? 'smoke_user.created' : ((bool) $this->option('rotate') ? 'smoke_user.rotated' : 'smoke_user.reconciled'),
                'label' => 'Identidade técnica de smoke reconciliada',
                'message' => 'Operação privada idempotente para o tenant '.$slug.'.',
                'entity_type' => AcessoUsuario::class,
                'entity_id' => $user->id,
                'actor_type' => 'cli',
                'actor_name' => 'private-console',
                'source_system' => 'auth',
                'source_kind' => 'business_event',
                'retention_days' => 365,
            ]);
        });

        $this->info('Smoke identity reconciled. No credential was printed.');

        return self::SUCCESS;
    }

    private function tenantSlug(string $fallback): string
    {
        $email = strtolower(trim((string) $this->option('email')));
        if (preg_match('/^sierra-smoke\+([a-z0-9-]+)@webleap\.dev$/D', $email, $matches)) {
            return $matches[1];
        }

        return trim(preg_replace('/[^a-z0-9-]+/', '-', $fallback), '-');
    }
}
