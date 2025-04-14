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

        // Buscar Perfis e Usuários existentes
        $adminPerfil = DB::table('acesso_perfis')->where('nome', 'Administrador')->first();
        $usuarioPerfil = DB::table('acesso_perfis')->where('nome', 'Usuário')->first();

        $adminUser = DB::table('acesso_usuarios')->where('email', 'admin@teste.com')->first();
        $usuarioUser = DB::table('acesso_usuarios')->where('email', 'usuario@teste.com')->first();

        // Associações de Usuários e Perfis
        DB::table('acesso_usuario_perfil')->insert([
            ['id_usuario' => $adminUser->id, 'id_perfil' => $adminPerfil->id, 'created_at' => $now, 'updated_at' => $now],
            ['id_usuario' => $usuarioUser->id, 'id_perfil' => $usuarioPerfil->id, 'created_at' => $now, 'updated_at' => $now]
        ]);

        // Associação de permissões (Administrador - todas as permissões, Usuário - visualizar)
        $permissoes = DB::table('acesso_permissoes')->get();

        foreach ($permissoes as $perm) {
            if (str_contains($perm->nome, 'Visualizar') || !str_contains($perm->nome, ':')) {
                DB::table('acesso_perfil_permissao')->insert([
                    ['id_perfil' => $usuarioPerfil->id, 'id_permissao' => $perm->id, 'created_at' => $now, 'updated_at' => $now]
                ]);
            }

            DB::table('acesso_perfil_permissao')->insert([
                ['id_perfil' => $adminPerfil->id, 'id_permissao' => $perm->id, 'created_at' => $now, 'updated_at' => $now]
            ]);
        }
    }
}

