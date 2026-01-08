<?php

namespace App\Console\Commands;

use App\Models\AcessoUsuario;
use App\Services\PermissoesCacheService;
use Illuminate\Console\Command;

class RefreshPermissoesCache extends Command
{
    protected $signature = 'permissao:refresh-cache {usuarioId?}';
    protected $description = 'Limpa e reconstrói o cache de permissões para um usuário ou para todos.';

    public function __construct(private readonly PermissoesCacheService $cache)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $usuarioId = $this->argument('usuarioId');

        if ($usuarioId) {
            $usuario = AcessoUsuario::find((int) $usuarioId);
            if (!$usuario) {
                $this->warn("Usuário ID {$usuarioId} não encontrado.");
                return self::FAILURE;
            }

            $this->cache->forget((int) $usuario->id);
            $this->cache->get($usuario);

            $this->info("🔄 Cache atualizado para o usuário ID {$usuario->id}.");
            return self::SUCCESS;
        }

        $usuarios = AcessoUsuario::all();
        foreach ($usuarios as $u) {
            $this->cache->forget((int) $u->id);
            $this->cache->get($u);
        }

        $this->info("🔄 Cache atualizado para {$usuarios->count()} usuário(s).");
        return self::SUCCESS;
    }
}
