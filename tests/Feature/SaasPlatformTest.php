<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

final class SaasPlatformTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true, 'saas.base_domain' => 'sierra.test', 'saas.platform_host' => 'admin.sierra.test',
            'database.connections.saas_central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('saas_central');
        $migration = require __DIR__.'/../../database/saas/2026_09_07_000001_create_saas_platform.php';
        $migration->up();
        DB::connection('saas_central')->table('saas_admins')->insert([
            'email' => 'operator@example.test', 'password' => Hash::make('test-password-123'), 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Route::middleware('api')->prefix('api')->group(base_path('routes/platform.php'));
    }

    private function login(): string
    {
        return $this->postJson('https://admin.sierra.test/api/v1/platform/login', ['email' => 'operator@example.test', 'password' => 'test-password-123'])
            ->assertOk()->json('token');
    }

    public function test_platform_requires_its_own_identity_and_host(): void
    {
        $this->getJson('https://admin.sierra.test/api/v1/platform/tenants')->assertUnauthorized();
        $token = $this->login();
        $this->withToken($token)->getJson('https://admin.sierra.test/api/v1/platform/tenants')->assertOk()->assertJsonCount(6, 'catalog');
        $this->withToken($token)->getJson('https://alpha.sierra.test/api/v1/platform/tenants')->assertNotFound();
        $this->withToken($token)->postJson('https://admin.sierra.test/api/v1/platform/logout')->assertNoContent();
        $this->withToken($token)->getJson('https://admin.sierra.test/api/v1/platform/tenants')->assertUnauthorized();
    }

    public function test_provisioning_gate_and_contract_dependencies(): void
    {
        $token = $this->login();
        $data = ['name' => 'Empresa Alpha', 'slug' => 'alpha', 'admin_name' => 'Gestor', 'admin_email' => 'gestor@example.test', 'modules' => ['vendas']];
        $this->withToken($token)->postJson('https://admin.sierra.test/api/v1/platform/tenants', $data)->assertUnprocessable();
        $data['modules'] = ['estoque', 'financeiro', 'vendas'];
        $id = $this->withToken($token)->postJson('https://admin.sierra.test/api/v1/platform/tenants', $data)->assertCreated()->assertJsonPath('status', 'pending')->json('id');
        $this->withToken($token)->postJson('https://admin.sierra.test/api/v1/platform/tenants', $data)->assertUnprocessable();
        $url = 'https://admin.sierra.test/api/v1/platform/tenants/'.$id;
        $this->withToken($token)->patchJson($url, ['status' => 'active'])->assertConflict();
        $this->withToken($token)->postJson($url.'/provision')->assertStatus(202);
        $this->assertNotNull(DB::connection('saas_central')->table('saas_tenants')->where('id', $id)->value('provision_requested_at'));
        DB::connection('saas_central')->table('saas_tenants')->where('id', $id)->update(['status' => 'active', 'provisioned_at' => now()]);
        $this->withToken($token)->patchJson($url, ['modules' => ['vendas']])->assertUnprocessable();
        $this->withToken($token)->patchJson($url, ['status' => 'suspended'])->assertOk()->assertJsonPath('status', 'suspended');
        $this->withToken($token)->patchJson($url, ['status' => 'active'])->assertOk();
        $this->assertSame(1, DB::connection('saas_central')->table('saas_tenants')->count());
        $this->assertGreaterThanOrEqual(4, DB::connection('saas_central')->table('saas_events')->where('tenant_id', $id)->count());
    }
}
