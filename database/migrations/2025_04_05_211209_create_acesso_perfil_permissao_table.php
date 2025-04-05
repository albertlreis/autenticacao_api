<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcessoPerfilPermissaoTable extends Migration
{
    public function up()
    {
        Schema::create('acesso_perfil_permissao', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_perfil');
            $table->unsignedInteger('id_permissao');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('id_perfil')->references('id')->on('acesso_perfis')->onDelete('cascade');
            $table->foreign('id_permissao')->references('id')->on('acesso_permissoes')->onDelete('cascade');
            $table->unique(['id_perfil', 'id_permissao'], 'uq_perfil_permissao');
        });
    }

    public function down()
    {
        Schema::table('acesso_perfil_permissao', function (Blueprint $table) {
            $table->dropForeign(['id_perfil']);
            $table->dropForeign(['id_permissao']);
        });
        Schema::dropIfExists('acesso_perfil_permissao');
    }
}
