<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissoesSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $permissoes = [
            // Permissões básicas
            ['nome' => 'Criar', 'descricao' => 'Permite criar registros', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Editar', 'descricao' => 'Permite editar registros', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Excluir', 'descricao' => 'Permite excluir registros', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Visualizar', 'descricao' => 'Permite visualizar registros', 'created_at' => $now, 'updated_at' => $now],

            // Permissões por módulo
            ['nome' => 'Usuarios: Visualizar', 'descricao' => 'Permite visualizar usuários', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Usuarios: Criar', 'descricao' => 'Permite criar usuários', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Usuarios: Editar', 'descricao' => 'Permite editar usuários', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Usuarios: Excluir', 'descricao' => 'Permite excluir usuários', 'created_at' => $now, 'updated_at' => $now],

            ['nome' => 'Produtos: Visualizar', 'descricao' => 'Permite visualizar produtos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Produtos: Criar', 'descricao' => 'Permite criar produtos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Produtos: Editar', 'descricao' => 'Permite editar produtos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Produtos: Excluir', 'descricao' => 'Permite excluir produtos', 'created_at' => $now, 'updated_at' => $now],

            ['nome' => 'Pedidos: Visualizar', 'descricao' => 'Permite visualizar pedidos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Pedidos: Criar', 'descricao' => 'Permite criar pedidos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Pedidos: Editar', 'descricao' => 'Permite editar pedidos', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Pedidos: Excluir', 'descricao' => 'Permite excluir pedidos', 'created_at' => $now, 'updated_at' => $now],

            ['nome' => 'Estoque: Visualizar', 'descricao' => 'Permite visualizar estoque', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Estoque: Criar', 'descricao' => 'Permite criar itens de estoque', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Estoque: Editar', 'descricao' => 'Permite editar itens de estoque', 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Estoque: Excluir', 'descricao' => 'Permite excluir itens do estoque', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('acesso_permissoes')->insert($permissoes);
    }
}

