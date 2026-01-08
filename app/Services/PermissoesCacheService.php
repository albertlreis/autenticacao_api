<?php

namespace App\Services;

use App\Models\AcessoUsuario;
use Illuminate\Support\Facades\Cache;

class PermissoesCacheService
{
    public function key(int $usuarioId): string
    {
        return 'permissoes_usuario_' . $usuarioId;
    }

    public function get(AcessoUsuario $usuario): array
    {
        $ttlHours = (int) config('acesso.permissions_cache_ttl_hours', 6);
        $key = $this->key((int) $usuario->getKey());

        return Cache::remember($key, now()->addHours($ttlHours), function () use ($usuario) {
            return $usuario->perfis()
                ->with('permissoes')
                ->get()
                ->pluck('permissoes')
                ->flatten()
                ->pluck('slug')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        });
    }

    public function forget(int $usuarioId): void
    {
        Cache::forget($this->key($usuarioId));
    }

    public function forgetByPerfilId(int $perfilId): void
    {
        $usuariosIds = \App\Models\AcessoUsuario::whereHas('perfis', function ($q) use ($perfilId) {
            $q->where('acesso_perfis.id', $perfilId);
        })->pluck('id')->all();

        foreach ($usuariosIds as $uid) {
            $this->forget((int) $uid);
        }
    }
}
