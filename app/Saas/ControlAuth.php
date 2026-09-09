<?php
namespace App\Saas;
use Closure;
use Illuminate\Support\Facades\DB;
final class ControlAuth {
 public static function canonical($r): string {return implode("\n",[$r->method(),$r->getRequestUri(),hash('sha256',$r->getContent()),$r->header('X-Sierra-Actor'),$r->header('X-Sierra-Time'),$r->header('X-Sierra-Nonce'),$r->header('Idempotency-Key','')]);}
 public function handle($r,Closure $next) {
  abort_unless(config('saas.enabled') && config('saas_control.enabled') && $r->getHost()===config('saas_control.host'),404);
  $key=config('saas_control.keys.'.$r->header('X-Sierra-Key'));
  abort_unless(is_array($key) && in_array('sierra:manage',$key['scopes']??[],true) && strlen($key['secret']??'')>=32,403);
  $time=$r->header('X-Sierra-Time','');$nonce=$r->header('X-Sierra-Nonce','');$actor=$r->header('X-Sierra-Actor','');
  abort_unless(ctype_digit($time)&&abs(time()-(int)$time)<=300&&preg_match('/^[a-f0-9]{64}$/D',$nonce)&&preg_match('/^webleap:[1-9][0-9]*$/D',$actor),403);
  abort_unless(hash_equals(hash_hmac('sha256',self::canonical($r),$key['secret']),$r->header('X-Sierra-Signature','')),403);
  $db=DB::connection('saas_central');
  $db->table('saas_control_nonces')->where('expires_at','<',now())->delete();
  abort_unless($db->table('saas_control_nonces')->insertOrIgnore(['id'=>$nonce,'expires_at'=>now()->addMinutes(10)])===1,403);
  $r->attributes->set('control_actor',$actor);
  return $next($r);
 }
}
