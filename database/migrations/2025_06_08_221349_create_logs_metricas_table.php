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

            $table->string('chave', 191);
            $table->string('origem', 191);  // dashboard_resumo, estatisticas, estoque
            $table->string('status', 191);  // cache_hit, cache_miss

            $table->foreignId('usuario_id')->nullable()
                ->constrained('acesso_usuarios')
                ->nullOnDelete()
                ->restrictOnUpdate();

            $table->decimal('duracao_ms', 10, 2);

            $table->timestamp('criado_em')->useCurrent();

            $table->index(['origem', 'status', 'criado_em'], 'ix_logs_metricas_o_s_t');
            $table->index('criado_em', 'ix_logs_metricas_criado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_metricas');
    }
};
