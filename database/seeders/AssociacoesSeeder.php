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
        $devPerfil = DB::table('acesso_perfis')->where('nome', PerfilEnum::DESENVOLVEDOR->value)->first();
        $financeiroPerfil = DB::table('acesso_perfis')->where('nome', PerfilEnum::FINANCEIRO->value)->first();
        $estoquistaPerfil = DB::table('acesso_perfis')->where('nome', PerfilEnum::ESTOQUISTA->value)->first();

        $usuarios = DB::table('acesso_usuarios')->get();

        foreach ($usuarios as $usuario) {
            $perfil = match (true) {
                str_contains($usuario->email, 'dev')        => $devPerfil,
                str_contains($usuario->email, 'admin')      => $adminPerfil,
                str_contains($usuario->email, 'financeiro') => $financeiroPerfil,
                str_contains($usuario->email, 'estoquista')  => $estoquistaPerfil,
                default                                      => $vendedorPerfil,
            };

            DB::table('acesso_usuario_perfil')->insert([
                'id_usuario' => $usuario->id,
                'id_perfil'  => $perfil->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Permissões do perfil Financeiro
        $financeiroPerms = DB::table('acesso_permissoes')->whereIn('slug', [
            'contas.pagar.view','contas.pagar.create','contas.pagar.update','contas.pagar.delete',
            'contas.pagar.pagar','contas.pagar.estornar','contas.pagar.exportar_excel','contas.pagar.exportar_pdf',
            'contas.receber.view','contas.receber.create','contas.receber.update','contas.receber.delete',
            'contas.receber.receber','contas.receber.estornar','contas.receber.exportar_excel','contas.receber.exportar_pdf',
            'relatorios.visualizar','relatorios.exportar_excel','relatorios.exportar_pdf',
            'home.visualizar','home.kpis'
        ])->get();

        foreach ($financeiroPerms as $perm) {
            DB::table('acesso_perfil_permissao')->insert([
                'id_perfil'    => $financeiroPerfil->id,
                'id_permissao' => $perm->id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Permissões do perfil Estoquista
        $estoquistaPerms = DB::table('acesso_permissoes')->whereIn('slug', [
            'depositos.visualizar','depositos.criar','depositos.editar',
            'estoque.movimentacao','estoque.historico','estoque.caixa','estoque.transferir','estoque.logs',
            'produtos.visualizar','produtos.importar',
            'home.visualizar'
        ])->get();

        foreach ($estoquistaPerms as $perm) {
            DB::table('acesso_perfil_permissao')->insert([
                'id_perfil'    => $estoquistaPerfil->id,
                'id_permissao' => $perm->id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Permissões
        $todasPermissoes = DB::table('acesso_permissoes')->get();

        foreach ($todasPermissoes as $perm) {
            DB::table('acesso_perfil_permissao')->insert([
                'id_perfil' => $devPerfil->id,
                'id_permissao' => $perm->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!in_array($perm->slug, [
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
            ])) {
                DB::table('acesso_perfil_permissao')->insert([
                    'id_perfil' => $adminPerfil->id,
                    'id_permissao' => $perm->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (
                str_starts_with($perm->slug, 'clientes.') ||
                (
                    str_starts_with($perm->slug, 'produtos.') &&
                    !in_array($perm->slug, [
                        'produtos.gerenciar',
                        'produtos.criar',
                        'produtos.editar',
                        'produtos.excluir',
                        'produtos.importar',
                        'produtos.outlet.cadastrar',
                        'produtos.outlet.editar',
                        'produtos.outlet.excluir',
                        'produtos.configurar_outlet'
                    ])
                ) ||
                str_starts_with($perm->slug, 'produto_variacoes.') ||
                (
                    str_starts_with($perm->slug, 'pedidos.') &&
                    !in_array($perm->slug, ['pedidos.visualizar.todos', 'pedidos.estatisticas'])
                ) ||
                (
                    str_starts_with($perm->slug, 'carrinhos.') &&
                    $perm->slug !== 'carrinhos.visualizar.todos'
                ) ||
                (
                    str_starts_with($perm->slug, 'consignacoes.') &&
                    $perm->slug !== 'consignacoes.vencendo.todos'
                ) ||
                str_starts_with($perm->slug, 'home.')
                ||
                ($perm->slug === 'fornecedores.visualizar')
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
