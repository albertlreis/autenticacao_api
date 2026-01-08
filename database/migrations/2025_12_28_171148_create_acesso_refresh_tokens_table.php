<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acesso_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')
                ->constrained('acesso_usuarios')
                ->cascadeOnDelete();

            $table->char('token_hash', 64)->unique(); // sha256
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->string('created_ip', 45)->nullable();
            $table->string('created_user_agent', 255)->nullable();

            $table->foreignId('replaced_by_id')
                ->nullable()
                ->constrained('acesso_refresh_tokens')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['usuario_id', 'revoked_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acesso_refresh_tokens');
    }
};
