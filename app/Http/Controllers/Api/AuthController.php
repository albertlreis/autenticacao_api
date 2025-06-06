<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AuthController extends Controller
{
    public function me(): JsonResponse
    {
        $user = Auth::user();
        $cacheKey = 'permissoes_usuario_' . $user->id;

        if (!Cache::has($cacheKey)) {
            $permissoes = $user->perfis()
                ->with('permissoes')
                ->get()
                ->pluck('permissoes')
                ->flatten()
                ->pluck('slug')
                ->unique()
                ->toArray();

            try {
                Cache::put($cacheKey, $permissoes, now()->addHours(6));
            } catch (Throwable $e) {
                Log::error("Erro ao salvar cache de permissões do usuário [{$user->id}]: " . $e->getMessage());
            }
        }

        return response()->json([
            'id'         => $user->id,
            'nome'       => $user->nome,
            'email'      => $user->email,
            'permissoes' => Cache::get($cacheKey, []),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome'  => 'required|string|max:255',
            'email' => 'required|string|email|max:100|unique:acesso_usuarios',
            'senha' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $usuario = AcessoUsuario::create([
            'nome'  => $request->nome,
            'email' => $request->email,
            'senha' => Hash::make($request->senha),
            'ativo' => true,
        ]);

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'senha' => 'required',
        ]);

        $usuario = AcessoUsuario::where('email', $request->email)->first();

        if (!$usuario || !Hash::check($request->senha, $usuario->senha)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        $perfis = $usuario->perfis()->pluck('nome')->toArray();

        $permissoes = $usuario->perfis()
            ->with('permissoes')
            ->get()
            ->pluck('permissoes')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->toArray();

        try {
            Cache::put('permissoes_usuario_' . $usuario->id, $permissoes, now()->addHours(6));
        } catch (Throwable $e) {
            Log::error("Erro ao salvar cache de permissões no login do usuário [{$usuario->id}]: " . $e->getMessage());
        }

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
            'user'         => [
                'id'         => $usuario->id,
                'nome'       => $usuario->nome,
                'email'      => $usuario->email,
                'ativo'      => $usuario->ativo,
                'perfis'     => $perfis,
                'permissoes' => $permissoes,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }
}
