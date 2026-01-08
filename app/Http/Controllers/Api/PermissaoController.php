<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPermissao;
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

        $permissao->update($request->only('slug', 'nome', 'descricao'));
        return response()->json($permissao);
    }

    public function destroy($id): JsonResponse
    {
        $permissao = AcessoPermissao::find($id);
        if (!$permissao) return response()->json(['message' => 'Permissão não encontrada'], 404);

        $permissao->delete();
        return response()->json(['message' => 'Permissão removida com sucesso']);
    }
}
