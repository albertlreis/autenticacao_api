<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoPerfil;
use App\Services\PermissoesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PerfilController extends Controller
{
    public function __construct(private readonly PermissoesCacheService $permissoesCache) {}

    public function index(): JsonResponse
    {
        return response()->json(AcessoPerfil::with('permissoes')->orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
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
        return response()->json($perfil, 201);
    }

    public function show($id): JsonResponse
    {
        $perfil = AcessoPerfil::with('permissoes')->find($id);
        if (!$perfil) return response()->json(['message' => 'Perfil não encontrado'], 404);
        return response()->json($perfil);
    }

    public function update(Request $request, $id): JsonResponse
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

        $perfil->update($request->only('nome', 'descricao'));

        if ($request->has('permissoes')) {
            $perfil->permissoes()->sync($request->input('permissoes', []));
            $perfil->load('permissoes');

            // invalida cache de todos usuários que possuem esse perfil
            $this->permissoesCache->forgetByPerfilId((int) $perfil->id);
        }

        return response()->json($perfil);
    }

    public function destroy($id): JsonResponse
    {
        $perfil = AcessoPerfil::find($id);
        if (!$perfil) return response()->json(['message' => 'Perfil não encontrado'], 404);

        // invalida cache antes de apagar (ainda dá pra achar usuários do perfil)
        $this->permissoesCache->forgetByPerfilId((int) $perfil->id);

        $perfil->delete();
        return response()->json(['message' => 'Perfil removido com sucesso']);
    }
}
