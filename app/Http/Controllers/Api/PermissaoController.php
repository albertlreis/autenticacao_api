<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPermissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissaoController extends Controller
{
    /**
     * Exibe uma listagem de permissões.
     */
    public function index(): JsonResponse
    {
        $permissoes = AcessoPermissao::all();
        return response()->json($permissoes);
    }

    /**
     * Cria uma permissão.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome'      => 'required|string|max:100',
            'descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $permissao = AcessoPermissao::create($request->only('nome', 'descricao'));

        return response()->json($permissao, 201);
    }

    /**
     * Exibe uma permissão específica.
     */
    public function show($id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);

        if (!$permissao) {
            return response()->json(['message' => 'Permissão não encontrada'], 404);
        }

        return response()->json($permissao);
    }

    /**
     * Atualiza uma permissão existente.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);

        if (!$permissao) {
            return response()->json(['message' => 'Permissão não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome'      => 'sometimes|required|string|max:100',
            'descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $permissao->update($request->only('nome', 'descricao'));

        return response()->json($permissao);
    }

    /**
     * Remove uma permissão.
     */
    public function destroy($id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);

        if (!$permissao) {
            return response()->json(['message' => 'Permissão não encontrada'], 404);
        }

        $permissao->delete();

        return response()->json(['message' => 'Permissão removida com sucesso']);
    }
}
