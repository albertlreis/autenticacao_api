<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoUsuario;
use App\Models\AcessoPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    /**
     * Exibe a listagem de usuários.
     */
    public function index(): JsonResponse
    {
        $usuarios = AcessoUsuario::with('perfis')->get();
        return response()->json($usuarios);
    }

    /**
     * Cria um usuário.
     * Este método pode ser utilizado por administradores para criação de usuários.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome'  => 'required|string|max:255',
            'email' => 'required|string|email|max:100|unique:acesso_usuarios',
            'senha' => 'required|string|min:6',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $usuario = AcessoUsuario::create([
            'nome'  => $request->nome,
            'email' => $request->email,
            'senha' => Hash::make($request->senha),
            'ativo' => $request->has('ativo') ? $request->ativo : true,
        ]);

        return response()->json($usuario, 201);
    }

    /**
     * Exibe um usuário específico.
     */
    public function show($id): JsonResponse
    {
        $usuario = AcessoUsuario::with('perfis')->find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        return response()->json($usuario);
    }

    /**
     * Atualiza os dados de um usuário.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $usuario = AcessoUsuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome'  => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:100|unique:acesso_usuarios,email,'.$usuario->id,
            'senha' => 'sometimes|required|string|min:6',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->has('nome')) {
            $usuario->nome = $request->nome;
        }
        if ($request->has('email')) {
            $usuario->email = $request->email;
        }
        if ($request->has('senha')) {
            $usuario->senha = Hash::make($request->senha);
        }
        if ($request->has('ativo')) {
            $usuario->ativo = $request->ativo;
        }

        $usuario->save();

        return response()->json($usuario);
    }

    /**
     * Remove um usuário.
     */
    public function destroy($id): JsonResponse
    {
        $usuario = AcessoUsuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $usuario->delete();

        return response()->json(['message' => 'Usuário removido com sucesso']);
    }

    /**
     * Associa um perfil a um usuário.
     * Espera-se que o request possua o campo 'id_perfil'.
     */
    public function assignPerfil(Request $request, $usuario): JsonResponse
    {
        $usuario = AcessoUsuario::find($usuario);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_perfil' => 'required|integer|exists:acesso_perfis,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $idPerfil = $request->input('id_perfil');
        $perfil = AcessoPerfil::find($idPerfil);

        if (!$perfil) {
            return response()->json(['message' => 'Perfil não encontrado'], 404);
        }

        // Associa o perfil, evitando duplicação
        if (!$usuario->perfis()->where('acesso_perfis.id', $idPerfil)->exists()) {
            $usuario->perfis()->attach($idPerfil);
        }

        return response()->json([
            'message' => 'Perfil associado com sucesso',
            'usuario' => $usuario->load('perfis')
        ]);
    }

    /**
     * Remove a associação de um perfil de um usuário.
     */
    public function removePerfil($usuario, $perfil): JsonResponse
    {
        $usuario = AcessoUsuario::find($usuario);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        if (!$usuario->perfis()->where('id', $perfil)->exists()) {
            return response()->json(['message' => 'Perfil não associado a este usuário'], 404);
        }

        $usuario->perfis()->detach($perfil);

        return response()->json([
            'message' => 'Perfil removido com sucesso',
            'usuario' => $usuario->load('perfis')
        ]);
    }
}
