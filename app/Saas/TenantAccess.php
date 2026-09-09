<?php

namespace App\Saas;

use Illuminate\Support\Facades\DB;

final class TenantAccess
{
    public const PROFILES = ['administrador' => 'Administrador', 'estoquista' => 'Estoquista', 'financeiro' => 'Financeiro', 'vendedor' => 'Vendedor', 'desenvolvedor' => 'Desenvolvedor'];
    public const TECHNICAL = ['permissoes.criar', 'permissoes.editar', 'permissoes.excluir', 'estoque.importar_planilha_dev'];

    public static function enabled(): bool { return (bool) config('saas.enabled'); }
    public static function code(object $profile): ?string
    {
        if (!empty($profile->codigo)) return $profile->codigo;
        $name = mb_strtolower(trim($profile->nome));
        foreach (self::PROFILES as $code => $label) if ($name === mb_strtolower($label)) return $code;
        return null;
    }
    public static function profileAllowed(object $profile): bool
    {
        if (!self::enabled()) return true;
        $module = ['estoquista' => 'estoque', 'financeiro' => 'financeiro', 'vendedor' => 'vendas'][self::code($profile)] ?? 'base';
        return app(TenantContext::class)->allows($module);
    }
    public static function permissionAllowed(string $slug, bool $technical = false): bool
    {
        if (!self::enabled()) return true;
        $module = config('saas_permissions', [])[$slug] ?? null;
        return $module !== null && app(TenantContext::class)->allows($module)
            && ($technical || !in_array($slug, self::TECHNICAL, true));
    }
    public static function profiles(int $userId)
    {
        return DB::table('acesso_usuario_perfil as up')->join('acesso_perfis as p', 'p.id', '=', 'up.id_perfil')
            ->where('up.id_usuario', $userId)->select('p.*')->get()->filter(fn ($p) => self::profileAllowed($p));
    }
    public static function names(int $userId): array { return self::profiles($userId)->pluck('nome')->unique()->values()->all(); }
    public static function permissions(int $userId): array
    {
        // SaaS checks current profile membership, then the current contract. Never trust a stale flattened cache.
        $profiles = self::profiles($userId); $allowed = [];
        foreach ($profiles as $profile) {
            $technical = self::code($profile) === 'desenvolvedor';
            $slugs = DB::table('acesso_perfil_permissao as pp')->join('acesso_permissoes as p', 'p.id', '=', 'pp.id_permissao')
                ->where('pp.id_perfil', $profile->id)->pluck('p.slug');
            foreach ($slugs as $slug) if (self::permissionAllowed($slug, $technical)) $allowed[] = $slug;
        }
        return array_values(array_unique($allowed));
    }
    public static function metadata(object $profile): array
    {
        $available = self::profileAllowed($profile);
        $support = self::code($profile) === 'desenvolvedor';
        if (!self::code($profile)) {
            $available = DB::table('acesso_perfil_permissao as pp')->join('acesso_permissoes as p', 'p.id', '=', 'pp.id_permissao')
                ->where('pp.id_perfil', $profile->id)->pluck('p.slug')->contains(fn ($slug) => self::permissionAllowed($slug));
        }
        return ['codigo' => self::code($profile), 'disponivel' => $available,
            'atribuivel' => $available && !$support,
            'motivo_bloqueio' => $support ? 'Reservado ao suporte' : ($available ? null : 'Módulo não contratado')];
    }
}
