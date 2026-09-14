<?php
namespace App\Saas;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
final class ControlIdempotency {
 public function handle($r,Closure $next) {
  if(in_array($r->method(),['GET','HEAD'],true))return $next($r);
  $id=$r->header('Idempotency-Key','');abort_unless(preg_match('/^[a-f0-9-]{36}$/D',$id),422,'Identificador da operação obrigatório.');
  $db=DB::connection('saas_central');$actor=$r->attributes->get('control_actor');$fingerprint=hash('sha256',$r->method().' '.$r->getRequestUri().' '.$r->getContent());
  $claim=$db->transaction(function()use($db,$id,$actor,$fingerprint){
   $created=$db->table('saas_control_operations')->insertOrIgnore(['id'=>$id,'actor'=>$actor,'fingerprint'=>$fingerprint,'state'=>'requested','created_at'=>now(),'updated_at'=>now()])===1;
   $op=$db->table('saas_control_operations')->where('id',$id)->lockForUpdate()->first();
   abort_unless($op->actor===$actor&&hash_equals($op->fingerprint,$fingerprint),409,'Identificador já utilizado para outra operação.');
   if($op->response!==null)return response()->json(json_decode($op->response,true),$op->http_status);
   if(!$created&&in_array($op->state,['running','uncertain'],true))return response()->json(['operation_id'=>$id,'state'=>$op->state],202);
   $db->table('saas_control_operations')->where('id',$id)->update(['state'=>'running','updated_at'=>now()]);
   return null;
  });
  if($claim)return $claim;
  $state='failed';
  try{$response=$db->transaction(function()use($next,$r){$response=$next($r);if($response->getStatusCode()>=400)throw new ControlRejected($response);return $response;});}
  catch(ControlRejected $e){$response=$e->response;if($response->getStatusCode()>=500)$state='uncertain';}
  catch(ValidationException $e){$response=response()->json(['message'=>'Dados inválidos.','errors'=>$e->errors()],422);}
  catch(HttpExceptionInterface $e){$response=response()->json(['message'=>$e->getMessage()?:'Operação não permitida.'],$e->getStatusCode());}
  catch(\Throwable $e){report($e);$state='uncertain';$response=response()->json(['message'=>'Operação não concluída. Consulte o histórico operacional.'],500);}
  $code=$response->getStatusCode();$body=$code>=500?['message'=>'Operação não concluída. Consulte o histórico operacional.']:(json_decode($response->getContent(),true)??[]);$body['operation_id']=$id;
  $db->table('saas_control_operations')->where('id',$id)->where('state','running')->update(['state'=>$state==='uncertain'?'uncertain':($code<400?'completed':'failed'),'http_status'=>$code,'response'=>json_encode($body),'updated_at'=>now()]);
  return response()->json($body,$code);
 }
}
