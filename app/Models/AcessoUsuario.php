<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AcessoUsuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'acesso_usuarios';

    protected $fillable = [
        'nome',
        'email',
        'senha',
        'ativo',
        'ultimo_login_em',
        'ultimo_login_ip',
        'ultimo_login_user_agent',
        'tentativas_login',
        'bloqueado_ate',
        'senha_alterada_em',
    ];

    protected $hidden = [
        'senha',
    ];

    protected $casts = [
        'ativo'           => 'boolean',
        'ultimo_login_em' => 'datetime',
        'bloqueado_ate'   => 'datetime',
        'senha_alterada_em' => 'datetime',
    ];

    public function perfis()
    {
        return $this->belongsToMany(AcessoPerfil::class, 'acesso_usuario_perfil', 'id_usuario', 'id_perfil')
            ->withTimestamps();
    }

    // Para compatibilizar Auth::attempt etc, mantendo coluna 'senha'
    public function getAuthPassword(): string
    {
        return (string) $this->senha;
    }

    public function isBloqueado(): bool
    {
        return $this->bloqueado_ate !== null && $this->bloqueado_ate->isFuture();
    }
}
