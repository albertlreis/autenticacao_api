<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPermissao;
use App\Services\AuditoriaLogService;
use App\Support\Auditoria\AuditoriaDiff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissaoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AcessoPermissao::orderBy('slug')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug'      => 'required|string|max:100|unique:acesso_permissoes,slug',
            'nome'      => 'required|string|max:100',
            'descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $permissao = AcessoPermissao::create($request->only('slug', 'nome', 'descricao'));
        $this->registrarAuditoriaPermissao(
            'permissao.created',
            $permissao,
            'Permissao criada',
            AuditoriaDiff::modelChanges(null, $permissao, ['slug', 'nome', 'descricao'])
        );

        return response()->json($permissao, 201);
    }

    public function show($id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);
        if (!$permissao) return response()->json(['message' => 'Permissão não encontrada'], 404);
        return response()->json($permissao);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);
        if (!$permissao) return response()->json(['message' => 'Permissão não encontrada'], 404);

        $validator = Validator::make($request->all(), [
            'slug'      => 'sometimes|required|string|max:100|unique:acesso_permissoes,slug,' . $permissao->id,
            'nome'      => 'sometimes|required|string|max:100',
            'descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $before = $permissao->fresh();
        $permissao->update($request->only('slug', 'nome', 'descricao'));
        $permissao = $permissao->fresh();

        $this->registrarAuditoriaPermissao(
            'permissao.updated',
            $permissao,
            'Permissao atualizada',
            AuditoriaDiff::modelChanges($before, $permissao, ['slug', 'nome', 'descricao'])
        );

        return response()->json($permissao);
    }

    public function destroy($id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);
        if (!$permissao) return response()->json(['message' => 'Permissão não encontrada'], 404);

        $before = $permissao->fresh();
        $mudancas = AuditoriaDiff::modelChanges($before, null, ['slug', 'nome', 'descricao']);

        $permissao->delete();
        $this->registrarAuditoriaPermissao('permissao.deleted', $before, 'Permissao removida', $mudancas);
        return response()->json(['message' => 'Permissão removida com sucesso']);
    }
    /**
     * @param array<int,array{campo:string,old?:mixed,new?:mixed,old_value?:mixed,new_value?:mixed,value_type?:string}> $mudancas
     */
    private function registrarAuditoriaPermissao(string $acao, AcessoPermissao $permissao, string $label, array $mudancas): void
    {
        app(AuditoriaLogService::class)->registrar([
            'occurred_at' => now(),
            'tipo' => 'auditoria',
            'categoria' => 'negocio',
            'modulo' => 'acessos',
            'acao' => $acao,
            'label' => $label,
            'message' => $label,
            'entity_type' => AcessoPermissao::class,
            'entity_id' => $permissao->id,
            'source_system' => 'auth',
            'source_kind' => 'business_event',
            'retention_days' => 365,
        ], $mudancas);
    }
}
