<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use App\Saas\TenantContext;
final class SaasInviteAdmin extends Command {
 protected $signature='saas:invite-admin';
 protected $description='Send the initial invitation once; uncertain delivery requires review';
 public function handle(TenantContext $context):int {
  $tenant=$context->tenant();if(!$tenant||!in_array($tenant->status,['provisioning','active'],true))return 1;
  $db=DB::connection('saas_central');
  if(!$db->table('saas_invitations')->insertOrIgnore(['tenant_id'=>$tenant->id,'state'=>'attempted','attempted_at'=>now()])){
   return $db->table('saas_invitations')->where('tenant_id',$tenant->id)->value('state')==='sent'?0:1;
  }
  try{$result=Password::sendResetLink(['email'=>$tenant->admin_email]);}
  catch(\Throwable $e){$this->error('Delivery requires review; no automatic retry.');return 1;}
  if($result!==Password::RESET_LINK_SENT)return 1;
  $db->table('saas_invitations')->where('tenant_id',$tenant->id)->update(['state'=>'sent','sent_at'=>now()]);return 0;
 }
}
