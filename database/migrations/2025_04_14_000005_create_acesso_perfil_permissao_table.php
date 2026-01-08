<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcessoPerfilPermissaoTable extends Migration
{
    public function up(): void
    {
        Schema::create('acesso_perfil_permissao', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_perfil')
                ->constrained('acesso_perfis')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('id_permissao')
                ->constrained('acesso_permissoes')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->timestamps();

            $table->unique(['id_perfil', 'id_permissao'], 'uq_perfil_permissao');
            $table->index('id_perfil');
            $table->index('id_permissao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acesso_perfil_permissao');
    }
}
