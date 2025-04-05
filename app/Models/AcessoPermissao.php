<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcessoPermissao extends Model
{
    protected $table = 'acesso_permissoes';

    protected $fillable = [
        'nome', 'descricao'
    ];

    // Relação com perfis (muitos para muitos)
    public function perfis()
    {
        return $this->belongsToMany(AcessoPerfil::class, 'acesso_perfil_permissao', 'id_permissao', 'id_perfil')
            ->withTimestamps();
    }
}
