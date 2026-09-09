<?php
namespace App\Saas;

use App\Models\AcessoPerfil;
use App\Models\AcessoPermissao;
use Illuminate\Validation\ValidationException;

final class AccessPolicy
{
    public static function authorize(string $slug): void
    {
        if (!TenantAccess::enabled()) return;
        abort_unless(auth()->check(), 401);
        abort_unless(in_array($slug, TenantAccess::permissions((int) auth()->id()), true), 403, 'Sem permissão para esta ação.');
    }
    public static function profiles(array $ids, array $existing = []): void
    {
        if (!TenantAccess::enabled()) return;
        $existing = array_map('intval', $existing);
        foreach (array_diff($existing, array_map('intval', $ids)) as $removed) {
            $profile = AcessoPerfil::find($removed);
            abort_if($profile && TenantAccess::code($profile) === 'desenvolvedor', 403, 'Reservado ao suporte.');
        }
        foreach ($ids as $id) {
            $profile = AcessoPerfil::find($id);
            if (!$profile) throw ValidationException::withMessages(['perfis' => 'Perfil inválido.']);
            if (in_array((int) $id, $existing, true)) continue;
            abort_if(TenantAccess::code($profile) === 'desenvolvedor', 403, 'Reservado ao suporte.');
            if (!TenantAccess::profileAllowed($profile)) throw ValidationException::withMessages(['perfis' => 'Perfil indisponível: módulo não contratado.']);
            if (!TenantAccess::code($profile) && !$profile->permissoes->contains(fn ($p) => TenantAccess::permissionAllowed($p->slug))) {
                throw ValidationException::withMessages(['perfis' => 'Perfil sem permissões disponíveis para esta empresa.']);
            }
        }
    }
    public static function permissions(array $ids, array $existing = []): void
    {
        if (!TenantAccess::enabled()) return;
        foreach ($ids as $id) {
            $permission = AcessoPermissao::find($id);
            if (!$permission) throw ValidationException::withMessages(['permissoes' => 'Permissão inválida.']);
            if (in_array((int) $id, array_map('intval', $existing), true)) continue;
            if (!TenantAccess::permissionAllowed($permission->slug)) throw ValidationException::withMessages(['permissoes' => 'Permissão indisponível para esta empresa.']);
        }
    }
    public static function profileWrite(?AcessoPerfil $profile, array $data): void
    {
        if (!TenantAccess::enabled()) return;
        abort_if($profile && TenantAccess::code($profile) === 'desenvolvedor', 403, 'Reservado ao suporte.');
        if (isset($data['nome'])) {
            $code = TenantAccess::code((object) ['nome' => $data['nome']]);
            if (($code && (!$profile || $code !== TenantAccess::code($profile))) || ($profile && TenantAccess::code($profile) && $data['nome'] !== $profile->nome)) {
                throw ValidationException::withMessages(['nome' => 'O nome de um perfil padrão é reservado e não pode ser alterado.']);
            }
        }
        if (isset($data['permissoes'])) self::permissions($data['permissoes'], $profile ? $profile->permissoes()->pluck('acesso_permissoes.id')->all() : []);
    }
}
