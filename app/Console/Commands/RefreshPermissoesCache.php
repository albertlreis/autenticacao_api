<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\AcessoUsuario;

class RefreshPermissoesCache extends Command
{
    protected $signature = 'permissao:refresh-cache {usuarioId?}';
    protected $description = 'Limpa e reconstrói o cache de permissões para um usuário ou para todos os usuários.';

    public function handle(): int
    {
        $usuarioId = $this->argument('usuarioId');

        if ($usuarioId) {
            Cache::forget('permissoes_usuario_' . $usuarioId);

            $usuario = AcessoUsuario::with('perfis.permissoes')->find($usuarioId);

            if (!$usuario) {
                $this->warn("Usuário ID {$usuarioId} não encontrado.");
                return self::FAILURE;
            }

            $permissoes = $usuario->perfis
                ->flatMap(fn($perfil) => $perfil->permissoes)
                ->pluck('slug')
                ->unique()
                ->values()
                ->toArray();

            Cache::put('permissoes_usuario_' . $usuario->id, $permissoes, now()->addHours(6));
            $this->info("🔄 Cache de permissões atualizado para o usuário ID {$usuarioId}.");
            return self::SUCCESS;
        }

        $usuarios = AcessoUsuario::with('perfis.permissoes')->get();
        $total = 0;

        foreach ($usuarios as $usuario) {
            Cache::forget('permissoes_usuario_' . $usuario->id);

            $permissoes = $usuario->perfis
                ->flatMap(fn($perfil) => $perfil->permissoes)
                ->pluck('slug')
                ->unique()
                ->values()
                ->toArray();

            Cache::put('permissoes_usuario_' . $usuario->id, $permissoes, now()->addHours(6));
            $total++;
        }

        $this->info("🔄 Cache de permissões limpo e reconstruído para {$total} usuário(s).");
        return self::SUCCESS;
    }
}
