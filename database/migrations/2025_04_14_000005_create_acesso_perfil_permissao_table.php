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
            $table->timestamps();

            $table->foreign('id_perfil')->references('id')->on('acesso_perfis')->onDelete('cascade');
            $table->foreign('id_permissao')->references('id')->on('acesso_permissoes')->onDelete('cascade');

            $table->unique(['id_perfil', 'id_permissao'], 'uq_perfil_permissao');
        });
    }

    public function down()
    {
        Schema::dropIfExists('acesso_perfil_permissao');
    }
}
