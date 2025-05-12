<?php

namespace App\Observers;

use App\Models\AcessoPerfil;
use Illuminate\Support\Facades\Cache;

class AcessoPerfilObserver
{
    /**
     * Dispara ao excluir um perfil.
     * Remove o cache de todos os usuários vinculados.
     */
    public function deleted(AcessoPerfil $perfil): void
    {
        $this->limparCacheUsuariosVinculados($perfil);
    }

    /**
     * Utilitário: limpa cache de todos os usuários que possuem este perfil.
     */
    protected function limparCacheUsuariosVinculados(AcessoPerfil $perfil): void
    {
        $perfil->loadMissing('usuarios');

        foreach ($perfil->usuarios as $usuario) {
            Cache::forget('permissoes_usuario_' . $usuario->id);
        }
    }
}
