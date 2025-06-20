<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_metricas', function (Blueprint $table) {
            $table->id();
            $table->string('chave');
            $table->string('origem'); // dashboard_resumo, estatisticas, estoque
            $table->string('status'); // cache_hit, cache_miss
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->float('duracao_ms');
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_metricas');
    }
};
