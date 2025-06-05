<?php

namespace Database\Seeders;

use App\Enums\PerfilEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssociacoesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $adminPerfil = DB::table('acesso_perfis')->where('nome', PerfilEnum::ADMINISTRADOR->value)->first();
        $vendedorPerfil = DB::table('acesso_perfis')->where('nome', PerfilEnum::VENDEDOR->value)->first();

        $usuarios = DB::table('acesso_usuarios')->get();

        foreach ($usuarios as $usuario) {
            $perfil = str_contains($usuario->email, 'admin') ? $adminPerfil : $vendedorPerfil;

            DB::table('acesso_usuario_perfil')->insert([
                'id_usuario' => $usuario->id,
                'id_perfil' => $perfil->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Permissões
        $todasPermissoes = DB::table('acesso_permissoes')->get();

        foreach ($todasPermissoes as $perm) {
            // Administrador recebe todas as permissões
            DB::table('acesso_perfil_permissao')->insert([
                'id_perfil' => $adminPerfil->id,
                'id_permissao' => $perm->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Vendedor recebe apenas permissões comerciais
            if (
                str_starts_with($perm->slug, 'clientes.') ||
                str_starts_with($perm->slug, 'produtos.') ||
                str_starts_with($perm->slug, 'produto_variacoes.') ||
                str_starts_with($perm->slug, 'pedidos.') ||
                str_starts_with($perm->slug, 'carrinhos.') ||
                str_starts_with($perm->slug, 'consignacoes.') ||
                $perm->slug === 'home.visualizar'
            ) {
                DB::table('acesso_perfil_permissao')->insert([
                    'id_perfil' => $vendedorPerfil->id,
                    'id_permissao' => $perm->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
