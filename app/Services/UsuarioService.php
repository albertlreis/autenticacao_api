<?php

namespace App\Services;

use App\Models\AcessoUsuario;
use App\Support\Auditoria\AuditoriaDiff;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    private const USER_AUDIT_FIELDS = [
        'nome',
        'email',
        'telefone',
        'cargo',
        'ativo',
        'forcar_troca_senha',
    ];

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
            'forcar_troca_senha' => array_key_exists('forcar_troca_senha', $data)
                ? (bool) $data['forcar_troca_senha']
                : true,
        ]);

        if (array_key_exists('perfis', $data)) {
            $usuario->perfis()->sync($data['perfis'] ?? []);
        }

        $this->permissoesCache->forget((int) $usuario->id);

        $usuario = $usuario->load('perfis');
        $mudancas = AuditoriaDiff::modelChanges(null, $usuario, self::USER_AUDIT_FIELDS);
        $mudancas = array_merge(
            $mudancas,
            AuditoriaDiff::listChange('perfis', [], $this->perfilNames($usuario))
        );

        $this->registrarAuditoriaUsuario('usuario.created', $usuario, 'Usuario criado', $mudancas);

        return $usuario;
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
        $before = $usuario->fresh(['perfis']);
        $perfisAntes = $this->perfilNames($before);
        $senhaAlterada = !empty($data['senha']);

        if (array_key_exists('nome', $data)) $usuario->nome = $data['nome'];
        if (array_key_exists('email', $data)) $usuario->email = $data['email'];
        if (array_key_exists('ativo', $data)) $usuario->ativo = (bool) $data['ativo'];
        if (array_key_exists('forcar_troca_senha', $data)) {
            $usuario->forcar_troca_senha = (bool) $data['forcar_troca_senha'];
        }

        if (!empty($data['senha'])) {
            $usuario->senha = Hash::make($data['senha']);
            $usuario->senha_alterada_em = now();
            $usuario->forcar_troca_senha = array_key_exists('forcar_troca_senha', $data)
                ? (bool) $data['forcar_troca_senha']
                : false;
        }

        $usuario->save();

        if (array_key_exists('perfis', $data)) {
            $usuario->perfis()->sync($data['perfis'] ?? []);
        }

        $this->permissoesCache->forget((int) $usuario->id);

        $usuario = $usuario->load('perfis');
        $mudancas = AuditoriaDiff::modelChanges($before, $usuario, self::USER_AUDIT_FIELDS);
        if ($senhaAlterada) {
            $mudancas[] = [
                'campo' => 'senha',
                'old' => '[REDACTED]',
                'new' => '[REDACTED]',
                'value_type' => 'string',
            ];
        }

        $mudancas = array_merge(
            $mudancas,
            AuditoriaDiff::listChange('perfis', $perfisAntes, $this->perfilNames($usuario))
        );

        $this->registrarAuditoriaUsuario('usuario.updated', $usuario, 'Usuario atualizado', $mudancas);

        return $usuario;
    }

    /**
     * Remove usuário e invalida permissões em cache.
     *
     * @param  AcessoUsuario  $usuario
     * @return void
     */
    public function remover(AcessoUsuario $usuario): void
    {
        $before = $usuario->fresh(['perfis']);
        $mudancas = AuditoriaDiff::modelChanges($before, null, self::USER_AUDIT_FIELDS);
        $mudancas = array_merge(
            $mudancas,
            AuditoriaDiff::listChange('perfis', $this->perfilNames($before), [])
        );

        $this->permissoesCache->forget((int) $usuario->id);
        $usuario->tokens()->delete();
        $usuario->delete();

        $this->registrarAuditoriaUsuario('usuario.deleted', $before, 'Usuario removido', $mudancas);
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
        $before = $usuario->fresh(['perfis']);
        $perfisAntes = $this->perfilNames($before);
        $usuario->perfis()->syncWithoutDetaching($perfisIds);
        $this->permissoesCache->forget((int) $usuario->id);
        $usuario = $usuario->load('perfis');

        $this->registrarAuditoriaUsuario(
            'perfil.attached',
            $usuario,
            'Perfil atribuido ao usuario',
            AuditoriaDiff::listChange('perfis', $perfisAntes, $this->perfilNames($usuario))
        );

        return $usuario;
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
        $before = $usuario->fresh(['perfis']);
        $perfisAntes = $this->perfilNames($before);
        $usuario->perfis()->detach($perfilId);
        $this->permissoesCache->forget((int) $usuario->id);
        $usuario = $usuario->load('perfis');

        $this->registrarAuditoriaUsuario(
            'perfil.detached',
            $usuario,
            'Perfil removido do usuario',
            AuditoriaDiff::listChange('perfis', $perfisAntes, $this->perfilNames($usuario))
        );

        return $usuario;
    }

    /**
     * @return array<int,string>
     */
    private function perfilNames(?AcessoUsuario $usuario): array
    {
        if (!$usuario) {
            return [];
        }

        $perfis = $usuario->relationLoaded('perfis')
            ? $usuario->perfis
            : $usuario->perfis()->get();

        return $perfis->pluck('nome')->filter()->values()->all();
    }

    /**
     * @param array<int,array{campo:string,old?:mixed,new?:mixed,old_value?:mixed,new_value?:mixed,value_type?:string}> $mudancas
     */
    private function registrarAuditoriaUsuario(string $acao, AcessoUsuario $usuario, string $label, array $mudancas): void
    {
        app(AuditoriaLogService::class)->registrar([
            'occurred_at' => now(),
            'tipo' => 'auditoria',
            'categoria' => 'negocio',
            'modulo' => 'acessos',
            'acao' => $acao,
            'label' => $label,
            'message' => $label,
            'entity_type' => AcessoUsuario::class,
            'entity_id' => $usuario->id,
            'source_system' => 'auth',
            'source_kind' => 'business_event',
            'retention_days' => 365,
        ], $mudancas);
    }
}
