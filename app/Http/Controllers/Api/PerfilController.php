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

        if ($request->has('permissoes')) {
            $perfil->permissoes()->sync($request->input('permissoes'));
            $perfil->load('permissoes');
        }

        return response()->json($perfil, 201);
    }

    /**
     * Exibe um perfil específico.
     */
    public function show($id): JsonResponse
    {
        $perfil = AcessoPerfil::with('permissoes')->find($id);

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

        if ($request->has('permissoes')) {
            $perfil->permissoes()->sync($request->input('permissoes'));
            $perfil->load('permissoes');
        }

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

    /**
     * Associa uma permissão a um perfil.
     * O request deve conter o campo 'id_permissao'.
     */
    public function assignPermissao(Request $request, $perfil): JsonResponse
    {
        $perfil = AcessoPerfil::find($perfil);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_permissao' => 'required|integer|exists:permissoes,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $idPermissao = $request->input('id_permissao');

        if (!$perfil->permissoes()->where('permissoes.id', $idPermissao)->exists()) {
            $perfil->permissoes()->attach($idPermissao);
        }

        return response()->json([
            'message' => 'Permissão associada com sucesso',
            'perfil'  => $perfil->load('permissoes')
        ]);
    }

    /**
     * Remove a associação de uma permissão de um perfil.
     */
    public function removePermissao($perfil, $permissao): JsonResponse
    {
        $perfil = AcessoPerfil::find($perfil);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        if (!$perfil->permissoes()->where('permissoes.id', $permissao)->exists()) {
            return response()->json(['message' => 'Permissão não associada a este perfil'], 404);
        }

        $perfil->permissoes()->detach($permissao);

        return response()->json([
            'message' => 'Permissão removida com sucesso',
            'perfil'  => $perfil->load('permissoes')
        ]);
    }
}
