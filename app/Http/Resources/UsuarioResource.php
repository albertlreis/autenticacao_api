<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AcessoUsuario
 */
class UsuarioResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array{
     *   id:int,
     *   nome:string,
     *   email:string,
     *   ativo:bool,
     *   created_at:?string,
     *   updated_at:?string,
     *   ultimo_login_at:?string,
     *   perfis?: array<int, array{id:int,nome:string,descricao:mixed}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'nome' => (string) $this->nome,
            'email' => (string) $this->email,
            'ativo' => (bool) $this->ativo,

            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),

            // mapeia "ultimo_login_em" -> "ultimo_login_at"
            'ultimo_login_at' => optional($this->ultimo_login_em)?->toISOString(),

            'perfis' => $this->whenLoaded('perfis', function () {
                return $this->perfis->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'nome' => (string) $p->nome,
                    'descricao' => $p->descricao,
                ])->values();
            }),
        ];
    }
}
