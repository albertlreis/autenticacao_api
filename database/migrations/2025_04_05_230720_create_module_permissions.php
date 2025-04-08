<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateModulePermissions extends Migration
{
    /**
     * Executa as inserções das permissões.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();

        $permissions = [
            // Permissões para o módulo Usuários
            [
                'nome' => 'Usuarios: Visualizar',
                'descricao' => 'Permite visualizar a lista de usuários',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Usuarios: Criar',
                'descricao' => 'Permite criar novos usuários',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Usuarios: Editar',
                'descricao' => 'Permite editar os dados dos usuários',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Usuarios: Excluir',
                'descricao' => 'Permite excluir usuários',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Permissões para o módulo Produtos
            [
                'nome' => 'Produtos: Visualizar',
                'descricao' => 'Permite visualizar a lista de produtos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Produtos: Criar',
                'descricao' => 'Permite criar novos produtos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Produtos: Editar',
                'descricao' => 'Permite editar os dados dos produtos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Produtos: Excluir',
                'descricao' => 'Permite excluir produtos',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Permissões para o módulo Pedidos
            [
                'nome' => 'Pedidos: Visualizar',
                'descricao' => 'Permite visualizar a lista de pedidos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Pedidos: Criar',
                'descricao' => 'Permite criar novos pedidos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Pedidos: Editar',
                'descricao' => 'Permite editar os dados dos pedidos',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Pedidos: Excluir',
                'descricao' => 'Permite excluir pedidos',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Permissões para o módulo Estoque
            [
                'nome' => 'Estoque: Visualizar',
                'descricao' => 'Permite visualizar o estoque',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Estoque: Criar',
                'descricao' => 'Permite adicionar itens ao estoque',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Estoque: Editar',
                'descricao' => 'Permite editar os dados dos itens do estoque',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nome' => 'Estoque: Excluir',
                'descricao' => 'Permite remover itens do estoque',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('acesso_permissoes')->insert($permissions);
    }

    /**
     * Reverte as inserções das permissões.
     *
     * @return void
     */
    public function down()
    {
        $nomes = [
            'Usuarios: Visualizar',
            'Usuarios: Criar',
            'Usuarios: Editar',
            'Usuarios: Excluir',
            'Produtos: Visualizar',
            'Produtos: Criar',
            'Produtos: Editar',
            'Produtos: Excluir',
            'Pedidos: Visualizar',
            'Pedidos: Criar',
            'Pedidos: Editar',
            'Pedidos: Excluir',
            'Estoque: Visualizar',
            'Estoque: Criar',
            'Estoque: Editar',
            'Estoque: Excluir',
        ];

        DB::table('acesso_permissoes')->whereIn('nome', $nomes)->delete();
    }
}
