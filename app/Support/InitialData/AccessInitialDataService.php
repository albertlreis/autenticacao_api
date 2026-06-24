<?php

namespace App\Support\InitialData;

use App\Enums\PerfilEnum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccessInitialDataService
{
    public function runBootstrap(?callable $logger = null): void
    {
        $steps = [
            'Perfis obrigatórios' => 'seedPerfis',
            'Permissões obrigatórias' => 'seedPermissoes',
            'Usuários padrão' => 'seedUsuariosPadrao',
            'Associações obrigatórias' => 'seedAssociacoes',
        ];

        foreach ($steps as $label => $method) {
            if ($method === 'seedUsuariosPadrao' && !$this->shouldSeedUsuariosPadrao()) {
                if ($logger) {
                    $logger($label . ' (pulados fora de local/testing)');
                }

                continue;
            }

            if ($logger) {
                $logger($label);
            }
            $this->{$method}();
        }

        $this->refreshPermissoesCache();
    }

    public function shouldSeedUsuariosPadrao(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function seedPerfis(): void
    {
        $now = now();

        $rows = [
            ['nome' => PerfilEnum::ADMINISTRADOR->value, 'descricao' => 'Acesso total ao sistema'],
            ['nome' => PerfilEnum::VENDEDOR->value, 'descricao' => 'Acesso comercial restrito'],
            ['nome' => PerfilEnum::DESENVOLVEDOR->value, 'descricao' => 'Acesso técnico irrestrito'],
            ['nome' => PerfilEnum::FINANCEIRO->value, 'descricao' => 'Operação do módulo financeiro'],
            ['nome' => PerfilEnum::ESTOQUISTA->value, 'descricao' => 'Operação do módulo de estoque'],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        DB::table('acesso_perfis')->insertOrIgnore($rows);
    }

    public function seedPermissoes(): void
    {
        $now = now();

        $rows = collect($this->permissoes())
            ->map(fn (array $permissao) => [
                'slug' => $permissao['slug'],
                'nome' => $permissao['nome'],
                'descricao' => $permissao['descricao'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('acesso_permissoes')->insertOrIgnore($rows);
    }

    public function seedUsuariosPadrao(): void
    {
        $now = now();

        foreach ($this->usuariosPadrao() as $usuario) {
            $existe = DB::table('acesso_usuarios')
                ->where('email', $usuario['email'])
                ->exists();

            if (!$existe) {
                DB::table('acesso_usuarios')->insert([
                    'nome' => $usuario['nome'],
                    'ativo' => true,
                    'updated_at' => $now,
                    'email' => $usuario['email'],
                    'senha' => Hash::make($usuario['senha']),
                    'senha_alterada_em' => $now,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function seedAssociacoes(): void
    {
        DB::transaction(function () {
            $now = now();

            $perfis = DB::table('acesso_perfis')
                ->pluck('id', 'nome');

            $adminPerfilId = $perfis[PerfilEnum::ADMINISTRADOR->value] ?? null;
            $vendedorPerfilId = $perfis[PerfilEnum::VENDEDOR->value] ?? null;
            $devPerfilId = $perfis[PerfilEnum::DESENVOLVEDOR->value] ?? null;
            $financeiroPerfilId = $perfis[PerfilEnum::FINANCEIRO->value] ?? null;
            $estoquistaPerfilId = $perfis[PerfilEnum::ESTOQUISTA->value] ?? null;

            $usuarios = DB::table('acesso_usuarios')->get(['id', 'email']);

            foreach ($usuarios as $usuario) {
                $perfilId = match (true) {
                    str_contains($usuario->email, 'dev') => $devPerfilId,
                    str_contains($usuario->email, 'admin') => $adminPerfilId,
                    str_contains($usuario->email, 'financeiro') => $financeiroPerfilId,
                    str_contains($usuario->email, 'estoquista') => $estoquistaPerfilId,
                    default => $vendedorPerfilId,
                };

                if (!$perfilId) {
                    continue;
                }

                $this->insertUsuarioPerfil((int) $usuario->id, (int) $perfilId, $now);
            }

            if ($financeiroPerfilId) {
                $financeiroPerms = DB::table('acesso_permissoes')
                    ->whereIn('slug', [
                        'contas.pagar.view', 'contas.pagar.create', 'contas.pagar.update', 'contas.pagar.delete',
                        'contas.pagar.pagar', 'contas.pagar.estornar', 'contas.pagar.exportar_excel', 'contas.pagar.exportar_pdf',
                        'contas.receber.view', 'contas.receber.create', 'contas.receber.update', 'contas.receber.delete',
                        'contas.receber.receber', 'contas.receber.estornar', 'contas.receber.exportar_excel', 'contas.receber.exportar_pdf',
                        'financeiro.dashboard.visualizar',
                        'financeiro.lancamentos.visualizar', 'financeiro.lancamentos.criar', 'financeiro.lancamentos.editar',
                        'financeiro.lancamentos.excluir', 'financeiro.lancamentos.exportar',
                        'financeiro.relatorios.visualizar', 'financeiro.relatorios.exportar_excel', 'financeiro.relatorios.exportar_pdf',
                        'despesas_recorrentes.visualizar', 'despesas_recorrentes.criar', 'despesas_recorrentes.editar',
                        'despesas_recorrentes.executar', 'despesas_recorrentes.cancelar',
                        'relatorios.visualizar', 'relatorios.exportar_excel', 'relatorios.exportar_pdf',
                        'home.visualizar', 'home.kpis',
                        'conta_azul.visualizar', 'conta_azul.configurar', 'conta_azul.importar',
                        'conta_azul.conciliar', 'conta_azul.auditar',
                        'google_calendar.visualizar',
                    ])
                    ->pluck('id');

                foreach ($financeiroPerms as $permissaoId) {
                    $this->insertPerfilPermissao($financeiroPerfilId, (int) $permissaoId, $now);
                }
            }

            if ($estoquistaPerfilId) {
                $estoquistaPrefixos = [
                    'pedidos.',
                    'pedidos_fabrica.',
                    'produtos.',
                    'produto_variacoes.',
                    'clientes.',
                    'categorias.',
                    'fornecedores.',
                    'parceiros.',
                    'estoque.',
                    'depositos.',
                    'relatorios.',
                    'carrinhos.',
                    'consignacoes.',
                    'assistencias.',
                ];

                $estoquistaPerms = DB::table('acesso_permissoes')
                    ->where(function ($query) use ($estoquistaPrefixos) {
                        foreach ($estoquistaPrefixos as $prefixo) {
                            $query->orWhere('slug', 'like', $prefixo . '%');
                        }
                    })
                    ->orWhereIn('slug', ['home.visualizar'])
                    ->pluck('id');

                foreach ($estoquistaPerms as $permissaoId) {
                    $this->insertPerfilPermissao($estoquistaPerfilId, (int) $permissaoId, $now);
                }
            }

            $permissoes = DB::table('acesso_permissoes')->get(['id', 'slug']);

            foreach ($permissoes as $permissao) {
                if ($devPerfilId) {
                    $this->insertPerfilPermissao($devPerfilId, (int) $permissao->id, $now);
                }

                if (
                    $adminPerfilId
                    && !in_array($permissao->slug, [
                        'monitoramento.visualizar',
                        'perfis.visualizar',
                        'perfis.criar',
                        'perfis.editar',
                        'perfis.excluir',
                        'perfis.atribuir_permissao',
                        'perfis.remover_permissao',
                        'permissoes.visualizar',
                        'permissoes.criar',
                        'permissoes.editar',
                        'permissoes.excluir',
                    ], true)
                ) {
                    $this->insertPerfilPermissao($adminPerfilId, (int) $permissao->id, $now);
                }

                if (
                    $vendedorPerfilId
                    && (
                        str_starts_with($permissao->slug, 'clientes.')
                        || (
                            str_starts_with($permissao->slug, 'produtos.')
                            && !in_array($permissao->slug, [
                                'produtos.gerenciar',
                                'produtos.criar',
                                'produtos.editar',
                                'produtos.excluir',
                                'produtos.importar',
                                'produtos.outlet.cadastrar',
                                'produtos.outlet.editar',
                                'produtos.outlet.excluir',
                                'produtos.configurar_outlet',
                            ], true)
                        )
                        || str_starts_with($permissao->slug, 'produto_variacoes.')
                        || (
                            str_starts_with($permissao->slug, 'pedidos.')
                            && !in_array($permissao->slug, ['pedidos.visualizar.todos', 'pedidos.estatisticas'], true)
                        )
                        || (
                            str_starts_with($permissao->slug, 'carrinhos.')
                            && $permissao->slug !== 'carrinhos.visualizar.todos'
                        )
                        || (
                            str_starts_with($permissao->slug, 'consignacoes.')
                            && $permissao->slug !== 'consignacoes.vencendo.todos'
                        )
                        || str_starts_with($permissao->slug, 'home.')
                        || str_starts_with($permissao->slug, 'parceiros.')
                        || $permissao->slug === 'fornecedores.visualizar'
                    )
                ) {
                    $this->insertPerfilPermissao($vendedorPerfilId, (int) $permissao->id, $now);
                }
            }
        });
    }

    public function refreshPermissoesCache(): void
    {
        Artisan::call('permissao:refresh-cache');
    }

    private function insertUsuarioPerfil(int $usuarioId, int $perfilId, \Illuminate\Support\Carbon $now): void
    {
        DB::table('acesso_usuario_perfil')->insertOrIgnore([
            'id_usuario' => $usuarioId,
            'id_perfil' => $perfilId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertPerfilPermissao(int $perfilId, int $permissaoId, \Illuminate\Support\Carbon $now): void
    {
        DB::table('acesso_perfil_permissao')->insertOrIgnore([
            'id_perfil' => $perfilId,
            'id_permissao' => $permissaoId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function usuariosPadrao(): array
    {
        return [
            ['nome' => 'Dev Master', 'email' => 'dev@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Admin Teste', 'email' => 'admin@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Vendedor 1', 'email' => 'vendedor1@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Vendedor 2', 'email' => 'vendedor2@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Vendedor 3', 'email' => 'vendedor3@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Financeiro Operador', 'email' => 'financeiro@teste.com', 'senha' => 'senha123'],
            ['nome' => 'Estoquista', 'email' => 'estoquista@teste.com', 'senha' => 'senha123'],
        ];
    }

    private function permissoes(): array
    {
        return [
            ['slug' => 'home.visualizar', 'nome' => 'Home: Visualizar', 'descricao' => 'Permite visualizar o painel inicial'],
            ['slug' => 'home.graficos', 'nome' => 'Home: Gráficos', 'descricao' => 'Permite visualizar gráficos do painel'],
            ['slug' => 'home.kpis', 'nome' => 'Home: Indicadores', 'descricao' => 'Permite visualizar os indicadores (KPIs) do painel'],
            ['slug' => 'home.modais', 'nome' => 'Home: Modais', 'descricao' => 'Permite acessar os modais de KPIs e estoque crítico'],
            ['slug' => 'dashboard.admin', 'nome' => 'Dashboard: Administrador', 'descricao' => 'Permite acessar o dashboard de administração'],
            ['slug' => 'dashboard.vendedor', 'nome' => 'Dashboard: Vendedor', 'descricao' => 'Permite acessar o dashboard de vendedores'],
            ['slug' => 'usuarios.visualizar', 'nome' => 'Usuários: Visualizar', 'descricao' => 'Permite visualizar usuários'],
            ['slug' => 'usuarios.criar', 'nome' => 'Usuários: Criar', 'descricao' => 'Permite criar usuários'],
            ['slug' => 'usuarios.editar', 'nome' => 'Usuários: Editar', 'descricao' => 'Permite editar usuários'],
            ['slug' => 'usuarios.excluir', 'nome' => 'Usuários: Excluir', 'descricao' => 'Permite excluir usuários'],
            ['slug' => 'usuarios.atribuir_perfil', 'nome' => 'Usuários: Atribuir Perfil', 'descricao' => 'Permite associar perfis a usuários'],
            ['slug' => 'usuarios.remover_perfil', 'nome' => 'Usuários: Remover Perfil', 'descricao' => 'Permite remover perfis de usuários'],
            ['slug' => 'perfis.visualizar', 'nome' => 'Perfis: Visualizar', 'descricao' => 'Permite visualizar perfis'],
            ['slug' => 'perfis.criar', 'nome' => 'Perfis: Criar', 'descricao' => 'Permite criar perfis'],
            ['slug' => 'perfis.editar', 'nome' => 'Perfis: Editar', 'descricao' => 'Permite editar perfis'],
            ['slug' => 'perfis.excluir', 'nome' => 'Perfis: Excluir', 'descricao' => 'Permite excluir perfis'],
            ['slug' => 'perfis.atribuir_permissao', 'nome' => 'Perfis: Atribuir Permissão', 'descricao' => 'Permite associar permissões a perfis'],
            ['slug' => 'perfis.remover_permissao', 'nome' => 'Perfis: Remover Permissão', 'descricao' => 'Permite remover permissões de perfis'],
            ['slug' => 'permissoes.visualizar', 'nome' => 'Permissões: Visualizar', 'descricao' => 'Permite visualizar permissões'],
            ['slug' => 'permissoes.criar', 'nome' => 'Permissões: Criar', 'descricao' => 'Permite criar permissões'],
            ['slug' => 'permissoes.editar', 'nome' => 'Permissões: Editar', 'descricao' => 'Permite editar permissões'],
            ['slug' => 'permissoes.excluir', 'nome' => 'Permissões: Excluir', 'descricao' => 'Permite excluir permissões'],
            ['slug' => 'clientes.visualizar', 'nome' => 'Clientes: Visualizar', 'descricao' => 'Permite visualizar clientes'],
            ['slug' => 'clientes.criar', 'nome' => 'Clientes: Criar', 'descricao' => 'Permite cadastrar clientes'],
            ['slug' => 'clientes.editar', 'nome' => 'Clientes: Editar', 'descricao' => 'Permite editar dados de clientes'],
            ['slug' => 'clientes.excluir', 'nome' => 'Clientes: Excluir', 'descricao' => 'Permite excluir clientes'],
            ['slug' => 'categorias.visualizar', 'nome' => 'Categorias: Visualizar', 'descricao' => 'Permite visualizar categorias'],
            ['slug' => 'categorias.criar', 'nome' => 'Categorias: Criar', 'descricao' => 'Permite criar categorias'],
            ['slug' => 'categorias.editar', 'nome' => 'Categorias: Editar', 'descricao' => 'Permite editar categorias'],
            ['slug' => 'categorias.excluir', 'nome' => 'Categorias: Excluir', 'descricao' => 'Permite excluir categorias'],
            ['slug' => 'fornecedores.visualizar', 'nome' => 'Fornecedores: Visualizar', 'descricao' => 'Permite visualizar fornecedores'],
            ['slug' => 'fornecedores.criar', 'nome' => 'Fornecedores: Criar', 'descricao' => 'Permite cadastrar fornecedores'],
            ['slug' => 'fornecedores.editar', 'nome' => 'Fornecedores: Editar', 'descricao' => 'Permite editar fornecedores'],
            ['slug' => 'fornecedores.excluir', 'nome' => 'Fornecedores: Excluir', 'descricao' => 'Permite excluir fornecedores'],
            ['slug' => 'parceiros.visualizar', 'nome' => 'Parceiros: Visualizar', 'descricao' => 'Permite visualizar parceiros'],
            ['slug' => 'parceiros.criar', 'nome' => 'Parceiros: Criar', 'descricao' => 'Permite cadastrar parceiros'],
            ['slug' => 'parceiros.editar', 'nome' => 'Parceiros: Editar', 'descricao' => 'Permite editar parceiros'],
            ['slug' => 'parceiros.excluir', 'nome' => 'Parceiros: Excluir', 'descricao' => 'Permite excluir parceiros'],
            ['slug' => 'produtos.visualizar', 'nome' => 'Produtos: Visualizar', 'descricao' => 'Permite visualizar produtos'],
            ['slug' => 'produtos.gerenciar', 'nome' => 'Produtos: Gerenciar', 'descricao' => 'Permite gerenciar produtos'],
            ['slug' => 'produtos.criar', 'nome' => 'Produtos: Criar', 'descricao' => 'Permite cadastrar novos produtos'],
            ['slug' => 'produtos.editar', 'nome' => 'Produtos: Editar', 'descricao' => 'Permite editar produtos'],
            ['slug' => 'produtos.excluir', 'nome' => 'Produtos: Excluir', 'descricao' => 'Permite excluir produtos'],
            ['slug' => 'produtos.importar', 'nome' => 'Produtos: Importar XML', 'descricao' => 'Permite importar produtos via XML de nota fiscal'],
            ['slug' => 'produtos.catalogo', 'nome' => 'Produtos: Ver Catálogo', 'descricao' => 'Permite visualizar o catálogo de produtos'],
            ['slug' => 'produtos.precos_custos', 'nome' => 'Produtos: Preços e Custos', 'descricao' => 'Permite acessar e gerenciar preços e custos dos produtos'],
            ['slug' => 'produtos.outlet', 'nome' => 'Produtos: Ver Outlet', 'descricao' => 'Permite acessar produtos em outlet'],
            ['slug' => 'produtos.outlet.cadastrar', 'nome' => 'Outlet: Cadastrar', 'descricao' => 'Permite cadastrar produtos no outlet'],
            ['slug' => 'produtos.outlet.editar', 'nome' => 'Outlet: Editar', 'descricao' => 'Permite editar dados de outlet de um produto'],
            ['slug' => 'produtos.outlet.excluir', 'nome' => 'Outlet: Excluir', 'descricao' => 'Permite remover produtos do outlet'],
            ['slug' => 'produto_variacoes.visualizar', 'nome' => 'Variações: Visualizar', 'descricao' => 'Permite visualizar variações de produto'],
            ['slug' => 'produto_variacoes.criar', 'nome' => 'Variações: Criar', 'descricao' => 'Permite criar variações'],
            ['slug' => 'produto_variacoes.editar', 'nome' => 'Variações: Editar', 'descricao' => 'Permite editar variações'],
            ['slug' => 'produto_variacoes.excluir', 'nome' => 'Variações: Excluir', 'descricao' => 'Permite excluir variações'],
            ['slug' => 'produtos.atributos', 'nome' => 'Produtos: Atributos', 'descricao' => 'Permite gerenciar atributos dos produtos'],
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
            ['slug' => 'pedidos_fabrica.visualizar', 'nome' => 'Pedidos Fábrica: Visualizar', 'descricao' => 'Permite visualizar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.criar', 'nome' => 'Pedidos Fábrica: Criar', 'descricao' => 'Permite criar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.editar', 'nome' => 'Pedidos Fábrica: Editar', 'descricao' => 'Permite editar pedidos de fábrica'],
            ['slug' => 'pedidos_fabrica.excluir', 'nome' => 'Pedidos Fábrica: Excluir', 'descricao' => 'Permite excluir pedidos de fábrica'],
            ['slug' => 'depositos.visualizar', 'nome' => 'Depósitos: Visualizar', 'descricao' => 'Permite visualizar depósitos'],
            ['slug' => 'depositos.criar', 'nome' => 'Depósitos: Criar', 'descricao' => 'Permite criar depósitos'],
            ['slug' => 'depositos.editar', 'nome' => 'Depósitos: Editar', 'descricao' => 'Permite editar depósitos'],
            ['slug' => 'depositos.excluir', 'nome' => 'Depósitos: Excluir', 'descricao' => 'Permite excluir depósitos'],
            ['slug' => 'estoque.movimentacao', 'nome' => 'Estoque: Movimentação', 'descricao' => 'Permite registrar ou visualizar movimentações de estoque'],
            ['slug' => 'estoque.movimentar', 'nome' => 'Estoque: Movimentar', 'descricao' => 'Permite realizar movimentações manuais (E, S, T, ou Leitor'],
            ['slug' => 'estoque.historico', 'nome' => 'Estoque: Histórico', 'descricao' => 'Permite visualizar histórico de movimentações'],
            ['slug' => 'estoque.transferir', 'nome' => 'Estoque: Transferir entre Depósitos', 'descricao' => 'Permite realizar transferências entre depósitos'],
            ['slug' => 'estoque.logs', 'nome' => 'Estoque: Logs Detalhados', 'descricao' => 'Permite visualizar e gerar logs detalhados de operações'],
            ['slug' => 'relatorios.visualizar', 'nome' => 'Relatórios: Visualizar', 'descricao' => 'Permite visualizar relatórios'],
            ['slug' => 'relatorios.exportar_excel', 'nome' => 'Relatórios: Exportar Excel', 'descricao' => 'Permite exportar relatórios em Excel'],
            ['slug' => 'relatorios.exportar_pdf', 'nome' => 'Relatórios: Exportar PDF', 'descricao' => 'Permite exportar relatórios em PDF'],
            ['slug' => 'configuracoes.visualizar', 'nome' => 'Configurações: Visualizar', 'descricao' => 'Permite visualizar configurações'],
            ['slug' => 'configuracoes.editar', 'nome' => 'Configurações: Editar', 'descricao' => 'Permite editar configurações do sistema'],
            ['slug' => 'avisos.visualizar', 'nome' => 'Avisos: Visualizar', 'descricao' => 'Permite listar e visualizar avisos internos'],
            ['slug' => 'avisos.gerenciar', 'nome' => 'Avisos: Gerenciar', 'descricao' => 'Permite criar, editar e inativar avisos internos'],
            ['slug' => 'eventos.visualizar', 'nome' => 'Eventos: Visualizar', 'descricao' => 'Permite listar e visualizar eventos internos'],
            ['slug' => 'eventos.gerenciar', 'nome' => 'Eventos: Gerenciar', 'descricao' => 'Permite criar, editar e remover eventos internos'],
            ['slug' => 'carrinhos.visualizar.todos', 'nome' => 'Carrinhos: Visualizar', 'descricao' => 'Permite visualizar carrinhos de todos os usuários'],
            ['slug' => 'carrinhos.visualizar', 'nome' => 'Carrinhos: Visualizar', 'descricao' => 'Permite visualizar carrinhos'],
            ['slug' => 'carrinhos.finalizar', 'nome' => 'Carrinhos: Finalizar', 'descricao' => 'Permite finalizar um carrinho'],
            ['slug' => 'consignacoes.visualizar', 'nome' => 'Consignações: Visualizar', 'descricao' => 'Permite visualizar consignações'],
            ['slug' => 'consignacoes.visualizar.todos', 'nome' => 'Consignações: Visualizar (Todos)', 'descricao' => 'Permite visualizar consignações de todos os usuários'],
            ['slug' => 'consignacoes.gerenciar', 'nome' => 'Consignações: Gerenciar', 'descricao' => 'Permite alterar status e devolver itens'],
            ['slug' => 'consignacoes.vencendo.todos', 'nome' => 'Consignações: Vencendo (Todos)', 'descricao' => 'Permite visualizar consignações vencendo de todos os usuários'],
            ['slug' => 'monitoramento.visualizar', 'nome' => 'Monitoramento: Visualizar', 'descricao' => 'Permite visualizar métricas do sistema.'],
            ['slug' => 'auditoria.logs.visualizar', 'nome' => 'Auditoria: Logs', 'descricao' => 'Permite visualizar auditoria e logs unificados do sistema.'],
            ['slug' => 'assistencias.visualizar', 'nome' => 'Assistências: Visualizar', 'descricao' => 'Permite visualizar assistências.'],
            ['slug' => 'assistencias.gerenciar', 'nome' => 'Assistências: Gerenciar', 'descricao' => 'Permite gerenciar assistências autorizadas.'],
            ['slug' => 'contas.pagar.view', 'nome' => 'Contas a Pagar: Visualizar', 'descricao' => 'Visualizar contas a pagar'],
            ['slug' => 'contas.pagar.create', 'nome' => 'Contas a Pagar: Criar', 'descricao' => 'Criar contas a pagar'],
            ['slug' => 'contas.pagar.update', 'nome' => 'Contas a Pagar: Editar', 'descricao' => 'Editar contas a pagar'],
            ['slug' => 'contas.pagar.delete', 'nome' => 'Contas a Pagar: Excluir', 'descricao' => 'Excluir contas a pagar'],
            ['slug' => 'contas.pagar.pagar', 'nome' => 'Contas a Pagar: Registrar Pagamento', 'descricao' => 'Registrar pagamentos'],
            ['slug' => 'contas.pagar.estornar', 'nome' => 'Contas a Pagar: Estornar Pagamento', 'descricao' => 'Estornar pagamentos'],
            ['slug' => 'contas.pagar.exportar_excel', 'nome' => 'Contas a Pagar: Exportar Excel', 'descricao' => 'Exportar listagem em Excel'],
            ['slug' => 'contas.pagar.exportar_pdf', 'nome' => 'Contas a Pagar: Exportar PDF', 'descricao' => 'Exportar listagem em PDF'],
            ['slug' => 'contas.receber.view', 'nome' => 'Contas a Receber: Visualizar', 'descricao' => 'Visualizar contas a receber'],
            ['slug' => 'contas.receber.create', 'nome' => 'Contas a Receber: Criar', 'descricao' => 'Criar contas a receber'],
            ['slug' => 'contas.receber.update', 'nome' => 'Contas a Receber: Editar', 'descricao' => 'Editar contas a receber'],
            ['slug' => 'contas.receber.delete', 'nome' => 'Contas a Receber: Excluir', 'descricao' => 'Excluir contas a receber'],
            ['slug' => 'contas.receber.receber', 'nome' => 'Contas a Receber: Registrar Recebimento', 'descricao' => 'Registrar recebimentos'],
            ['slug' => 'contas.receber.estornar', 'nome' => 'Contas a Receber: Estornar Recebimento', 'descricao' => 'Estornar recebimentos'],
            ['slug' => 'contas.receber.exportar_excel', 'nome' => 'Contas a Receber: Exportar Excel', 'descricao' => 'Exportar listagem em Excel'],
            ['slug' => 'contas.receber.exportar_pdf', 'nome' => 'Contas a Receber: Exportar PDF', 'descricao' => 'Exportar listagem em PDF'],
            ['slug' => 'financeiro.lancamentos.visualizar', 'nome' => 'Financeiro: Lançamentos - Visualizar', 'descricao' => 'Permite listar e visualizar lançamentos'],
            ['slug' => 'financeiro.lancamentos.criar', 'nome' => 'Financeiro: Lançamentos - Criar', 'descricao' => 'Permite criar lançamentos'],
            ['slug' => 'financeiro.lancamentos.editar', 'nome' => 'Financeiro: Lançamentos - Editar', 'descricao' => 'Permite editar lançamentos'],
            ['slug' => 'financeiro.lancamentos.excluir', 'nome' => 'Financeiro: Lançamentos - Excluir', 'descricao' => 'Permite excluir lançamentos'],
            ['slug' => 'financeiro.lancamentos.exportar', 'nome' => 'Financeiro: Lançamentos - Exportar', 'descricao' => 'Permite exportar lançamentos'],
            ['slug' => 'financeiro.dashboard.visualizar', 'nome' => 'Financeiro: Dashboard - Visualizar', 'descricao' => 'Permite visualizar dashboard financeiro'],
            ['slug' => 'financeiro.relatorios.visualizar', 'nome' => 'Financeiro: Relatórios - Visualizar', 'descricao' => 'Permite visualizar relatórios financeiros'],
            ['slug' => 'financeiro.relatorios.exportar_excel', 'nome' => 'Financeiro: Relatórios - Exportar Excel', 'descricao' => 'Permite exportar relatórios financeiros em Excel'],
            ['slug' => 'financeiro.relatorios.exportar_pdf', 'nome' => 'Financeiro: Relatórios - Exportar PDF', 'descricao' => 'Permite exportar relatórios financeiros em PDF'],
            ['slug' => 'conta_azul.visualizar', 'nome' => 'Conta Azul: Visualizar', 'descricao' => 'Permite acessar a integraÃ§Ã£o Conta Azul'],
            ['slug' => 'conta_azul.configurar', 'nome' => 'Conta Azul: Configurar', 'descricao' => 'Permite configurar OAuth, token manual e teste de conexÃ£o'],
            ['slug' => 'conta_azul.importar', 'nome' => 'Conta Azul: Importar', 'descricao' => 'Permite importar dados da Conta Azul para staging'],
            ['slug' => 'conta_azul.conciliar', 'nome' => 'Conta Azul: Conciliar', 'descricao' => 'Permite conciliar e resolver pendÃªncias da Conta Azul'],
            ['slug' => 'conta_azul.auditar', 'nome' => 'Conta Azul: Auditar', 'descricao' => 'Permite consultar batches e logs da Conta Azul'],
            ['slug' => 'google_calendar.visualizar', 'nome' => 'Google Agenda: Visualizar', 'descricao' => 'Permite acessar o painel da Google Agenda'],
            ['slug' => 'google_calendar.configurar', 'nome' => 'Google Agenda: Configurar', 'descricao' => 'Permite conectar conta Google e habilitar agendas'],
            ['slug' => 'google_calendar.criar', 'nome' => 'Google Agenda: Criar', 'descricao' => 'Permite criar eventos e reunioes na Google Agenda'],
            ['slug' => 'google_calendar.editar', 'nome' => 'Google Agenda: Editar', 'descricao' => 'Permite editar eventos da Google Agenda'],
            ['slug' => 'google_calendar.cancelar', 'nome' => 'Google Agenda: Cancelar', 'descricao' => 'Permite cancelar eventos da Google Agenda'],
            ['slug' => 'google_calendar.auditar', 'nome' => 'Google Agenda: Auditar', 'descricao' => 'Permite consultar logs da integracao Google Agenda'],
            ['slug' => 'despesas_recorrentes.visualizar', 'nome' => 'Despesas Recorrentes: Visualizar', 'descricao' => 'Permite listar e visualizar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.criar', 'nome' => 'Despesas Recorrentes: Criar', 'descricao' => 'Permite cadastrar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.editar', 'nome' => 'Despesas Recorrentes: Editar', 'descricao' => 'Permite editar despesas recorrentes'],
            ['slug' => 'despesas_recorrentes.executar', 'nome' => 'Despesas Recorrentes: Executar', 'descricao' => 'Permite executar manualmente a geração de contas a pagar'],
            ['slug' => 'despesas_recorrentes.cancelar', 'nome' => 'Despesas Recorrentes: Cancelar', 'descricao' => 'Permite cancelar despesas recorrentes'],
            ['slug' => 'estoquista.operar', 'nome' => 'Estoquista: Operar', 'descricao' => 'Acesso operacional ao estoque'],
            ['slug' => 'comunicacao.visualizar', 'nome' => 'Comunicação: Visualizar', 'descricao' => 'Permite acessar o painel de comunicação (dashboard, requests e mensagens)'],
            ['slug' => 'comunicacao.templates', 'nome' => 'Comunicação: Templates', 'descricao' => 'Permite criar/editar templates e gerar preview'],
            ['slug' => 'comunicacao.requests.cancelar', 'nome' => 'Comunicação: Cancelar Request', 'descricao' => 'Permite cancelar requests pendentes'],
            ['slug' => 'comunicacao.messages.retry', 'nome' => 'Comunicação: Retry Mensagem', 'descricao' => 'Permite reprocessar mensagens com falha'],
        ];
    }
}
