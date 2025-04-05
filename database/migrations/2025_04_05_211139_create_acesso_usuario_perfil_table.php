<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcessoUsuarioPerfilTable extends Migration
{
    public function up()
    {
        Schema::create('acesso_usuario_perfil', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_usuario');
            $table->unsignedInteger('id_perfil');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_usuario')->references('id')->on('acesso_usuarios')->onDelete('cascade');
            $table->foreign('id_perfil')->references('id')->on('acesso_perfis')->onDelete('cascade');
            $table->unique(['id_usuario', 'id_perfil'], 'uq_usuario_perfil');
        });
    }

    public function down()
    {
        Schema::table('acesso_usuario_perfil', function (Blueprint $table) {
            $table->dropForeign(['id_usuario']);
            $table->dropForeign(['id_perfil']);
        });
        Schema::dropIfExists('acesso_usuario_perfil');
    }
}
