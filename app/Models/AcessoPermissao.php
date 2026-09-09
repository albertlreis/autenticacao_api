<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcessoPermissao extends Model
{
    protected $table = 'acesso_permissoes';

    protected $fillable = [
        'slug',
        'nome',
        'descricao',
    ];

    public function perfis()
    {
        return $this->belongsToMany(AcessoPerfil::class, 'acesso_perfil_permissao', 'id_permissao', 'id_perfil')
            ->withTimestamps();
    }
    public function toArray(): array
    {
        $data = parent::toArray();
        if (\App\Saas\TenantAccess::enabled()) {
            $available = \App\Saas\TenantAccess::permissionAllowed($this->slug);
            $data += ['disponivel' => $available, 'modulo' => config('saas_permissions', [])[$this->slug] ?? null,
                'motivo_bloqueio' => $available ? null : (in_array($this->slug, \App\Saas\TenantAccess::TECHNICAL, true) ? 'Reservado ao suporte' : 'Módulo não contratado ou permissão não classificada')];
        }
        return $data;
    }
}
