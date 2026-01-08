<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcessoPermissoesTable extends Migration
{
    public function up(): void
    {
        Schema::create('acesso_permissoes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique(); // Ex: 'clientes.visualizar'
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acesso_permissoes');
    }
}
