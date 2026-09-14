<?php

namespace Tests\Feature;

use App\Models\AcessoUsuario;
use App\Saas\LocalDeveloperSwitchController;
use App\Saas\ResolveTenant;
use App\Saas\TenantContext;
use App\Saas\TenantRegistry;
use App\Services\PermissoesCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LocalDeveloperSwitchTest extends SaasIsolationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.local_developer_switch' => true, 'saas.scheme' => 'http', 'saas.url_port' => '5173']);

        DB::connection('saas_central')->statement('ALTER TABLE saas_tenants ADD COLUMN provisioned_at TEXT');
        DB::connection('saas_central')->statement('CREATE TABLE saas_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, action TEXT, details TEXT, created_at TEXT, updated_at TEXT)');
        DB::connection('saas_central')->table('saas_tenants')->update(['provisioned_at' => now()]);

        foreach (['alpha', 'beta'] as $tenant) {
            $this->withinTenant($tenant, function (): void {
                foreach (['telefone', 'cargo', 'forcar_troca_senha'] as $column) {
                    DB::statement("ALTER TABLE acesso_usuarios ADD COLUMN {$column} TEXT");
                }
                DB::statement('CREATE TABLE acesso_perfis (id INTEGER PRIMARY KEY, nome TEXT, codigo TEXT, created_at TEXT, updated_at TEXT)');
                DB::statement('CREATE TABLE acesso_usuario_perfil (id_usuario INTEGER, id_perfil INTEGER, created_at TEXT, updated_at TEXT)');
                DB::statement('CREATE TABLE acesso_permissoes (id INTEGER PRIMARY KEY, slug TEXT, nome TEXT)');
                DB::statement('CREATE TABLE acesso_perfil_permissao (id_perfil INTEGER, id_permissao INTEGER, created_at TEXT, updated_at TEXT)');
                DB::table('acesso_perfis')->insert(['id' => 1, 'nome' => 'Desenvolvedor', 'codigo' => 'desenvolvedor']);
                DB::table('acesso_usuario_perfil')->insert(['id_usuario' => 1, 'id_perfil' => 1]);
            });
        }
    }

    public function test_endpoint_is_hidden_when_the_local_switch_is_disabled(): void
    {
        config(['saas.local_developer_switch' => false]);

        $this->withinTenant('alpha', function (): void {
            try {
                $this->asLocal(fn () => app(LocalDeveloperSwitchController::class)->index($this->requestFor(AcessoUsuario::findOrFail(1))));
                $this->fail('Disabled local switch was exposed.');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        });
    }

    public function test_production_build_does_not_register_the_switch_route(): void
    {
        $environment = $this->app['env'];
        $this->app['env'] = 'production';
        config(['saas.local_developer_switch' => true]);

        try {
            $this->getJson('/api/v1/dev/tenants')->assertNotFound();
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_local_switch_routes_belong_to_the_base_module(): void
    {
        $this->assertSame(['base'], config('saas_routes.'.LocalDeveloperSwitchController::class));
    }

    public function test_endpoint_requires_the_developer_profile(): void
    {
        $this->withinTenant('alpha', function (): void {
            DB::table('acesso_usuario_perfil')->delete();
            try {
                $this->asLocal(fn () => app(LocalDeveloperSwitchController::class)->index($this->requestFor(AcessoUsuario::findOrFail(1))));
                $this->fail('Non-developer user was allowed to switch tenants.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        });
    }

    public function test_index_lists_only_active_provisioned_tenants(): void
    {
        DB::connection('saas_central')->table('saas_tenants')->where('id', 'beta')->update(['status' => 'suspended']);

        $this->withinTenant('alpha', function (): void {
            $response = $this->asLocal(fn () => app(LocalDeveloperSwitchController::class)->index($this->requestFor(AcessoUsuario::findOrFail(1))));
            $this->assertSame(['alpha'], collect($response->getData(true)['tenants'])->pluck('id')->all());
        });
    }

    public function test_switch_revokes_the_source_token_and_issues_a_target_token(): void
    {
        $response = $this->withinTenant('alpha', function () {
            $user = AcessoUsuario::findOrFail(1);
            $sourceToken = $user->tokens()->firstOrFail();
            $accessToken = \Mockery::mock();
            $accessToken->shouldReceive('delete')->once()->andReturnUsing(function () use ($sourceToken): void {
                $sourceToken->delete();
                // Keep authorization in "local", then restore the SQLite test path resolution.
                $this->app['env'] = 'testing';
            });
            $user->withAccessToken($accessToken);

            return $this->asLocal(fn () => app(LocalDeveloperSwitchController::class)->switch(
                $this->requestFor($user, ['tenant_id' => 'beta']),
                app(TenantRegistry::class),
                app(TenantContext::class),
                app(PermissoesCacheService::class),
            ));
        });

        $payload = $response->getData(true);
        $this->assertSame('http://beta.sierra.test:5173', $payload['target_url']);
        $this->assertNotEmpty($payload['access_token']);
        $this->withinTenant('alpha', fn () => $this->assertDatabaseMissing('personal_access_tokens', ['id' => 1]));
        $this->withinTenant('beta', function (): void {
            $this->assertDatabaseHas('personal_access_tokens', ['name' => 'local-developer-switch']);
            $this->assertDatabaseHas('acesso_usuario_perfil', ['id_usuario' => 1, 'id_perfil' => 1]);
        });
        $this->assertDatabaseHas('saas_events', [
            'tenant_id' => 'beta',
            'action' => 'developer.switched',
        ], 'saas_central');
    }

    private function withinTenant(string $tenant, callable $callback)
    {
        return app(ResolveTenant::class)->handle(
            Request::create('http://'.$tenant.'.sierra.test/api/v1/probe'),
            fn () => $callback()
        );
    }

    private function requestFor(AcessoUsuario $user, array $input = []): Request
    {
        $request = Request::create('/api/v1/dev/switch', 'POST', $input);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function asLocal(callable $callback)
    {
        $environment = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            return $callback();
        } finally {
            $this->app['env'] = $environment;
        }
    }
}
