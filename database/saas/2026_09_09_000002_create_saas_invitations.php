<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 protected $connection='saas_central';
 public function up():void{Schema::connection($this->connection)->create('saas_invitations',function(Blueprint $t){$t->uuid('tenant_id')->primary();$t->string('state',20);$t->timestamp('attempted_at');$t->timestamp('sent_at')->nullable();});}
 public function down():void{Schema::connection($this->connection)->dropIfExists('saas_invitations');}
};
