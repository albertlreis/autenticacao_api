<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissoesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $permissoes = [
            // Home
            ['slug' => 'home.visualizar', 'nome' => 'Home: Visualizar', 'descricao' => 'Permite visualizar o painel inicial'],
            ['slug' => 'home.graficos', 'nome' => 'Home: Gráficos', 'descricao' => 'Permite visualizar gráficos do painel'],
            ['slug' => 'home.kpis', 'nome' => 'Home: Indicadores', 'descricao' => 'Permite visualizar os indicadores (KPIs) do painel'],
            ['slug' => 'home.modais', 'nome' => 'Home: Modais', 'descricao' => 'Permite acessar os modais de KPIs e estoque crítico'],

            //Dashboard
            ['slug' => 'dashboard.admin', 'nome' => 'Dashboard: Administrador', 'descricao' => 'Permite acessar o dashboard de administração'],
            ['slug' => 'dashboard.vendedor', 'nome' => 'Dashboard: Vendedor', 'descricao' => 'Permite acessar o dashboard de vendedores'],

            // Usuários
            ['slug' => 'usuarios.visualizar', 'nome' => 'Usuários: Visualizar', 'descricao' => 'Permite visualizar usuários'],
            ['slug' => 'usuarios.criar', 'nome' => 'Usuários: Criar', 'descricao' => 'Permite criar usuários'],
            ['slug' => 'usuarios.editar', 'nome' => 'Usuários: Editar', 'descricao' => 'Permite editar usuários'],
            ['slug' => 'usuarios.excluir', 'nome' => 'Usuários: Excluir', 'descricao' => 'Permite excluir usuários'],
            ['slug' => 'usuarios.atribuir_perfil', 'nome' => 'Usuários: Atribuir Perfil', 'descricao' => 'Permite associar perfis a usuários'],
            ['slug' => 'usuarios.remover_perfil', 'nome' => 'Usuários: Remover Perfil', 'descricao' => 'Permite remover perfis de usuários'],

            // Perfis
            ['slug' => 'perfis.visualizar', 'nome' => 'Perfis: Visualizar', 'descricao' => 'Permite visualizar perfis'],
            ['slug' => 'perfis.criar', 'nome' => 'Perfis: Criar', 'descricao' => 'Permite criar perfis'],
            ['slug' => 'perfis.editar', 'nome' => 'Perfis: Editar', 'descricao' => 'Permite editar perfis'],
            ['slug' => 'perfis.excluir', 'nome' => 'Perfis: Excluir', 'descricao' => 'Permite excluir perfis'],
            ['slug' => 'perfis.atribuir_permissao', 'nome' => 'Perfis: Atribuir Permissão', 'descricao' => 'Permite associar permissões a perfis'],
            ['slug' => 'perfis.remover_permissao', 'nome' => 'Perfis: Remover Permissão', 'descricao' => 'Permite remover permissões de perfis'],

            // Permissões
            ['slug' => 'permissoes.visualizar', 'nome' => 'Permissões: Visualizar', 'descricao' => 'Permite visualizar permissões'],
            ['slug' => 'permissoes.criar', 'nome' => 'Permissões: Criar', 'descricao' => 'Permite criar permissões'],
            ['slug' => 'permissoes.editar', 'nome' => 'Permissões: Editar', 'descricao' => 'Permite editar permissões'],
            ['slug' => 'permissoes.excluir', 'nome' => 'Permissões: Excluir', 'descricao' => 'Permite excluir permissões'],

            // Clientes
            ['slug' => 'clientes.visualizar', 'nome' => 'Clientes: Visualizar', 'descricao' => 'Permite visualizar clientes'],
            ['slug' => 'clientes.criar', 'nome' => 'Clientes: Criar', 'descricao' => 'Permite cadastrar clientes'],
            ['slug' => 'clientes.editar', 'nome' => 'Clientes: Editar', 'descricao' => 'Permite editar dados de clientes'],
            ['slug' => 'clientes.excluir', 'nome' => 'Clientes: Excluir', 'descricao' => 'Permite excluir clientes'],

            // Categorias
            ['slug' => 'categorias.visualizar', 'nome' => 'Categorias: Visualizar', 'descricao' => 'Permite visualizar categorias'],
            ['slug' => 'categorias.criar', 'nome' => 'Categorias: Criar', 'descricao' => 'Permite criar categorias'],
            ['slug' => 'categorias.editar', 'nome' => 'Categorias: Editar', 'descricao' => 'Permite editar categorias'],
            ['slug' => 'categorias.excluir', 'nome' => 'Categorias: Excluir', 'descricao' => 'Permite excluir categorias'],

            // Produtos
            ['slug' => 'produtos.visualizar', 'nome' => 'Produtos: Visualizar', 'descricao' => 'Permite visualizar produtos'],
            ['slug' => 'produtos.gerenciar', 'nome' => 'Produtos: Gerenciar', 'descricao' => 'Permite gerenciar produtos'],
            ['slug' => 'produtos.criar', 'nome' => 'Produtos: Criar', 'descricao' => 'Permite cadastrar novos produtos'],
            ['slug' => 'produtos.editar', 'nome' => 'Produtos: Editar', 'descricao' => 'Permite editar produtos'],
            ['slug' => 'produtos.excluir', 'nome' => 'Produtos: Excluir', 'descricao' => 'Permite excluir produtos'],
            ['slug' => 'produtos.importar', 'nome' => 'Produtos: Importar XML', 'descricao' => 'Permite importar produtos via XML de nota fiscal'],
            ['slug' => 'produtos.catalogo', 'nome' => 'Produtos: Ver Catálogo', 'descricao' => 'Permite visualizar o catálogo de produtos'],
            ['slug' => 'produtos.outlet', 'nome' => 'Produtos: Ver Outlet', 'descricao' => 'Permite acessar produtos em outlet'],
            ['slug' => 'produtos.configurar_outlet', 'nome' => 'Produtos: Configurar Outlet', 'descricao' => 'Permite configurar os critérios do outlet'],
            ['slug' => 'produtos.variacoes', 'nome' => 'Produtos: Gerenciar Variações', 'descricao' => 'Permite visualizar e editar variações de produtos'],

            // Produto Variações
            ['slug' => 'produto_variacoes.visualizar', 'nome' => 'Variações: Visualizar', 'descricao' => 'Permite visualizar variações de produto'],
            ['slug' => 'produto_variacoes.criar', 'nome' => 'Variações: Criar', 'descricao' => 'Permite criar variações'],
            ['slug' => 'produto_variacoes.editar', 'nome' => 'Variações: Editar', 'descricao' => 'Permite editar variações'],
            ['slug' => 'produto_variacoes.excluir', 'nome' => 'Variações: Excluir', 'descricao' => 'Permite excluir variações'],

            // Produtos - Atributos
            ['slug' => 'produtos.atributos', 'nome' => 'Produtos: Atributos', 'descricao' => 'Permite gerenciar atributos dos produtos'],

            // Pedidos
            ['slug' => 'pedidos.visualizar', 'nome' => 'Pedidos: Visualizar', 'descricao' => 'Permite visualizar pedidos'],
            ['slug' => 'pedidos.visualizar.todos', 'nome' => 'Pedidos: Visualizar Todos', 'descricao' => 'Permite visualizar pedidos de todos os usuários'],
            ['slug' => 'pedidos.estatisticas', 'nome' => 'Pedidos: Estatísticas', 'descricao' => 'Permite visualizar estatísticas de pedidos'],
            ['slug' => 'pedidos.criar', 'nome' => 'Pedidos: Criar', 'descricao' => 'Permite criar novos pedidos'],
            ['slug' => 'pedidos.editar', 'nome' => 'Pedidos: Editar', 'descricao' => 'Permite editar pedidos'],
            ['slug' => 'pedidos.excluir', 'nome' => 'Pedidos: Excluir', 'descricao' => 'Permite excluir pedidos'],
            ['slug' => 'pedidos.exportar_pdf', 'nome' => 'Pedidos: Exportar PDF', 'descricao' => 'Permite exportar o pedido em PDF'],
            ['slug' => 'pedidos.enviar_whatsapp', 'nome' => 'Pedidos: Enviar por WhatsApp', 'descricao' => 'Permite enviar o pedido por WhatsApp'],
            ['slug' => 'pedidos.importar_pdf', 'nome' => 'Pedidos: Importar PDF', 'descricao' => 'Permite importar pedidos a partir de arquivos PDF'],
            ['slug' => 'pedidos.alterar_status', 'nome' => 'Pedidos: Alterar Status', 'descricao' => 'Permite alterar status de pedidos'],
            ['slug' => 'pedidos.cancelar_status', 'nome' => 'Pedidos: Cancelar Status', 'descricao' => 'Permite cancelar status críticos'],

            // Estoque / Depósitos
            ['slug' => 'depositos.visualizar', 'nome' => 'Depósitos: Visualizar', 'descricao' => 'Permite visualizar depósitos'],
            ['slug' => 'depositos.criar', 'nome' => 'Depósitos: Criar', 'descricao' => 'Permite criar depósitos'],
            ['slug' => 'depositos.editar', 'nome' => 'Depósitos: Editar', 'descricao' => 'Permite editar depósitos'],
            ['slug' => 'depositos.excluir', 'nome' => 'Depósitos: Excluir', 'descricao' => 'Permite excluir depósitos'],
            ['slug' => 'estoque.movimentacao', 'nome' => 'Estoque: Movimentação', 'descricao' => 'Permite registrar ou visualizar movimentações de estoque'],
            ['slug' => 'estoque.historico', 'nome' => 'Estoque: Histórico', 'descricao' => 'Permite visualizar histórico de movimentações'],

            // Relatórios
            ['slug' => 'relatorios.visualizar', 'nome' => 'Relatórios: Visualizar', 'descricao' => 'Permite visualizar relatórios'],
            ['slug' => 'relatorios.exportar_excel', 'nome' => 'Relatórios: Exportar Excel', 'descricao' => 'Permite exportar relatórios em Excel'],
            ['slug' => 'relatorios.exportar_pdf', 'nome' => 'Relatórios: Exportar PDF', 'descricao' => 'Permite exportar relatórios em PDF'],

            // Configurações
            ['slug' => 'configuracoes.visualizar', 'nome' => 'Configurações: Visualizar', 'descricao' => 'Permite visualizar configurações'],
            ['slug' => 'configuracoes.editar', 'nome' => 'Configurações: Editar', 'descricao' => 'Permite editar configurações do sistema'],

            // Carrinhos
            ['slug' => 'carrinhos.visualizar.todos', 'nome' => 'Carrinhos: Visualizar', 'descricao' => 'Permite visualizar carrinhos de todos os usuários'],
            ['slug' => 'carrinhos.visualizar', 'nome' => 'Carrinhos: Visualizar', 'descricao' => 'Permite visualizar carrinhos'],
            ['slug' => 'carrinhos.finalizar', 'nome' => 'Carrinhos: Finalizar', 'descricao' => 'Permite finalizar um carrinho'],

            // Consignações
            ['slug' => 'consignacoes.visualizar', 'nome' => 'Consignações: Visualizar', 'descricao' => 'Permite visualizar consignações'],
            ['slug' => 'consignacoes.visualizar.todos', 'nome' => 'Consignações: Visualizar (Todos)', 'descricao' => 'Permite visualizar consignações de todos os usuários'],
            ['slug' => 'consignacoes.gerenciar', 'nome' => 'Consignações: Gerenciar', 'descricao' => 'Permite alterar status e devolver itens'],
            ['slug' => 'consignacoes.vencendo.todos', 'nome' => 'Consignações: Vencendo (Todos)', 'descricao' => 'Permite visualizar consignações vencendo de todos os usuários'],

            // Métricas
            ['slug' => 'monitoramento.visualizar', 'nome' => 'Monitoramento: Visualizar', 'descricao' => 'Permite visualizar métricas do sistema.'],

        ];

        foreach ($permissoes as $p) {
            DB::table('acesso_permissoes')->updateOrInsert(
                ['slug' => $p['slug']],
                [
                    'nome' => $p['nome'],
                    'descricao' => $p['descricao'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
