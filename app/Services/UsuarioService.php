<?php

namespace App\Services;

use App\Models\AcessoUsuario;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function __construct(
        private readonly PermissoesCacheService $permissoesCache
    ) {}

    /**
     * Cria um usuário e opcionalmente sincroniza perfis.
     *
     * @param  array{
     *   nome:string,
     *   email:string,
     *   senha:string,
     *   ativo?:bool,
     *   perfis?:array<int,int>
     * }  $data
     * @return AcessoUsuario
     */
    public function criar(array $data): AcessoUsuario
    {
        $usuario = AcessoUsuario::create([
            'nome'  => $data['nome'],
            'email' => $data['email'],
            'senha' => Hash::make($data['senha']),
            'ativo' => array_key_exists('ativo', $data) ? (bool) $data['ativo'] : true,
            'senha_alterada_em' => now(),
        ]);

        if (array_key_exists('perfis', $data)) {
            $usuario->perfis()->sync($data['perfis'] ?? []);
        }

        $this->permissoesCache->forget((int) $usuario->id);

        return $usuario->load('perfis');
    }

    /**
     * Atualiza usuário e opcionalmente sincroniza perfis.
     *
     * @param AcessoUsuario $usuario
     * @param array $data
     * @return AcessoUsuario
     */
    public function atualizar(AcessoUsuario $usuario, array $data): AcessoUsuario
    {
        if (array_key_exists('nome', $data)) $usuario->nome = $data['nome'];
        if (array_key_exists('email', $data)) $usuario->email = $data['email'];
        if (array_key_exists('ativo', $data)) $usuario->ativo = (bool) $data['ativo'];

        if (!empty($data['senha'])) {
            $usuario->senha = Hash::make($data['senha']);
            $usuario->senha_alterada_em = now();
        }

        $usuario->save();

        if (array_key_exists('perfis', $data)) {
            $usuario->perfis()->sync($data['perfis'] ?? []);
        }

        $this->permissoesCache->forget((int) $usuario->id);

        return $usuario->load('perfis');
    }

    /**
     * Remove usuário e invalida permissões em cache.
     *
     * @param  AcessoUsuario  $usuario
     * @return void
     */
    public function remover(AcessoUsuario $usuario): void
    {
        $this->permissoesCache->forget((int) $usuario->id);
        $usuario->tokens()->delete();
        $usuario->delete();
    }

    /**
     * Adiciona perfis sem remover os existentes.
     *
     * @param  AcessoUsuario  $usuario
     * @param  array<int,int>  $perfisIds
     * @return AcessoUsuario
     */
    public function adicionarPerfis(AcessoUsuario $usuario, array $perfisIds): AcessoUsuario
    {
        $usuario->perfis()->syncWithoutDetaching($perfisIds);
        $this->permissoesCache->forget((int) $usuario->id);
        return $usuario->load('perfis');
    }

    /**
     * Remove um perfil do usuário.
     *
     * @param  AcessoUsuario  $usuario
     * @param  int  $perfilId
     * @return AcessoUsuario
     */
    public function removerPerfil(AcessoUsuario $usuario, int $perfilId): AcessoUsuario
    {
        $usuario->perfis()->detach($perfilId);
        $this->permissoesCache->forget((int) $usuario->id);
        return $usuario->load('perfis');
    }
}
