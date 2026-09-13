<?php

namespace Tests\Feature;

use App\Saas\ModuleCatalog;
use App\Saas\RequireModule;
use App\Saas\ResolveTenant;
use App\Saas\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\TestCase;

class SaasIsolationTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/sierra-saas-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0770, true);
        config([
            'saas.enabled' => true, 'saas.base_domain' => 'sierra.test', 'saas.platform_host' => 'admin.sierra.test',
            'saas.asset_key' => str_repeat('k', 40), 'saas.storage_root' => $this->directory.'/storage',
            'saas.profiles.default' => ['driver' => 'sqlite', 'directory' => $this->directory, 'prefix' => ''],
            'database.connections.saas_central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('saas_central');
        Storage::extend('tenant-local', function ($app, $config) {
            $disk = $app['filesystem']->createLocalDriver(array_merge($config, ['driver' => 'local']));
            return new \App\Saas\TenantFilesystem($disk->getDriver(), $disk->getAdapter(), $config);
        });
        DB::connection('saas_central')->statement('CREATE TABLE saas_tenants (id TEXT PRIMARY KEY, name TEXT, slug TEXT, database_name TEXT, connection_profile TEXT, status TEXT, modules TEXT)');
        DB::connection('saas_central')->statement('CREATE TABLE saas_tenant_domains (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT, host TEXT UNIQUE, is_canonical INTEGER, active INTEGER)');
        foreach (['alpha', 'beta'] as $slug) {
            touch($this->directory.'/sierra_'.$slug);
            DB::connection('saas_central')->table('saas_tenants')->insert([
                'id' => $slug, 'name' => $slug, 'slug' => $slug, 'database_name' => 'sierra_'.$slug,
                'connection_profile' => 'default', 'status' => 'active', 'modules' => json_encode(['estoque']),
            ]);
            DB::connection('saas_central')->table('saas_tenant_domains')->insert(['tenant_id' => $slug, 'host' => $slug.'.sierra.test', 'is_canonical' => 1, 'active' => 1]);
            $this->within($slug, function () use ($slug) {
                DB::statement('CREATE TABLE records (id INTEGER PRIMARY KEY, value TEXT)');
                DB::table('records')->insert(['id' => 1, 'value' => $slug]);
                DB::statement('CREATE TABLE acesso_usuarios (id INTEGER PRIMARY KEY, nome TEXT, email TEXT, senha TEXT, ativo INTEGER)');
                DB::table('acesso_usuarios')->insert(['id' => 1, 'nome' => $slug, 'email' => 'same@example.test', 'senha' => 'unused', 'ativo' => 1]);
                DB::statement('CREATE TABLE personal_access_tokens (id INTEGER PRIMARY KEY, tokenable_type TEXT, tokenable_id INTEGER, name TEXT, token TEXT, abilities TEXT, last_used_at TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT)');
                DB::table('personal_access_tokens')->insert(['id' => 1, 'tokenable_type' => \App\Models\AcessoUsuario::class, 'tokenable_id' => 1, 'name' => 'test', 'token' => hash('sha256', $slug.'-secret'), 'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now()]);
            });
        }
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->leave();
        DB::purge('mysql');
        DB::purge('saas_central');
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function within(string $slug, callable $callback)
    {
        return app(ResolveTenant::class)->handle(Request::create('https://'.$slug.'.sierra.test/api/v1/probe'), fn () => $callback());
    }

    public function test_maintenance_blocks_new_work_and_preserves_other_tenant(): void
    {
        $root = config('saas.storage_root').'/alpha';
        file_put_contents($root.'/.operations-maintenance.json', '{"reason":"backup"}');
        try {
            $this->within('alpha', fn () => $this->fail('Maintenance allowed business execution'));
            $this->fail('Expected 503');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertNull(app(TenantContext::class)->tenant());
        $this->within('beta', fn () => $this->assertSame('beta', DB::table('records')->value('value')));
        unlink($root.'/.operations-maintenance.json');
        $this->within('alpha', fn () => $this->assertSame('alpha', DB::table('records')->value('value')));
    }

    public function test_inflight_context_holds_a_shared_backup_gate(): void
    {
        $this->within('alpha', function () {
            $lock = fopen(config('saas.storage_root').'/alpha/.operations.lock', 'c');
            $this->assertFalse(flock($lock, LOCK_EX | LOCK_NB));
            fclose($lock);
        });
        $lock = fopen(config('saas.storage_root').'/alpha/.operations.lock', 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        flock($lock, LOCK_UN); fclose($lock);
    }

    public function test_database_queue_reconnects_when_tenant_context_changes(): void
    {
        config(['queue.default' => 'database']);
        foreach (['alpha', 'beta', 'alpha'] as $slug) {
            $this->within($slug, function () use ($slug) {
                $queue = app('queue')->connection('database');
                $this->assertSame($this->directory.'/sierra_'.$slug, $queue->getDatabase()->getDatabaseName());
                $this->assertSame($slug, $queue->getDatabase()->table('records')->value('value'));
            });
        }
    }

    public function test_local_port_is_preserved_in_asset_and_reset_urls(): void
    {
        config(['saas.scheme' => 'http', 'saas.url_port' => '5173']);
        $this->within('alpha', function () {
            $this->assertSame('http://alpha.sierra.test:5173', config('acesso.password_reset_frontend_url'));
            $this->assertStringStartsWith('http://alpha.sierra.test:5173/storage/', Storage::disk('public')->url('image.png'));
        });
    }

    public function test_database_cache_and_files_are_isolated_and_context_is_restored(): void
    {
        $original = config('database.connections.mysql');
        $this->within('alpha', function () {
            $this->assertSame('alpha', DB::table('records')->where('id', 1)->value('value'));
            Cache::put('permissoes_usuario_1', 'alpha', 60);
            Storage::disk('local')->put('export.pdf', 'alpha');
        });
        $this->within('beta', function () {
            $this->assertSame('beta', DB::table('records')->where('id', 1)->value('value'));
            $this->assertNull(Cache::get('permissoes_usuario_1'));
            $this->assertFalse(Storage::disk('local')->exists('export.pdf'));
            Cache::put('permissoes_usuario_1', 'beta', 60);
        });
        $this->within('alpha', function () {
            $this->assertSame('alpha', Cache::get('permissoes_usuario_1'));
            $this->assertSame('alpha', Storage::disk('local')->get('export.pdf'));
        });
        $this->assertNull(app(TenantContext::class)->tenant());
        $this->assertSame($original, config('database.connections.mysql'));
    }

    public function test_identical_user_and_token_ids_do_not_allow_cross_tenant_login(): void
    {
        foreach (['alpha' => true, 'beta' => false] as $slug => $expected) {
            $this->within($slug, function () use ($expected) {
                $request = Request::create('/api/v1/probe');
                $request->headers->set('Authorization', 'Bearer 1|alpha-secret');
                Auth::guard('sanctum')->setRequest($request);
                $this->assertSame($expected, Auth::guard('sanctum')->check());
            });
        }
    }

    public function test_unknown_and_suspended_tenants_never_run_business_code(): void
    {
        $this->assertSame(404, $this->within('missing', fn () => $this->fail('Unexpected tenant execution'))->getStatusCode());
        DB::connection('saas_central')->table('saas_tenants')->where('id', 'alpha')->update(['status' => 'suspended']);
        $this->assertSame(403, $this->within('alpha', fn () => $this->fail('Unexpected tenant execution'))->getStatusCode());
        $this->assertNull(app(TenantContext::class)->tenant());
    }

    public function test_failure_cleans_context(): void
    {
        try { $this->within('alpha', fn () => throw new \RuntimeException('expected')); }
        catch (\RuntimeException $e) { $this->assertSame('expected', $e->getMessage()); }
        $this->assertNull(app(TenantContext::class)->tenant());
        $this->within('beta', fn () => $this->assertSame('beta', DB::table('records')->value('value')));
    }

    public function test_disabled_and_unmapped_actions_fail_closed(): void
    {
        $this->within('alpha', function () {
            $request = Request::create('/api/v1/financeiro');
            $route = new Route('GET', 'api/v1/financeiro', ['uses' => 'ExampleController@index', 'controller' => 'ExampleController@index']);
            $request->setRouteResolver(fn () => $route);
            config(['saas_routes.ExampleController' => ['financeiro']]);
            $response = app(RequireModule::class)->handle($request, fn () => $this->fail('Disabled module executed'));
            $this->assertSame('MODULE_DISABLED', $response->getData()->code);
            config(['saas_routes' => []]);
            $response = app(RequireModule::class)->handle($request, fn () => $this->fail('Unmapped module executed'));
            $this->assertSame('MODULE_UNMAPPED', $response->getData()->code);
        });
    }

    public function test_job_rejects_wrong_tenant_before_handler_execution(): void
    {
        (new \App\Saas\SaasServiceProvider($this->app))->boot();
        $this->within('alpha', function () {
            $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $job->shouldReceive('payload')->andReturn(['tenant_id' => 'beta', 'displayName' => 'App\\Jobs\\GenerateDocumentExport']);
            $job->shouldReceive('fail')->once();
            try {
                event(new \Illuminate\Queue\Events\JobProcessing('database', $job));
                $this->fail('Foreign job accepted');
            } catch (\RuntimeException $e) { $this->assertSame('Job tenant rejected.', $e->getMessage()); }
        });
    }

    public function test_job_rechecks_current_contract(): void
    {
        (new \App\Saas\SaasServiceProvider($this->app))->boot();
        $this->within('alpha', function () {
            $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $job->shouldReceive('payload')->andReturn(['tenant_id' => 'alpha', 'displayName' => 'App\\Jobs\\GenerateDocumentExport']);
            $job->shouldReceive('fail')->once();
            try {
                event(new \Illuminate\Queue\Events\JobProcessing('database', $job));
                $this->fail('Unsubscribed job accepted');
            } catch (\RuntimeException $e) { $this->assertSame('Job module rejected.', $e->getMessage()); }
        });
    }

    public function test_rate_limits_are_isolated_without_losing_named_limiters(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('custom-test', fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(10));
        $this->within('alpha', function () {
            \Illuminate\Support\Facades\RateLimiter::hit('login:user-1', 60);
            $this->assertSame(1, \Illuminate\Support\Facades\RateLimiter::attempts('login:user-1'));
        });
        $this->within('beta', function () {
            $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts('login:user-1'));
            $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('custom-test'));
        });
    }

    public function test_dependencies_and_unknown_modules_are_rejected(): void
    {
        foreach ([['vendas'], ['assistencia', 'estoque'], ['inventado']] as $modules) {
            try { ModuleCatalog::validate($modules); $this->fail('Invalid contract accepted'); }
            catch (\InvalidArgumentException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertCount(6, ModuleCatalog::validate(array_keys(ModuleCatalog::MODULES)));
    }

    public function test_signed_asset_serves_the_correct_disk_and_requires_valid_signature(): void
    {
        $this->within('alpha', function () {
            foreach (['local', 'public'] as $disk) {
                Storage::disk($disk)->put('document.txt', $disk);
                $url = Storage::disk($disk)->url('document.txt');
                $response = app(\App\Saas\TenantAssetController::class)(Request::create($url), 'document.txt');
                ob_start();
                $response->sendContent();
                $this->assertSame($disk, ob_get_clean());
            }
        });
    }

    public function test_signed_asset_cannot_be_replayed_on_another_tenant(): void
    {
        $url = $this->within('alpha', function () {
            Storage::disk('public')->put('photo.png', 'alpha');
            return Storage::disk('public')->url('photo.png');
        });
        $this->within('beta', function () use ($url) {
            Storage::disk('public')->put('photo.png', 'beta');
            $request = Request::create(str_replace('alpha.sierra.test', 'beta.sierra.test', $url));
            try { app(\App\Saas\TenantAssetController::class)($request, 'photo.png'); $this->fail('Foreign signature accepted'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        });
    }
}
