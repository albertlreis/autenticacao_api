<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioStoreRequest;
use App\Http\Requests\UsuarioUpdateRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\AcessoUsuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $service
    ) {}

    /**
     * Lista usuários com filtros.
     *
     * Suporta dois modos:
     * - modo padrão: retorna usuários com campos completos (resource), ideal para DataTable.
     * - mode=options: retorna lista leve para combos (id/nome e opcionalmente email).
     *
     * Query params:
     * - q: string (busca por nome/email)
     * - ativo: bool
     * - perfil_id: int (filtra usuários que possuam o perfil)
     * - per_page: int (se informado, pagina)
     * - paginate: bool (força paginação mesmo sem per_page)
     * - mode: "default" | "options"
     * - fields: string (somente no mode=options). ex: "id,nome" ou "id,nome,email"
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ativo = $request->query('ativo', null);
        $perfilId = $request->query('perfil_id', null);

        $mode = (string) $request->query('mode', 'default'); // default|options

        $query = AcessoUsuario::query();

        // Carrega perfis apenas no modo padrão (DataTable)
        if ($mode !== 'options') {
            $query->with('perfis');
        }

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

        // Ordenação
        $query->orderBy('nome');

        // MODE OPTIONS: payload leve p/ select
        if ($mode === 'options') {
            $fields = (string) $request->query('fields', 'id,nome');
            $allowed = ['id', 'nome', 'email'];
            $cols = array_values(array_intersect($allowed, array_map('trim', explode(',', $fields))));

            if (empty($cols)) $cols = ['id', 'nome'];

            $query->select($cols);

            // Em options, nunca pagina (normalmente é usado para autocomplete/select).
            // Se quiser paginação, você pode permitir via paginate=1/per_page, mas geralmente não precisa.
            $list = $query->get();

            return response()->json($list);
        }

        // MODO PADRÃO (DataTable)
        // Se o front não usa "lazy", retornar paginado por padrão pode esconder usuários.
        // Então: só pagina se vier per_page (ou paginate=1).
        $perPage = $request->query('per_page', null);
        $paginate = $request->boolean('paginate', false) || ($perPage !== null && $perPage !== '');

        if ($paginate) {
            $perPage = (int) ($perPage ?: 15);
            $paginator = $query->paginate($perPage);
            return response()->json(UsuarioResource::collection($paginator));
        }

        $list = $query->get();
        return response()->json(UsuarioResource::collection($list));
    }

    /**
     * Cria usuário.
     *
     * @param  UsuarioStoreRequest  $request
     * @return JsonResponse
     */
    public function store(UsuarioStoreRequest $request): JsonResponse
    {
        $usuario = $this->service->criar($request->validated());
        return response()->json(new UsuarioResource($usuario), 201);
    }

    /**
     * Detalha um usuário.
     *
     * @param  AcessoUsuario  $usuario
     * @return JsonResponse
     */
    public function show(AcessoUsuario $usuario): JsonResponse
    {
        $usuario->load('perfis');
        return response()->json(new UsuarioResource($usuario));
    }

    /**
     * Atualiza um usuário.
     *
     * @param  UsuarioUpdateRequest  $request
     * @param  AcessoUsuario  $usuario
     * @return JsonResponse
     */
    public function update(UsuarioUpdateRequest $request, AcessoUsuario $usuario): JsonResponse
    {
        $usuario = $this->service->atualizar($usuario, $request->validated());
        return response()->json(new UsuarioResource($usuario));
    }

    /**
     * Remove um usuário.
     *
     * @param  AcessoUsuario  $usuario
     * @return JsonResponse
     */
    public function destroy(AcessoUsuario $usuario): JsonResponse
    {
        $this->service->remover($usuario);
        return response()->json(['message' => 'Usuário removido com sucesso']);
    }

    /**
     * Associa perfis ao usuário (adiciona sem remover os existentes).
     *
     * POST /usuarios/{usuario}/perfis
     * Body: { "perfis": [1,2] }
     *
     * @param  Request  $request
     * @param  AcessoUsuario  $usuario
     * @return JsonResponse
     */
    public function assignPerfil(Request $request, AcessoUsuario $usuario): JsonResponse
    {
        $data = $request->validate([
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['integer', 'exists:acesso_perfis,id'],
        ]);

        $usuario = $this->service->adicionarPerfis($usuario, $data['perfis']);
        return response()->json(new UsuarioResource($usuario));
    }

    /**
     * Remove um perfil do usuário.
     *
     * DELETE /usuarios/{usuario}/perfis/{perfil}
     *
     * @param  AcessoUsuario  $usuario
     * @param  int|string  $perfil
     * @return JsonResponse
     */
    public function removePerfil(AcessoUsuario $usuario, $perfil): JsonResponse
    {
        $usuario = $this->service->removerPerfil($usuario, (int) $perfil);
        return response()->json(new UsuarioResource($usuario));
    }
}
