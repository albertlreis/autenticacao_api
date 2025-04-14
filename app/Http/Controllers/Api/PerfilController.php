<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PerfilController extends Controller
{
    /**
     * Exibe uma listagem de perfis.
     */
    public function index(): JsonResponse
    {
        $perfis = AcessoPerfil::with('permissoes')->get();
        return response()->json($perfis);
    }

    /**
     * Cria um perfil.
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

        $perfil = AcessoPerfil::create($request->only('nome', 'descricao'));

        return response()->json($perfil, 201);
    }

    /**
     * Exibe um perfil específico.
     */
    public function show($id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        return response()->json($perfil);
    }

    /**
     * Atualiza um perfil existente.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome'      => 'sometimes|required|string|max:100',
            'descricao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $perfil->update($request->only('nome', 'descricao'));

        return response()->json($perfil);
    }

    /**
     * Remove um perfil.
     */
    public function destroy($id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        $perfil->delete();

        return response()->json(['message' => 'Perfil removido com sucesso']);
    }
}
