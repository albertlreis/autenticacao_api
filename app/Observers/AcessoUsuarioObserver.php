<?php

namespace App\Observers;

use App\Models\AcessoUsuario;
use Illuminate\Support\Facades\Cache;

class AcessoUsuarioObserver
{
    /**
     * Dispara ao salvar (criar ou atualizar) um usuário.
     * Limpa o cache de permissões relacionado.
     */
    public function saved(AcessoUsuario $usuario): void
    {
        $this->limparCache($usuario);
    }

    /**
     * Dispara ao excluir um usuário.
     */
    public function deleted(AcessoUsuario $usuario): void
    {
        $this->limparCache($usuario);
    }

    /**
     * Método utilitário para limpar cache de permissões do usuário.
     */
    protected function limparCache(AcessoUsuario $usuario): void
    {
        Cache::forget('permissoes_usuario_' . $usuario->id);
    }
}
