<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcessoPerfil extends Model
{
    protected $table = 'acesso_perfis';

    protected $fillable = [
        'nome', 'descricao'
    ];

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
