<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
final class SaasControlTest extends TestCase {
 public function createApplication(){ $a=require __DIR__.'/../../bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $a; }
 protected function setUp():void{parent::setUp();config(['saas.enabled'=>true,'saas.base_domain'=>'sierra.test','saas.scheme'=>'https','saas.url_port'=>5173,'saas.dedicated_tenant_id'=>null,'saas_control.enabled'=>true,'saas_control.host'=>'sierra-control.internal','saas_control.provision_enabled'=>true,'saas_control.keys.test'=>['secret'=>str_repeat('x',40),'scopes'=>['sierra:manage']],'database.connections.saas_central'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);DB::purge('saas_central');foreach(['2026_09_07_000001_create_saas_platform.php','2026_09_09_000001_add_webleap_control.php','2026_09_09_000002_create_saas_invitations.php','2026_09_10_000001_create_saas_tenant_domains.php'] as $f)(require base_path('database/saas/'.$f))->up();Route::middleware('api')->prefix('api')->group(base_path('routes/control.php'));}
 private function signed($method,$path,$data=[],$id=null,$nonce=null,$host='sierra-control.internal',$actor='webleap:1'){
  $body=$method==='GET'?'':json_encode($data);$time=(string)time();$nonce??=bin2hex(random_bytes(32));$uri='/api/v1/control/'.$path;
  $canonical=implode("\n",[$method,$uri,hash('sha256',$body),$actor,$time,$nonce,$id??'']);
  return $this->call($method,'http://'.$host.$uri,[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_SIERRA_KEY'=>'test','HTTP_X_SIERRA_ACTOR'=>$actor,'HTTP_X_SIERRA_TIME'=>$time,'HTTP_X_SIERRA_NONCE'=>$nonce,'HTTP_X_SIERRA_SIGNATURE'=>hash_hmac('sha256',$canonical,str_repeat('x',40)),'HTTP_IDEMPOTENCY_KEY'=>$id??''],$body);
 }
 private function createTenant(){return $this->signed('POST','tenants',['name'=>'Alpha','slug'=>'alpha','admin_name'=>'Gestor','admin_email'=>'gestor@example.test','modules'=>['estoque','vendas']],(string)Str::uuid())->assertCreated()->json('tenant.id');}
 public function test_signature_replay_host_and_scope():void{
  $this->getJson('http://sierra-control.internal/api/v1/control/tenants')->assertForbidden();$nonce=bin2hex(random_bytes(32));
  $this->signed('GET','tenants',[],null,$nonce)->assertOk();$this->signed('GET','tenants',[],null,$nonce)->assertForbidden();
  $this->signed('GET','tenants',[],null,null,'manaus.sierra.test')->assertNotFound();
  config(['saas_control.keys.test.scopes'=>[]]);$this->signed('GET','tenants')->assertForbidden();
 }
 public function test_create_is_idempotent_and_does_not_expose_database():void{
  $d=['name'=>'Alpha','slug'=>'alpha','admin_name'=>'Gestor','admin_email'=>'gestor@example.test','modules'=>['estoque']];$id=(string)Str::uuid();
  $a=$this->signed('POST','tenants',$d,$id)->assertCreated()->json();$b=$this->signed('POST','tenants',$d,$id)->assertCreated()->json();$this->assertSame($a,$b);$this->assertArrayNotHasKey('database_name',$a['tenant']);
  $this->assertSame(1,DB::connection('saas_central')->table('saas_tenants')->count());$this->assertSame(1,DB::connection('saas_central')->table('saas_events')->count());
  $d['name']='Outra';$this->signed('POST','tenants',$d,$id)->assertConflict();$this->signed('GET','operations/'.$id)->assertOk()->assertJsonPath('state','completed');$this->signed('GET','operations/'.$id,[],null,null,'sierra-control.internal','webleap:2')->assertNotFound();
 }
 public function test_version_validation_and_atomic_audit():void{
  $id=$this->createTenant();DB::connection('saas_central')->table('saas_tenants')->where('id',$id)->update(['status'=>'active','provisioned_at'=>now()]);
  $this->signed('PATCH','tenants/'.$id,['version'=>1,'modules'=>['vendas'],'reason'=>'Teste'],(string)Str::uuid())->assertUnprocessable();
  $this->signed('PATCH','tenants/'.$id,['version'=>1,'status'=>'suspended','reason'=>'Teste'],(string)Str::uuid())->assertOk()->assertJsonPath('tenant.version',2);
  $this->signed('PATCH','tenants/'.$id,['version'=>1,'status'=>'active','reason'=>'Conflito'],(string)Str::uuid())->assertConflict();
  $this->assertSame(2,DB::connection('saas_central')->table('saas_events')->count());
 }
 public function test_provision_gate_and_duplicate_request():void{
  $id=$this->createTenant();$this->signed('POST','tenants/'.$id.'/provision',[],(string)Str::uuid())->assertStatus(202);$this->signed('POST','tenants/'.$id.'/provision',[],(string)Str::uuid())->assertConflict();
  config(['saas_control.provision_enabled'=>false]);$this->signed('POST','tenants',[],(string)Str::uuid())->assertConflict();
 }
 public function test_dedicated_context_preserves_legacy_configuration():void{
  $id=$this->createTenant();DB::connection('saas_central')->table('saas_tenants')->where('id',$id)->update(['status'=>'active','installation_type'=>'dedicated','database_name'=>'sierra']);
  config(['saas.dedicated_tenant_id'=>$id,'saas.dedicated_hosts'=>['sierra.test','auth.sierra.test'],'database.connections.mysql.database'=>'sierra']);
  $snapshot=[config('database'),config('filesystems'),config('acesso'),config('services'),storage_path()];$reg=app(\App\Saas\TenantRegistry::class);$this->assertNull($reg->byHost('alpha.sierra.test'));$t=$reg->byHost('auth.sierra.test');$context=app(\App\Saas\TenantContext::class);$context->enter($t);$this->assertTrue($context->allows('vendas'));$context->leave();$this->assertSame($snapshot,[config('database'),config('filesystems'),config('acesso'),config('services'),storage_path()]);
 }
 public function test_error_responses_roll_back_partial_writes():void{
  Route::middleware(['api',\App\Saas\ControlAuth::class,\App\Saas\ControlIdempotency::class])->post('api/v1/control/fault',function(){DB::connection('saas_central')->table('saas_events')->insert(['action'=>'must.rollback','created_at'=>now(),'updated_at'=>now()]);return response()->json(['message'=>'failure'],500);})->withoutMiddleware(['throttle:api',\App\Saas\RequireModule::class]);
  $this->signed('POST','fault',[],(string)Str::uuid())->assertStatus(500);$this->assertSame(0,DB::connection('saas_central')->table('saas_events')->count());$this->assertSame('failed',DB::connection('saas_central')->table('saas_control_operations')->value('state'));
 }

 public function test_invitation_is_sent_once_and_uncertain_delivery_is_not_repeated():void {
  $id=$this->createTenant();DB::connection('saas_central')->table('saas_tenants')->where('id',$id)->update(['status'=>'active','installation_type'=>'dedicated','database_name'=>'sierra']);config(['saas.dedicated_tenant_id'=>$id,'database.connections.mysql.database'=>'sierra']);$context=app(\App\Saas\TenantContext::class);$context->enter(app(\App\Saas\TenantRegistry::class)->byId($id));
  \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')->once()->andReturn(\Illuminate\Support\Facades\Password::RESET_LINK_SENT);
  $this->assertSame(0,\Illuminate\Support\Facades\Artisan::call('saas:invite-admin'));$this->assertSame(0,\Illuminate\Support\Facades\Artisan::call('saas:invite-admin'));$context->leave();
 }
 public function test_uncertain_invitation_requires_review():void {
  $id=$this->createTenant();DB::connection('saas_central')->table('saas_tenants')->where('id',$id)->update(['status'=>'active','installation_type'=>'dedicated','database_name'=>'sierra']);config(['saas.dedicated_tenant_id'=>$id,'database.connections.mysql.database'=>'sierra']);$context=app(\App\Saas\TenantContext::class);$context->enter(app(\App\Saas\TenantRegistry::class)->byId($id));
  \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('SMTP timeout'));
  $this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('saas:invite-admin'));$this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('saas:invite-admin'));$context->leave();
 }
 public function test_shared_runtime_rejects_dedicated_tenant():void {
  $id=$this->createTenant();DB::connection('saas_central')->table('saas_tenants')->where('id',$id)->update(['installation_type'=>'dedicated']);config(['saas.base_domain'=>'sierra.test']);$registry=app(\App\Saas\TenantRegistry::class);$this->assertNull($registry->byId($id));$this->assertNull($registry->byHost('alpha.sierra.test'));
 }
 public function test_custom_domains_resolve_and_canonical_domain_controls_urls():void {
  $payload=['name'=>'Alpha','slug'=>'alpha','admin_name'=>'Gestor','admin_email'=>'gestor@example.test','modules'=>['estoque'],
   'domains'=>[['host'=>'alpha.sierra.test','canonical'=>true,'active'=>true],['host'=>'erp.alpha.example','canonical'=>false,'active'=>false],['host'=>'alias.alpha.example','canonical'=>false,'active'=>false]]];
  $tenant=$this->signed('POST','tenants',$payload,(string)Str::uuid())->assertCreated()
   ->assertJsonPath('tenant.url','https://alpha.sierra.test:5173')->assertJsonCount(3,'tenant.domains')->json('tenant');
  $registry=app(\App\Saas\TenantRegistry::class);
  $this->assertNull($registry->byHost('ALIAS.ALPHA.EXAMPLE.'));
  $custom=collect($tenant['domains'])->firstWhere('host','erp.alpha.example');
  $this->signed('POST','tenants/'.$tenant['id'].'/domains/'.$custom['id'].'/approve',['reason'=>'DNS e TLS validados'],(string)Str::uuid())
   ->assertOk()->assertJsonPath('domain.verification_status','verified')->assertJsonPath('domain.active',1);
  DB::connection('saas_central')->table('saas_tenants')->where('id',$tenant['id'])->update(['status'=>'active','provisioned_at'=>now()]);
  $updatedDomains=collect($this->signed('GET','tenants/'.$tenant['id'])->assertOk()->json('tenant.domains'))->map(fn($domain)=>[
   'host'=>$domain['host'],'canonical'=>$domain['host']==='erp.alpha.example','active'=>$domain['host']!=='alias.alpha.example'])->values()->all();
  $this->signed('PATCH','tenants/'.$tenant['id'],['version'=>1,'domains'=>$updatedDomains,'reason'=>'Tornar domínio aprovado canônico'],(string)Str::uuid())
   ->assertOk()->assertJsonPath('tenant.url','https://erp.alpha.example:5173');
  $this->assertSame($tenant['id'],$registry->byHost('ERP.ALPHA.EXAMPLE.')?->id);
  $this->assertSame('erp.alpha.example',$registry->byHost('alpha.sierra.test')?->canonical_host);
  $this->assertNull($registry->byHost('unknown.example'));
  $this->assertNull($registry->byHost('alias.alpha.example'));
  $duplicate=$payload;$duplicate['slug']='beta';$duplicate['name']='Beta';
  $this->signed('POST','tenants',$duplicate,(string)Str::uuid())->assertUnprocessable();
 }

 public function test_custom_domain_requires_approval_and_can_be_rejected():void {
  $payload=['name'=>'Alpha','slug'=>'alpha','admin_name'=>'Gestor','admin_email'=>'gestor@example.test','modules'=>['estoque'],
   'domains'=>[['host'=>'alpha.sierra.test','canonical'=>true,'active'=>true],['host'=>'erp.alpha.example','canonical'=>false,'active'=>true]]];
  $this->signed('POST','tenants',$payload,(string)Str::uuid())->assertUnprocessable();
  $payload['domains'][1]['active']=false;
  $tenant=$this->signed('POST','tenants',$payload,(string)Str::uuid())->assertCreated()->json('tenant');
  $custom=collect($tenant['domains'])->firstWhere('host','erp.alpha.example');
  $this->signed('POST','tenants/'.$tenant['id'].'/domains/'.$custom['id'].'/reject',['reason'=>'DNS não autorizado'],(string)Str::uuid())
   ->assertOk()->assertJsonPath('domain.verification_status','rejected')->assertJsonPath('domain.active',0);
 }

 public function test_default_https_port_is_omitted_from_tenant_url():void {
  config(['saas.url_port'=>443]);
  $id=$this->createTenant();
  $this->signed('GET','tenants/'.$id)->assertOk()->assertJsonPath('tenant.url','https://alpha.sierra.test');
 }
}
