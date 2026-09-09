<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPerfil;
use App\Services\AuditoriaLogService;
use App\Services\PermissoesCacheService;
use App\Support\Auditoria\AuditoriaDiff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PerfilController extends Controller
{
    public function __construct(private readonly PermissoesCacheService $permissoesCache) {}

    public function index(): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.visualizar');
        return $this->indexAuthorized();
    }

    private function indexAuthorized(): JsonResponse
    {
        return response()->json(AcessoPerfil::with('permissoes')->orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.criar');
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            \App\Saas\AccessPolicy::profileWrite(null, $request->validate(['nome' => 'required|string|max:100', 'permissoes' => 'sometimes|array', 'permissoes.*' => 'integer|exists:acesso_permissoes,id']));
            return $this->storeAuthorized($request);
        });
    }

    private function storeAuthorized(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome'        => 'required|string|max:100',
            'descricao'   => 'nullable|string',
            'permissoes'  => 'sometimes|array',
            'permissoes.*'=> 'integer|exists:acesso_permissoes,id',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $perfil = AcessoPerfil::create($request->only('nome', 'descricao'));

        if ($request->has('permissoes')) {
            $perfil->permissoes()->sync($request->input('permissoes', []));
            $perfil->load('permissoes');
        }

        // Sem usuários ainda, sem cache para invalidar (ok)
        $mudancas = AuditoriaDiff::modelChanges(null, $perfil, ['nome', 'descricao']);
        $mudancas = array_merge(
            $mudancas,
            AuditoriaDiff::listChange('permissoes', [], $this->permissaoSlugs($perfil))
        );
        $this->registrarAuditoriaPerfil('perfil.created', $perfil, 'Perfil criado', $mudancas);

        return response()->json($perfil, 201);
    }

    public function show($id): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.visualizar');
        return $this->showAuthorized($id);
    }

    private function showAuthorized($id): JsonResponse
    {
        $perfil = AcessoPerfil::with('permissoes')->find($id);
        if (!$perfil) return response()->json(['message' => 'Perfil não encontrado'], 404);
        return response()->json($perfil);
    }

    public function update(Request $request, $id): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.editar');
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
            \App\Saas\AccessPolicy::profileWrite(AcessoPerfil::find($id), $request->validate(['nome' => 'sometimes|required|string|max:100', 'permissoes' => 'sometimes|array', 'permissoes.*' => 'integer|exists:acesso_permissoes,id']));
            return $this->updateAuthorized($request, $id);
        });
    }

    private function updateAuthorized(Request $request, $id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);
        if (!$perfil) return response()->json(['message' => 'Perfil não encontrado'], 404);

        $validator = Validator::make($request->all(), [
            'nome'        => 'sometimes|required|string|max:100',
            'descricao'   => 'nullable|string',
            'permissoes'  => 'sometimes|array',
            'permissoes.*'=> 'integer|exists:acesso_permissoes,id',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $before = $perfil->fresh(['permissoes']);
        $permissoesAntes = $this->permissaoSlugs($before);

        $perfil->update($request->only('nome', 'descricao'));

        if ($request->has('permissoes')) {
            $perfil->permissoes()->sync($request->input('permissoes', []));
            $perfil->load('permissoes');

            // invalida cache de todos usuários que possuem esse perfil
            $this->permissoesCache->forgetByPerfilId((int) $perfil->id);
        }

        $perfil = $perfil->fresh(['permissoes']);
        $mudancas = AuditoriaDiff::modelChanges($before, $perfil, ['nome', 'descricao']);
        if ($request->has('permissoes')) {
            $mudancas = array_merge(
                $mudancas,
                AuditoriaDiff::listChange('permissoes', $permissoesAntes, $this->permissaoSlugs($perfil))
            );
        }

        $this->registrarAuditoriaPerfil(
            $request->has('permissoes') ? 'permissoes.synced' : 'perfil.updated',
            $perfil,
            $request->has('permissoes') ? 'Permissoes do perfil sincronizadas' : 'Perfil atualizado',
            $mudancas
        );

        return response()->json($perfil);
    }

    public function destroy($id): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.excluir');
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            \App\Saas\AccessPolicy::profileWrite(AcessoPerfil::find($id), []);
            return $this->destroyAuthorized($id);
        });
    }

    private function destroyAuthorized($id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);
        if (!$perfil) return response()->json(['message' => 'Perfil não encontrado'], 404);

        // invalida cache antes de apagar (ainda dá pra achar usuários do perfil)
        $this->permissoesCache->forgetByPerfilId((int) $perfil->id);

        $before = $perfil->fresh(['permissoes']);
        $mudancas = AuditoriaDiff::modelChanges($before, null, ['nome', 'descricao']);
        $mudancas = array_merge(
            $mudancas,
            AuditoriaDiff::listChange('permissoes', $this->permissaoSlugs($before), [])
        );

        $perfil->delete();
        $this->registrarAuditoriaPerfil('perfil.deleted', $before, 'Perfil removido', $mudancas);

        return response()->json(['message' => 'Perfil removido com sucesso']);
    }
    public function assignPermissao(Request $request, $perfil): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.atribuir_permissao');
        $data = $request->validate(['permissoes' => 'required|array|min:1', 'permissoes.*' => 'integer|exists:acesso_permissoes,id']);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $perfil, $request) {
            $model = AcessoPerfil::findOrFail($perfil);
            $ids = array_values(array_unique(array_merge($model->permissoes()->pluck('acesso_permissoes.id')->all(), $data['permissoes'])));
            \App\Saas\AccessPolicy::profileWrite($model, ['permissoes' => $ids]);
            return $this->updateAuthorized(new Request(['permissoes' => $ids]), $perfil);
        });
    }

    public function removePermissao($perfil, $permissao): JsonResponse
    {
        \App\Saas\AccessPolicy::authorize('perfis.remover_permissao');
        return \Illuminate\Support\Facades\DB::transaction(function () use ($perfil, $permissao) {
            $model = AcessoPerfil::findOrFail($perfil);
            \App\Saas\AccessPolicy::profileWrite($model, []);
            $ids = $model->permissoes()->pluck('acesso_permissoes.id')->reject(fn ($id) => (int) $id === (int) $permissao)->values()->all();
            return $this->updateAuthorized(new Request(['permissoes' => $ids]), $perfil);
        });
    }

    /**
     * @return array<int,string>
     */
    private function permissaoSlugs(?AcessoPerfil $perfil): array
    {
        if (!$perfil) {
            return [];
        }

        $permissoes = $perfil->relationLoaded('permissoes')
            ? $perfil->permissoes
            : $perfil->permissoes()->get();

        return $permissoes->pluck('slug')->filter()->values()->all();
    }

    /**
     * @param array<int,array{campo:string,old?:mixed,new?:mixed,old_value?:mixed,new_value?:mixed,value_type?:string}> $mudancas
     */
    private function registrarAuditoriaPerfil(string $acao, AcessoPerfil $perfil, string $label, array $mudancas): void
    {
        app(AuditoriaLogService::class)->registrar([
            'occurred_at' => now(),
            'tipo' => 'auditoria',
            'categoria' => 'negocio',
            'modulo' => 'acessos',
            'acao' => $acao,
            'label' => $label,
            'message' => $label,
            'entity_type' => AcessoPerfil::class,
            'entity_id' => $perfil->id,
            'source_system' => 'auth',
            'source_kind' => 'business_event',
            'retention_days' => 365,
        ], $mudancas);
    }
}
