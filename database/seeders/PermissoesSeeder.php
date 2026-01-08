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

            // Fornecedores
            ['slug' => 'fornecedores.visualizar', 'nome' => 'Fornecedores: Visualizar', 'descricao' => 'Permite visualizar fornecedores'],
            ['slug' => 'fornecedores.criar', 'nome' => 'Fornecedores: Criar', 'descricao' => 'Permite cadastrar fornecedores'],
            ['slug' => 'fornecedores.editar', 'nome' => 'Fornecedores: Editar', 'descricao' => 'Permite editar fornecedores'],
            ['slug' => 'fornecedores.excluir', 'nome' => 'Fornecedores: Excluir', 'descricao' => 'Permite excluir fornecedores'],

            // Parceiros
            ['slug' => 'parceiros.visualizar', 'nome' => 'Parceiros: Visualizar', 'descricao' => 'Permite visualizar parceiros'],
            ['slug' => 'parceiros.criar', 'nome' => 'Parceiros: Criar', 'descricao' => 'Permite cadastrar parceiros'],
            ['slug' => 'parceiros.editar', 'nome' => 'Parceiros: Editar', 'descricao' => 'Permite editar parceiros'],
            ['slug' => 'parceiros.excluir', 'nome' => 'Parceiros: Excluir', 'descricao' => 'Permite excluir parceiros'],

            // Produtos
            ['slug' => 'produtos.visualizar', 'nome' => 'Produtos: Visualizar', 'descricao' => 'Permite visualizar produtos'],
            ['slug' => 'produtos.gerenciar', 'nome' => 'Produtos: Gerenciar', 'descricao' => 'Permite gerenciar produtos'],
            ['slug' => 'produtos.criar', 'nome' => 'Produtos: Criar', 'descricao' => 'Permite cadastrar novos produtos'],
            ['slug' => 'produtos.editar', 'nome' => 'Produtos: Editar', 'descricao' => 'Permite editar produtos'],
            ['slug' => 'produtos.excluir', 'nome' => 'Produtos: Excluir', 'descricao' => 'Permite excluir produtos'],
            ['slug' => 'produtos.importar', 'nome' => 'Produtos: Importar XML', 'descricao' => 'Permite importar produtos via XML de nota fiscal'],
            ['slug' => 'produtos.catalogo', 'nome' => 'Produtos: Ver Catálogo', 'descricao' => 'Permite visualizar o catálogo de produtos'],

            // Produtos - Outlet
            ['slug' => 'produtos.outlet', 'nome' => 'Produtos: Ver Outlet', 'descricao' => 'Permite acessar produtos em outlet'],
            ['slug' => 'produtos.outlet.cadastrar', 'nome' => 'Outlet: Cadastrar', 'descricao' => 'Permite cadastrar produtos no outlet'],
            ['slug' => 'produtos.outlet.editar', 'nome' => 'Outlet: Editar', 'descricao' => 'Permite editar dados de outlet de um produto'],
            ['slug' => 'produtos.outlet.excluir', 'nome' => 'Outlet: Excluir', 'descricao' => 'Permite remover produtos do outlet'],

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
            ['slug' => 'pedidos.selecionar_vendedor', 'nome' => 'Pedidos: Selecionar Vendedor', 'descricao' => 'Permite selecionar vendedor do pedido'],

            // Pedidos Fábrica
            ['slug' => 'pedidos_fabrica.visualizar', 'nome' => 'Pedidos Fábrica: Visualizar', 'descricao' => 'Permite visualizar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.criar', 'nome' => 'Pedidos Fábrica: Criar', 'descricao' => 'Permite criar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.editar', 'nome' => 'Pedidos Fábrica: Editar', 'descricao' => 'Permite editar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.excluir', 'nome' => 'Pedidos Fábrica: Excluir', 'descricao' => 'Permite excluir pedidos de fábrica'],

            // Depósitos
            ['slug' => 'depositos.visualizar', 'nome' => 'Depósitos: Visualizar', 'descricao' => 'Permite visualizar depósitos'],
            ['slug' => 'depositos.criar', 'nome' => 'Depósitos: Criar', 'descricao' => 'Permite criar depósitos'],
            ['slug' => 'depositos.editar', 'nome' => 'Depósitos: Editar', 'descricao' => 'Permite editar depósitos'],
            ['slug' => 'depositos.excluir', 'nome' => 'Depósitos: Excluir', 'descricao' => 'Permite excluir depósitos'],

            //Estoque
            ['slug' => 'estoque.movimentacao', 'nome' => 'Estoque: Movimentação', 'descricao' => 'Permite registrar ou visualizar movimentações de estoque'],
            ['slug' => 'estoque.movimentar', 'nome' => 'Estoque: Movimentar', 'descricao' => 'Permite realizar movimentações manuais (E, S, T, ou Leitor'],
            ['slug' => 'estoque.historico', 'nome' => 'Estoque: Histórico', 'descricao' => 'Permite visualizar histórico de movimentações'],
            ['slug' => 'estoque.transferir', 'nome' => 'Estoque: Transferir entre Depósitos', 'descricao' => 'Permite realizar transferências entre depósitos'],
            ['slug' => 'estoque.logs', 'nome' => 'Estoque: Logs Detalhados', 'descricao' => 'Permite visualizar e gerar logs detalhados de operações'],

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

            // Assistências
            ['slug' => 'assistencias.visualizar', 'nome' => 'Assistências: Visualizar', 'descricao' => 'Permite visualizar assistências.'],
            ['slug' => 'assistencias.gerenciar', 'nome' => 'Assistências: Gerenciar', 'descricao' => 'Permite gerenciar assistências autorizadas.'],

            // Financeiro — Contas a Pagar
            ['slug' => 'contas.pagar.view',            'nome' => 'Contas a Pagar: Visualizar',         'descricao' => 'Visualizar contas a pagar'],
            ['slug' => 'contas.pagar.create',          'nome' => 'Contas a Pagar: Criar',              'descricao' => 'Criar contas a pagar'],
            ['slug' => 'contas.pagar.update',          'nome' => 'Contas a Pagar: Editar',             'descricao' => 'Editar contas a pagar'],
            ['slug' => 'contas.pagar.delete',          'nome' => 'Contas a Pagar: Excluir',            'descricao' => 'Excluir contas a pagar'],
            ['slug' => 'contas.pagar.pagar',           'nome' => 'Contas a Pagar: Registrar Pagamento','descricao' => 'Registrar pagamentos'],
            ['slug' => 'contas.pagar.estornar',        'nome' => 'Contas a Pagar: Estornar Pagamento', 'descricao' => 'Estornar pagamentos'],
            ['slug' => 'contas.pagar.exportar_excel',  'nome' => 'Contas a Pagar: Exportar Excel',     'descricao' => 'Exportar listagem em Excel'],
            ['slug' => 'contas.pagar.exportar_pdf',    'nome' => 'Contas a Pagar: Exportar PDF',       'descricao' => 'Exportar listagem em PDF'],

            // Financeiro — Contas a Receber
            ['slug' => 'contas.receber.view',            'nome' => 'Contas a Receber: Visualizar',          'descricao' => 'Visualizar contas a receber'],
            ['slug' => 'contas.receber.create',          'nome' => 'Contas a Receber: Criar',               'descricao' => 'Criar contas a receber'],
            ['slug' => 'contas.receber.update',          'nome' => 'Contas a Receber: Editar',              'descricao' => 'Editar contas a receber'],
            ['slug' => 'contas.receber.delete',          'nome' => 'Contas a Receber: Excluir',             'descricao' => 'Excluir contas a receber'],
            ['slug' => 'contas.receber.receber',         'nome' => 'Contas a Receber: Registrar Recebimento','descricao' => 'Registrar recebimentos'],
            ['slug' => 'contas.receber.estornar',        'nome' => 'Contas a Receber: Estornar Recebimento','descricao' => 'Estornar recebimentos'],
            ['slug' => 'contas.receber.exportar_excel',  'nome' => 'Contas a Receber: Exportar Excel',      'descricao' => 'Exportar listagem em Excel'],
            ['slug' => 'contas.receber.exportar_pdf',    'nome' => 'Contas a Receber: Exportar PDF',        'descricao' => 'Exportar listagem em PDF'],

            // Financeiro: Lançamentos
            ['slug' => 'financeiro.lancamentos.visualizar', 'nome' => 'Financeiro: Lançamentos - Visualizar', 'descricao' => 'Permite listar e visualizar lançamentos'],
            ['slug' => 'financeiro.lancamentos.criar',      'nome' => 'Financeiro: Lançamentos - Criar',      'descricao' => 'Permite criar lançamentos'],
            ['slug' => 'financeiro.lancamentos.editar',     'nome' => 'Financeiro: Lançamentos - Editar',     'descricao' => 'Permite editar lançamentos'],
            ['slug' => 'financeiro.lancamentos.excluir',    'nome' => 'Financeiro: Lançamentos - Excluir',    'descricao' => 'Permite excluir lançamentos'],
            ['slug' => 'financeiro.lancamentos.exportar',   'nome' => 'Financeiro: Lançamentos - Exportar',   'descricao' => 'Permite exportar lançamentos'],
            ['slug' => 'financeiro.dashboard.visualizar',   'nome' => 'Financeiro: Dashboard - Visualizar',   'descricao' => 'Permite visualizar dashboard financeiro'],

            // Despesas Recorrentes
            ['slug' => 'despesas_recorrentes.visualizar', 'nome' => 'Despesas Recorrentes: Visualizar', 'descricao' => 'Permite listar e visualizar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.criar', 'nome' => 'Despesas Recorrentes: Criar', 'descricao' => 'Permite cadastrar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.editar', 'nome' => 'Despesas Recorrentes: Editar', 'descricao' => 'Permite editar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.executar', 'nome' => 'Despesas Recorrentes: Executar', 'descricao' => 'Permite executar manualmente a geração de contas a pagar'],
            ['slug' => 'despesas_recorrentes.cancelar', 'nome' => 'Despesas Recorrentes: Cancelar', 'descricao' => 'Permite cancelar despesas recorrentes'],

            // Estoque — Operação (atalho; opcional manter só granularidade de estoque.* / depositos.*)
            ['slug' => 'estoquista.operar',            'nome' => 'Estoquista: Operar',                 'descricao' => 'Acesso operacional ao estoque'],

            // Comunicação (Painel)
            ['slug' => 'comunicacao.visualizar',       'nome' => 'Comunicação: Visualizar',         'descricao' => 'Permite acessar o painel de comunicação (dashboard, requests e mensagens)'],
            ['slug' => 'comunicacao.templates',        'nome' => 'Comunicação: Templates',          'descricao' => 'Permite criar/editar templates e gerar preview'],
            ['slug' => 'comunicacao.requests.cancelar','nome' => 'Comunicação: Cancelar Request',   'descricao' => 'Permite cancelar requests pendentes'],
            ['slug' => 'comunicacao.messages.retry',   'nome' => 'Comunicação: Retry Mensagem',     'descricao' => 'Permite reprocessar mensagens com falha'],

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
