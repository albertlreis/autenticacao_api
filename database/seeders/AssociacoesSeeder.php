<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssociacoesSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $adminPerfil = DB::table('acesso_perfis')->where('nome', 'Administrador')->first();
        $vendedorPerfil = DB::table('acesso_perfis')->where('nome', 'Vendedor')->first();

        $adminUser = DB::table('acesso_usuarios')->where('email', 'admin@teste.com')->first();
        $vendedorUser = DB::table('acesso_usuarios')->where('email', 'vendedor@teste.com')->first();

        // Associações de usuários com perfis
        DB::table('acesso_usuario_perfil')->insert([
            ['id_usuario' => $adminUser->id, 'id_perfil' => $adminPerfil->id, 'created_at' => $now, 'updated_at' => $now],
            ['id_usuario' => $vendedorUser->id, 'id_perfil' => $vendedorPerfil->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

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
