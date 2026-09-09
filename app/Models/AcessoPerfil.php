<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcessoPerfil extends Model
{
    protected $table = 'acesso_perfis';

    protected $fillable = [
        'nome', 'descricao'
    ];

    public function toArray(): array
    {
        $data = parent::toArray();
        if (!\App\Saas\TenantAccess::enabled()) return $data;
        $meta = \App\Saas\TenantAccess::metadata($this);
        return array_merge($data, $meta);
    }

    // Relação com usuários (muitos para muitos)
    public function usuarios()
    {
        return $this->belongsToMany(AcessoUsuario::class, 'acesso_usuario_perfil', 'id_perfil', 'id_usuario')
            ->withTimestamps();
    }

    // Relação com permissões (muitos para muitos)
    public function permissoes()
    {
        return $this->belongsToMany(AcessoPermissao::class, 'acesso_perfil_permissao', 'id_perfil', 'id_permissao')
            ->withTimestamps();
    }
}
