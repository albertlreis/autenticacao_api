<?php

namespace Tests\Feature;

use App\Enums\PerfilEnum;
use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessInitialDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_inicial_nao_altera_registros_existentes_e_adiciona_associacoes_faltantes(): void
    {
        $service = new AccessInitialDataService();

        $service->seedPerfis();
        $service->seedPermissoes();
        $service->seedUsuariosPadrao();
        $service->seedAssociacoes();

        $oldTimestamp = '2024-01-02 03:04:05';
        $vendedorPerfilId = DB::table('acesso_perfis')
            ->where('nome', PerfilEnum::VENDEDOR->value)
            ->value('id');
        $vendedorUsuarioId = DB::table('acesso_usuarios')
            ->where('email', 'vendedor1@teste.com')
            ->value('id');
        $homePermissaoId = DB::table('acesso_permissoes')
            ->where('slug', 'home.visualizar')
            ->value('id');
        $parceiroPermissaoId = DB::table('acesso_permissoes')
            ->where('slug', 'parceiros.visualizar')
            ->value('id');

        DB::table('acesso_perfis')
            ->where('id', $vendedorPerfilId)
            ->update([
                'descricao' => 'Descricao preservada',
                'updated_at' => $oldTimestamp,
            ]);

        DB::table('acesso_permissoes')
            ->where('id', $parceiroPermissaoId)
            ->update([
                'nome' => 'Nome preservado',
                'descricao' => 'Descricao preservada',
                'updated_at' => $oldTimestamp,
            ]);

        DB::table('acesso_usuarios')
            ->where('id', $vendedorUsuarioId)
            ->update([
                'nome' => 'Usuario preservado',
                'ativo' => false,
                'updated_at' => $oldTimestamp,
            ]);

        DB::table('acesso_perfil_permissao')
            ->where('id_perfil', $vendedorPerfilId)
            ->where('id_permissao', $homePermissaoId)
            ->update(['updated_at' => $oldTimestamp]);

        DB::table('acesso_perfil_permissao')
            ->where('id_perfil', $vendedorPerfilId)
            ->where('id_permissao', $parceiroPermissaoId)
            ->delete();

        $service->runBootstrap();

        $this->assertSame('Descricao preservada', DB::table('acesso_perfis')->where('id', $vendedorPerfilId)->value('descricao'));
        $this->assertSame($oldTimestamp, (string) DB::table('acesso_perfis')->where('id', $vendedorPerfilId)->value('updated_at'));

        $permissao = DB::table('acesso_permissoes')->where('id', $parceiroPermissaoId)->first();
        $this->assertSame('Nome preservado', $permissao->nome);
        $this->assertSame('Descricao preservada', $permissao->descricao);
        $this->assertSame($oldTimestamp, (string) $permissao->updated_at);

        $usuario = DB::table('acesso_usuarios')->where('id', $vendedorUsuarioId)->first();
        $this->assertSame('Usuario preservado', $usuario->nome);
        $this->assertFalse((bool) $usuario->ativo);
        $this->assertSame($oldTimestamp, (string) $usuario->updated_at);

        $this->assertSame(
            $oldTimestamp,
            (string) DB::table('acesso_perfil_permissao')
                ->where('id_perfil', $vendedorPerfilId)
                ->where('id_permissao', $homePermissaoId)
                ->value('updated_at')
        );

        $this->assertDatabaseHas('acesso_perfil_permissao', [
            'id_perfil' => $vendedorPerfilId,
            'id_permissao' => $parceiroPermissaoId,
        ]);
    }

    public function test_seed_associacoes_atribui_permissoes_financeiras_e_conta_azul_ao_financeiro_sem_duplicar(): void
    {
        $service = new AccessInitialDataService();

        $service->seedPerfis();
        $service->seedPermissoes();
        $service->seedAssociacoes();
        $service->seedAssociacoes();

        $financeiroPerfilId = DB::table('acesso_perfis')
            ->where('nome', PerfilEnum::FINANCEIRO->value)
            ->value('id');

        $expectedSlugs = [
            'contas.pagar.view',
            'contas.pagar.create',
            'contas.pagar.update',
            'contas.pagar.delete',
            'contas.pagar.pagar',
            'contas.pagar.estornar',
            'contas.pagar.exportar_excel',
            'contas.pagar.exportar_pdf',
            'contas.receber.view',
            'contas.receber.create',
            'contas.receber.update',
            'contas.receber.delete',
            'contas.receber.receber',
            'contas.receber.estornar',
            'contas.receber.exportar_excel',
            'contas.receber.exportar_pdf',
            'financeiro.dashboard.visualizar',
            'financeiro.lancamentos.visualizar',
            'financeiro.lancamentos.criar',
            'financeiro.lancamentos.editar',
            'financeiro.lancamentos.excluir',
            'financeiro.lancamentos.exportar',
            'despesas_recorrentes.visualizar',
            'despesas_recorrentes.criar',
            'despesas_recorrentes.editar',
            'despesas_recorrentes.executar',
            'despesas_recorrentes.cancelar',
            'relatorios.visualizar',
            'relatorios.exportar_excel',
            'relatorios.exportar_pdf',
            'home.visualizar',
            'home.kpis',
            'conta_azul.visualizar',
            'conta_azul.configurar',
            'conta_azul.importar',
            'conta_azul.conciliar',
            'conta_azul.auditar',
            'google_calendar.visualizar',
        ];

        $actualSlugs = DB::table('acesso_perfil_permissao')
            ->join('acesso_permissoes', 'acesso_permissoes.id', '=', 'acesso_perfil_permissao.id_permissao')
            ->where('acesso_perfil_permissao.id_perfil', $financeiroPerfilId)
            ->pluck('acesso_permissoes.slug')
            ->all();

        $this->assertEqualsCanonicalizing($expectedSlugs, $actualSlugs);

        $totalAssociacoes = DB::table('acesso_perfil_permissao')
            ->where('id_perfil', $financeiroPerfilId)
            ->count();

        $totalPermissoesDistintas = DB::table('acesso_perfil_permissao')
            ->where('id_perfil', $financeiroPerfilId)
            ->distinct('id_permissao')
            ->count('id_permissao');

        $this->assertSame(count($expectedSlugs), $totalAssociacoes);
        $this->assertSame($totalAssociacoes, $totalPermissoesDistintas);
    }
}
