<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('acesso_usuarios')->insert([
            ['nome' => 'Dev Master', 'email' => 'dev@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Admin Teste', 'email' => 'admin@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Vendedor 1', 'email' => 'vendedor1@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Vendedor 2', 'email' => 'vendedor2@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Vendedor 3', 'email' => 'vendedor3@teste.com', 'senha' => Hash::make('senha123'), 'ativo' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
