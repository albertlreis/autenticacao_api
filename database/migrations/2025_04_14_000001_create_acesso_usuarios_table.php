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
            $table->increments('id');
            $table->string('nome', 255);
            $table->string('email', 100)->unique();
            $table->string('senha', 255);
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        Schema::dropIfExists('acesso_usuarios');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
