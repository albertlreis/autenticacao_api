<?php
namespace Tests\Feature;

use App\Saas\TenantAccess;
use App\Saas\AccessPolicy;
use App\Saas\ResolveTenant;
use App\Models\AcessoPerfil;
use App\Models\AcessoUsuario;
use App\Services\UsuarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SaasAccessPolicyTest extends SaasIsolationTest
{
    private function access(string $slug, callable $callback)
    {
        return app(ResolveTenant::class)->handle(Request::create('https://'.$slug.'.sierra.test/api/v1/probe'), fn () => $callback());
    }
    private function seedAccess(): void
    {
        DB::statement('CREATE TABLE acesso_perfis (id INTEGER PRIMARY KEY, nome TEXT, codigo TEXT, descricao TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE acesso_permissoes (id INTEGER PRIMARY KEY, slug TEXT, nome TEXT, descricao TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE acesso_usuario_perfil (id_usuario INTEGER, id_perfil INTEGER, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE acesso_perfil_permissao (id_perfil INTEGER, id_permissao INTEGER, created_at TEXT, updated_at TEXT)');
        foreach (['senha_alterada_em','forcar_troca_senha','created_at','updated_at'] as $column) DB::statement('ALTER TABLE acesso_usuarios ADD COLUMN '.$column.' TEXT');
        foreach (['administrador','financeiro','estoquista','desenvolvedor','custom','vendedor'] as $i => $code) DB::table('acesso_perfis')->insert(['id' => $i+1, 'codigo' => $code === 'custom' ? null : $code, 'nome' => TenantAccess::PROFILES[$code] ?? 'Misto']);
        $slugs = ['produtos.visualizar','contas.pagar.view','perfis.criar','perfis.editar','perfis.atribuir_permissao','perfis.remover_permissao','perfis.visualizar','permissoes.visualizar','usuarios.criar','usuarios.editar','usuarios.atribuir_perfil','estoque.importar_planilha_dev','unknown.permissao'];
        foreach ($slugs as $i => $slug) DB::table('acesso_permissoes')->insert(['id' => $i+1, 'slug' => $slug, 'nome' => $slug]);
        foreach ([1,4,5] as $profile) foreach (range(1,count($slugs)) as $permission) DB::table('acesso_perfil_permissao')->insert(['id_perfil' => $profile, 'id_permissao' => $permission]);
        foreach ([1,2] as $permission) DB::table('acesso_perfil_permissao')->insert(['id_perfil' => 2, 'id_permissao' => $permission]);
        DB::table('acesso_usuario_perfil')->insert(['id_usuario' => 1, 'id_perfil' => 1]);
        Auth::setUser(AcessoUsuario::findOrFail(1));
    }
    public function test_contract_filters_permissions_even_with_stale_cache_and_recontract_restores(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess();
            \Illuminate\Support\Facades\Cache::put('permissoes_usuario_1', ['contas.pagar.view','unknown.permissao'], 3600);
            $permissions = app(\App\Services\PermissoesCacheService::class)->get(AcessoUsuario::find(1));
            $this->assertContains('produtos.visualizar', $permissions);
            $this->assertNotContains('contas.pagar.view', $permissions);
            $this->assertNotContains('unknown.permissao', $permissions);
            $this->assertNotContains('estoque.importar_planilha_dev', $permissions);
        });
        DB::connection('saas_central')->table('saas_tenants')->where('id','alpha')->update(['modules' => '["estoque","financeiro"]']);
        $this->access('alpha', fn () => $this->assertContains('contas.pagar.view', TenantAccess::permissions(1)));
        DB::connection('saas_central')->table('saas_tenants')->where('id','alpha')->update(['modules' => '["estoque"]']);
        $this->access('alpha', fn () => $this->assertNotContains('contas.pagar.view', TenantAccess::permissions(1)));
        $this->access('beta', function () { $this->seedAccess(); $this->assertNotContains('contas.pagar.view', TenantAccess::permissions(1)); });
    }
    public function test_uncontracted_specialist_has_no_base_permission_and_mixed_profile_keeps_stock(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess(); DB::table('acesso_usuario_perfil')->delete();
            DB::table('acesso_usuario_perfil')->insert(['id_usuario'=>1,'id_perfil'=>2]);
            $this->assertSame([], TenantAccess::permissions(1));
            DB::table('acesso_usuario_perfil')->insert(['id_usuario'=>1,'id_perfil'=>5]);
            $this->assertContains('produtos.visualizar', TenantAccess::permissions(1));
            $this->assertNotContains('contas.pagar.view', TenantAccess::permissions(1));
        });
    }
    public function test_invalid_user_creation_is_atomic_and_finance_assignment_rejected(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess(); $count = AcessoUsuario::count();
            foreach ([2,6] as $id) {
                try { app(UsuarioService::class)->criar(['nome'=>'Novo','email'=>'new@example.test','senha'=>'secret1234','perfis'=>[$id]]); $this->fail('Assignment allowed'); }
                catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('perfis',$e->errors()); }
                $this->assertSame($count,AcessoUsuario::count());
            }
            try { app(UsuarioService::class)->adicionarPerfis(AcessoUsuario::find(1),[2]); $this->fail('Direct assignment allowed'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(1,DB::table('acesso_usuario_perfil')->count()); }
        });
    }
    public function test_preserved_bindings_and_reserved_developer(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess(); DB::table('acesso_usuario_perfil')->insert(['id_usuario'=>1,'id_perfil'=>2]);
            app(UsuarioService::class)->atualizar(AcessoUsuario::find(1), ['nome'=>'Atualizado','perfis'=>[1,2]]);
            $this->assertSame(2,DB::table('acesso_usuario_perfil')->count());
            try { AccessPolicy::profiles([4]); $this->fail('Developer assigned'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
            AccessPolicy::profiles([1,4],[1,4]);
        });
    }
    public function test_profile_edits_cannot_grant_finance_or_rename_standard_profile(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess();
            foreach ([[null,['nome'=>'Outro','permissoes'=>[2]]],[null,['nome'=>'Financeiro']],[AcessoPerfil::find(2),['nome'=>'Outro']],[null,['nome'=>'Outro','permissoes'=>[12]]]] as [$profile,$data]) {
                try { AccessPolicy::profileWrite($profile,$data); $this->fail('Invalid profile accepted'); }
                catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
            }
            AccessPolicy::profileWrite(AcessoPerfil::find(5), ['nome'=>'Misto','permissoes'=>[1,2]]);
            $this->assertTrue(AcessoPerfil::find(3)->toArray()['atribuivel']);
            $this->assertFalse(AcessoPerfil::find(2)->toArray()['atribuivel']);
            $this->assertFalse(AcessoPerfil::find(4)->toArray()['atribuivel']);
        });
    }
    public function test_controller_authorization_and_permission_association_routes(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess(); $controller=app(\App\Http\Controllers\Api\PerfilController::class);
            $response=$controller->assignPermissao(new Request(['permissoes'=>[1]]),3);
            $this->assertSame(200,$response->getStatusCode());
            $this->assertSame(200,$controller->removePermissao(3,1)->getStatusCode());
            DB::table('acesso_usuario_perfil')->delete();
            try { $controller->index(); $this->fail('Unprivileged list allowed'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        });
    }
    public function test_shared_catalog_and_policy_are_identical_in_both_apis(): void
    {
        foreach (['config/saas_permissions.php','app/Saas/TenantAccess.php','app/Saas/ModuleCatalog.php'] as $path) {
            $other=base_path('../gerenciador_estoque_api/'.$path);
            $this->assertFileExists($other); $this->assertSame(file_get_contents(base_path($path)),file_get_contents($other));
        }
    }
    public function test_support_cli_requires_reason_and_grants_and_revokes_only_target_tenant(): void
    {
        $this->access('alpha', function () { $this->seedAccess(); DB::statement('CREATE TABLE auditoria_logs (id INTEGER PRIMARY KEY)'); });
        $this->mock(\App\Services\AuditoriaLogService::class, function ($mock) { $mock->shouldReceive('registrar')->twice()->andReturn(null); });
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('saas:support-profile', ['tenant'=>'alpha','user'=>1,'action'=>'grant']));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('saas:support-profile', ['tenant'=>'alpha','user'=>1,'action'=>'grant','--reason'=>'Teste autorizado']));
        $this->access('alpha', fn () => $this->assertTrue(DB::table('acesso_usuario_perfil')->where('id_perfil',4)->exists()));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('saas:support-profile', ['tenant'=>'alpha','user'=>1,'action'=>'revoke','--reason'=>'Encerramento do teste']));
        $this->access('alpha', fn () => $this->assertFalse(DB::table('acesso_usuario_perfil')->where('id_perfil',4)->exists()));
    }

    public function test_sales_without_finance_restores_seller_availability_without_assigning_it(): void
    {
        $this->access('alpha', function () {
            $this->seedAccess();
            DB::table('acesso_permissoes')->insert(['id'=>14,'slug'=>'pedidos.visualizar','nome'=>'Pedidos']);
            foreach ([1,6] as $profile) DB::table('acesso_perfil_permissao')->insert(['id_perfil'=>$profile,'id_permissao'=>14]);
            $this->assertFalse(AcessoPerfil::find(6)->toArray()['atribuivel']);
            $this->assertNotContains('pedidos.visualizar', TenantAccess::permissions(1));
        });
        DB::connection('saas_central')->table('saas_tenants')->where('id','alpha')->update(['modules'=>'["estoque","vendas"]']);
        $this->access('alpha', function () {
            $this->assertTrue(AcessoPerfil::find(6)->toArray()['atribuivel']);
            $this->assertFalse(AcessoPerfil::find(2)->toArray()['atribuivel']);
            $this->assertFalse(AcessoPerfil::find(4)->toArray()['atribuivel']);
            $this->assertSame([1], DB::table('acesso_usuario_perfil')->where('id_usuario',1)->pluck('id_perfil')->all());
            $this->assertContains('pedidos.visualizar', TenantAccess::permissions(1));
            $this->assertNotContains('contas.pagar.view', TenantAccess::permissions(1));
            AccessPolicy::profiles([6]);
            DB::table('acesso_usuario_perfil')->where('id_usuario',1)->update(['id_perfil'=>3]);
            $this->assertNotContains('pedidos.visualizar', TenantAccess::permissions(1));
            DB::table('acesso_usuario_perfil')->where('id_usuario',1)->update(['id_perfil'=>6]);
            $this->assertContains('pedidos.visualizar', TenantAccess::permissions(1));
        });
        DB::connection('saas_central')->table('saas_tenants')->where('id','alpha')->update(['modules'=>'["estoque"]']);
        $this->access('alpha', function () {
            $this->assertNotContains('pedidos.visualizar', TenantAccess::permissions(1));
            $this->assertSame(1,DB::table('acesso_usuario_perfil')->where('id_perfil',6)->count());
        });
    }

}
