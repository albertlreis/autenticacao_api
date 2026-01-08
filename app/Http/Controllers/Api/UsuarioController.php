<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoUsuario;
use App\Services\PermissoesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    public function __construct(private readonly PermissoesCacheService $permissoesCache) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ativo = $request->query('ativo', null);
        $perfilId = $request->query('perfil_id', null);

        $query = AcessoUsuario::query()->with('perfis');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('nome', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($ativo !== null && $ativo !== '') {
            $query->where('ativo', filter_var($ativo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($perfilId) {
            $query->whereHas('perfis', fn ($p) => $p->where('acesso_perfis.id', (int) $perfilId));
        }

        $perPage = (int) $request->query('per_page', 15);

        return response()->json(
            $query->orderBy('nome')->paginate($perPage)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome'   => 'required|string|max:255',
            'email'  => 'required|string|email|max:100|unique:acesso_usuarios',
            'senha'  => 'required|string|min:8',
            'ativo'  => 'sometimes|boolean',
            'perfis' => 'sometimes|array',
            'perfis.*' => 'integer|exists:acesso_perfis,id',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $usuario = AcessoUsuario::create([
            'nome'  => $request->nome,
            'email' => $request->email,
            'senha' => Hash::make($request->senha),
            'ativo' => $request->has('ativo') ? (bool) $request->ativo : true,
            'senha_alterada_em' => now(),
        ]);

        if ($request->has('perfis')) {
            $usuario->perfis()->sync($request->perfis);
            $usuario->load('perfis');
        }

        $this->permissoesCache->forget((int) $usuario->id);

        return response()->json($usuario, 201);
    }

    public function show($id): JsonResponse
    {
        $usuario = AcessoUsuario::with('perfis')->find($id);
        if (!$usuario) return response()->json(['message' => 'Usuário não encontrado'], 404);
        return response()->json($usuario);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $usuario = AcessoUsuario::find($id);
        if (!$usuario) return response()->json(['message' => 'Usuário não encontrado'], 404);

        $validator = Validator::make($request->all(), [
            'nome'   => 'sometimes|required|string|max:255',
            'email'  => 'sometimes|required|string|email|max:100|unique:acesso_usuarios,email,' . $usuario->id,
            'ativo'  => 'sometimes|boolean',
            'perfis' => 'sometimes|array',
            'perfis.*' => 'integer|exists:acesso_perfis,id',
            'senha'  => 'sometimes|nullable|string|min:8',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        if ($request->has('nome')) $usuario->nome = $request->nome;
        if ($request->has('email')) $usuario->email = $request->email;
        if ($request->has('ativo')) $usuario->ativo = (bool) $request->ativo;

        if ($request->filled('senha')) {
            $usuario->senha = Hash::make($request->senha);
            $usuario->senha_alterada_em = now();
        }

        $usuario->save();

        if ($request->has('perfis')) {
            $usuario->perfis()->sync($request->perfis);
            $usuario->load('perfis');
        }

        // Perfis mudaram => invalida cache de permissões
        $this->permissoesCache->forget((int) $usuario->id);

        return response()->json($usuario);
    }

    public function destroy($id): JsonResponse
    {
        $usuario = AcessoUsuario::find($id);
        if (!$usuario) return response()->json(['message' => 'Usuário não encontrado'], 404);

        $this->permissoesCache->forget((int) $usuario->id);

        $usuario->tokens()->delete(); // remove access tokens :contentReference[oaicite:9]{index=9}
        $usuario->delete();

        return response()->json(['message' => 'Usuário removido com sucesso']);
    }

    public function listarVendedores(): JsonResponse
    {
        $vendedores = AcessoUsuario::whereHas('perfis', function ($query) {
            $query->where('nome', 'Vendedor');
        })
            ->where('ativo', true)
            ->select('id', 'nome')
            ->orderBy('nome')
            ->get();

        return response()->json($vendedores);
    }
}
