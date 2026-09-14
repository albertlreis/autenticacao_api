<?php

namespace Tests\Feature;

use App\Models\AcessoUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SaasSmokeUserTest extends TestCase
{
    private string $secret = 'local-smoke-password-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'saas.enabled' => true,
            'saas.dedicated_tenant_id' => 'tenant-alpha',
            'saas.dedicated_hosts' => ['alpha.sierra.test'],
            'database.connections.saas_central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('saas_central');
        foreach (['2026_09_07_000001_create_saas_platform.php', '2026_09_09_000001_add_webleap_control.php', '2026_09_10_000001_create_saas_tenant_domains.php'] as $migration) {
            (require base_path('database/saas/'.$migration))->up();
        }
        DB::connection('saas_central')->table('saas_tenants')->insert([
            'id' => 'tenant-alpha',
            'name' => 'Alpha',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@alpha.test',
            'slug' => 'alpha',
            'database_name' => config('database.connections.mysql.database'),
            'connection_profile' => 'default',
            'status' => 'active',
            'modules' => json_encode(['estoque']),
            'installation_type' => 'dedicated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('saas_central')->table('saas_tenant_domains')->insert([
            'tenant_id' => 'tenant-alpha',
            'host' => 'alpha.sierra.test',
            'is_canonical' => true,
            'active' => true,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        putenv('SIERRA_SMOKE_PASSWORD='.$this->secret);
    }

    protected function tearDown(): void
    {
        putenv('SIERRA_SMOKE_PASSWORD');
        parent::tearDown();
    }

    public function test_it_creates_a_least_privilege_smoke_identity_without_printing_the_secret(): void
    {
        $this->artisan('saas:smoke-user', [
            'tenant' => 'tenant-alpha',
            '--email' => 'sierra-smoke+alpha@webleap.dev',
        ])->assertSuccessful();

        $user = AcessoUsuario::query()->where('email', 'sierra-smoke+alpha@webleap.dev')->firstOrFail();
        $this->assertTrue($user->ativo);
        $this->assertTrue(Hash::check($this->secret, $user->senha));
        $this->assertSame(['monitoramento'], $user->perfis()->pluck('codigo')->all());
        $this->assertSame(
            ['produtos.visualizar'],
            DB::table('acesso_perfil_permissao as pp')
                ->join('acesso_permissoes as p', 'p.id', '=', 'pp.id_permissao')
                ->where('pp.id_perfil', $user->perfis()->value('acesso_perfis.id'))
                ->pluck('p.slug')->all()
        );
        $this->assertDatabaseHas('auditoria_logs', ['acao' => 'smoke_user.created', 'entity_id' => (string) $user->id]);
    }

    public function test_reconciliation_preserves_password_until_rotation_is_explicit(): void
    {
        $arguments = ['tenant' => 'tenant-alpha', '--email' => 'sierra-smoke+alpha@webleap.dev'];
        $this->artisan('saas:smoke-user', $arguments)->assertSuccessful();
        $original = AcessoUsuario::where('email', $arguments['--email'])->value('senha');

        putenv('SIERRA_SMOKE_PASSWORD='.str_repeat('n', 40));
        $this->artisan('saas:smoke-user', $arguments)->assertSuccessful();
        $this->assertSame($original, AcessoUsuario::where('email', $arguments['--email'])->value('senha'));

        $this->artisan('saas:smoke-user', $arguments + ['--rotate' => true])->assertSuccessful();
        $this->assertNotSame($original, AcessoUsuario::where('email', $arguments['--email'])->value('senha'));
        $this->assertDatabaseHas('auditoria_logs', ['acao' => 'smoke_user.rotated']);
    }

    public function test_it_rejects_wrong_tenant_and_short_password(): void
    {
        $this->artisan('saas:smoke-user', ['tenant' => 'other'])->assertFailed();
        putenv('SIERRA_SMOKE_PASSWORD=short');
        $this->artisan('saas:smoke-user', ['tenant' => 'tenant-alpha'])->assertFailed();
    }

    public function test_it_supports_a_dedicated_runtime_without_enabling_request_tenant_resolution(): void
    {
        config(['saas.enabled' => false]);

        $this->artisan('saas:smoke-user', [
            'tenant' => 'tenant-alpha',
            '--email' => 'sierra-smoke+alpha@webleap.dev',
        ])->assertSuccessful();

        $this->assertDatabaseHas('acesso_usuarios', [
            'email' => 'sierra-smoke+alpha@webleap.dev',
            'ativo' => true,
        ]);
    }
}
