<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 protected $connection='saas_central';
 public function up(): void {
  $s=Schema::connection($this->connection);
  $s->table('saas_tenants',function(Blueprint $t){$t->unsignedBigInteger('contract_version')->default(1);$t->string('installation_type',20)->default('shared');$t->string('frontend_url')->nullable();});
  $s->create('saas_control_nonces',function(Blueprint $t){$t->string('id',64)->primary();$t->timestamp('expires_at')->index();});
  $s->create('saas_control_operations',function(Blueprint $t){$t->uuid('id')->primary();$t->string('actor',191);$t->string('fingerprint',64);$t->string('state',20)->default('requested');$t->unsignedSmallInteger('http_status')->nullable();$t->json('response')->nullable();$t->timestamps();});
  $s->create('saas_provision_steps',function(Blueprint $t){$t->id();$t->uuid('tenant_id')->index();$t->string('step',64);$t->string('state',20)->default('requested');$t->string('error_code')->nullable();$t->timestamps();$t->unique(['tenant_id','step']);});
 }
 public function down(): void { $s=Schema::connection($this->connection);foreach(['saas_provision_steps','saas_control_operations','saas_control_nonces'] as $t)$s->dropIfExists($t);$s->table('saas_tenants',function(Blueprint $t){$t->dropColumn(['contract_version','installation_type','frontend_url']);}); }
};
