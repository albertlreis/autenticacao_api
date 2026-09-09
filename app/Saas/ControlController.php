<?php
namespace App\Saas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class ControlController {
 private function db(){return DB::connection('saas_central');}
 public function index(){return response()->json(['catalog'=>ModuleCatalog::MODULES,'can_provision'=>(bool)config('saas_control.provision_enabled'),'tenants'=>$this->db()->table('saas_tenants')->orderBy('name')->get()->map(fn($t)=>$this->payload($t))]);}
 public function show(string $id){$t=$this->tenant($id);return response()->json(['tenant'=>$this->payload($t),'events'=>$this->db()->table('saas_events')->where('tenant_id',$id)->orderByDesc('id')->limit(100)->get(),'steps'=>$this->db()->table('saas_provision_steps')->where('tenant_id',$id)->orderBy('id')->get()]);}
 public function operation(Request $r,string $id){$op=$this->db()->table('saas_control_operations')->where('id',$id)->where('actor',$r->attributes->get('control_actor'))->first();abort_unless($op,404);return response()->json(['id'=>$op->id,'state'=>$op->state,'http_status'=>$op->http_status,'response'=>json_decode($op->response??'null',true)]);}
 public function store(Request $r){
  abort_unless(config('saas_control.provision_enabled'),409,'Criação de empresas ainda não habilitada.');
  $d=$r->validate(['name'=>'required|string|max:191','slug'=>['required','string','max:63','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],'admin_name'=>'required|string|max:191','admin_email'=>'required|email|max:100','modules'=>'present|array','modules.*'=>'string|distinct']);
  $d['modules']=$this->modules($d['modules']);
  if(in_array($d['slug'],config('saas_control.reserved_slugs'),true)||$this->db()->table('saas_tenants')->where('slug',$d['slug'])->exists())throw ValidationException::withMessages(['slug'=>'Subdomínio reservado ou já cadastrado.']);
  $id=(string)Str::uuid();$d['admin_email']=strtolower($d['admin_email']);$d['modules']=json_encode($d['modules']);
  $this->db()->table('saas_tenants')->insert($d+['id'=>$id,'database_name'=>'sierra_'.str_replace('-','',$id),'connection_profile'=>$id,'installation_type'=>'shared','status'=>'pending','contract_version'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $this->event($r,$id,'tenant.created',null,$this->payload($this->tenant($id)));return response()->json(['tenant'=>$this->payload($this->tenant($id))],201);
 }
 public function update(Request $r,string $id){
  $d=$r->validate(['version'=>'required|integer|min:1','modules'=>'sometimes|array','modules.*'=>'string|distinct','status'=>'sometimes|in:active,suspended','reason'=>'required|string|max:500']);
  abort_unless(array_key_exists('modules',$d)||isset($d['status']),422,'Nenhuma alteração informada.');
  if(array_key_exists('modules',$d))$d['modules']=$this->modules($d['modules']);
  $t=$this->db()->table('saas_tenants')->where('id',$id)->lockForUpdate()->first();abort_unless($t,404);
  abort_unless((int)$t->contract_version===$d['version'],409,'Contrato alterado por outro administrador. Atualize a página.');
  abort_unless(in_array($t->status,['active','suspended'],true)&&$t->provisioned_at,409,'Ambiente ainda não preparado.');
  $changes=['contract_version'=>$t->contract_version+1,'updated_at'=>now()];if(isset($d['status']))$changes['status']=$d['status'];if(isset($d['modules']))$changes['modules']=json_encode($d['modules']);
  $this->db()->table('saas_tenants')->where('id',$id)->update($changes);
  $after=$this->payload($this->tenant($id));$this->event($r,$id,'tenant.updated',$this->payload($t),$after,$d['reason']);return response()->json(['tenant'=>$after]);
 }
 public function provision(Request $r,string $id){
  abort_unless(config('saas_control.provision_enabled'),409,'Provisionamento ainda não habilitado.');$t=$this->db()->table('saas_tenants')->where('id',$id)->lockForUpdate()->first();abort_unless($t,404);
  abort_unless($t->installation_type==='shared'&&in_array($t->status,['pending','failed'],true)&&!$t->provision_requested_at,409,'Preparação já solicitada ou não permitida.');
  $this->db()->table('saas_tenants')->where('id',$id)->update(['provision_requested_at'=>now(),'updated_at'=>now()]);$this->event($r,$id,'provision.requested',null,null);return response()->json(['state'=>'requested'],202);
 }
 private function tenant($id){$t=$this->db()->table('saas_tenants')->where('id',$id)->first();abort_unless($t,404);return $t;}
 private function modules(array $m):array{try{return ModuleCatalog::validate($m);}catch(\InvalidArgumentException $e){throw ValidationException::withMessages(['modules'=>$e->getMessage()]);}}
 private function payload($t):array{return ['id'=>$t->id,'name'=>$t->name,'slug'=>$t->slug,'status'=>$t->status,'modules'=>json_decode($t->modules,true),'version'=>(int)$t->contract_version,'installation_type'=>$t->installation_type,'url'=>$t->frontend_url?:config('saas.scheme').'://'.$t->slug.'.'.config('saas.base_domain'),'provision_requested'=>(bool)$t->provision_requested_at];}
 private function event($r,$id,$action,$before,$after,$reason=null):void{$this->db()->table('saas_events')->insert(['tenant_id'=>$id,'action'=>$action,'details'=>json_encode(['actor'=>$r->attributes->get('control_actor'),'operation_id'=>$r->header('Idempotency-Key'),'before'=>$before,'after'=>$after,'reason'=>$reason]),'created_at'=>now(),'updated_at'=>now()]);}
}
