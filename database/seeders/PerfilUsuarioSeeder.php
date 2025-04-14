<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PerfilUsuarioSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // Perfis
        DB::table('acesso_perfis')->insert([
            ['nome' => 'Administrador', 'descricao' => 'Acesso total ao sistema', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Usuário', 'descricao' => 'Acesso limitado ao sistema', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Gerente', 'descricao' => 'Acesso intermediário ao sistema', 'created_at' => $now, 'updated_at' => $now]
        ]);

        // Usuários
        DB::table('acesso_usuarios')->insert([
            ['nome' => 'Admin Teste', 'email' => 'admin@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Usuario Teste', 'email' => 'usuario@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now]
        ]);
    }
}
