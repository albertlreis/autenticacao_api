<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAcessoUsuariosTable extends Migration
{
    public function up(): void
    {
        Schema::create('acesso_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->string('email', 100)->unique();
            $table->string('senha', 255);
            $table->boolean('ativo')->default(true);

            $table->timestamp('ultimo_login_em')->nullable();
            $table->string('ultimo_login_ip', 45)->nullable();
            $table->string('ultimo_login_user_agent', 255)->nullable();

            $table->unsignedSmallInteger('tentativas_login')->default(0);
            $table->timestamp('bloqueado_ate')->nullable();

            $table->timestamp('senha_alterada_em')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        Schema::dropIfExists('acesso_usuarios');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
