<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcessoUsuarioPerfilTable extends Migration
{
    public function up(): void
    {
        Schema::create('acesso_usuario_perfil', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_usuario')
                ->constrained('acesso_usuarios')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('id_perfil')
                ->constrained('acesso_perfis')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->timestamps();

            $table->unique(['id_usuario', 'id_perfil'], 'uq_usuario_perfil');
            $table->index('id_usuario');
            $table->index('id_perfil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acesso_usuario_perfil');
    }
}
