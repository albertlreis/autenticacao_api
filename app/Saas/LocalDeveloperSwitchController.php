<?php

namespace App\Saas;

use App\Models\AcessoUsuario;
use App\Models\AcessoPerfil;
use App\Services\PermissoesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LocalDeveloperSwitchController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize($request);
        $tenants = DB::connection('saas_central')->table('saas_tenants')->where('status', 'active')
            ->whereNotNull('provisioned_at')->orderBy('name')->get(['id', 'name', 'slug']);
        return response()->json(['tenants' => $tenants]);
    }

    public function switch(Request $request, TenantRegistry $registry, TenantContext $context, PermissoesCacheService $permissions): JsonResponse
    {
        $source = $context->tenant();
        $sourceUser = $this->authorize($request);
        $data = $request->validate(['tenant_id' => ['required', 'string', 'max:64']]);
        $target = $registry->byId($data['tenant_id']);
        abort_unless($target && $target->status === 'active' && $target->provisioned_at, 422, 'Empresa de destino indisponível.');

        $request->user()->currentAccessToken()?->delete();
        $context->leave();
        $context->enter($target);
        $targetUser = AcessoUsuario::where('email', $sourceUser->email)->first();
        if (!$targetUser) {
            $targetUser = AcessoUsuario::create([
                'nome' => $sourceUser->nome, 'email' => $sourceUser->email,
                'telefone' => $sourceUser->telefone, 'cargo' => $sourceUser->cargo,
                'senha' => $sourceUser->senha, 'ativo' => true, 'forcar_troca_senha' => false,
            ]);
        }
        abort_unless($targetUser->ativo, 403, 'Usuário inativo na empresa de destino.');
        $developerProfile = AcessoPerfil::where('codigo', 'desenvolvedor')->firstOrFail();
        $targetUser->perfis()->syncWithoutDetaching([$developerProfile->id]);
        $expiresAt = now()->addMinutes((int) config('acesso.access_token_ttl_minutes', 15));
        $token = $targetUser->createToken('local-developer-switch', ['*'], $expiresAt)->plainTextToken;
        DB::connection('saas_central')->table('saas_events')->insert([
            'tenant_id' => $target->id, 'action' => 'developer.switched',
            'details' => json_encode(['from' => $source?->id, 'to' => $target->id, 'email' => $targetUser->email]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json([
            'target_url' => TenantDomains::url($target),
            'access_token' => $token, 'expires_in' => $expiresAt->diffInSeconds(now()),
            'user' => ['id' => $targetUser->id, 'nome' => $targetUser->nome, 'email' => $targetUser->email,
                'perfis' => TenantAccess::names((int) $targetUser->id), 'permissoes' => $permissions->get($targetUser), ...$context->payload()],
        ]);
    }

    private function authorize(Request $request): AcessoUsuario
    {
        abort_unless(app()->environment(['local', 'testing']) && config('saas.local_developer_switch'), 404);
        $user = $request->user();
        abort_unless($user instanceof AcessoUsuario && $this->isDeveloper($user), 403);
        return $user;
    }

    private function isDeveloper(AcessoUsuario $user): bool
    {
        return TenantAccess::profiles((int) $user->id)->contains(fn ($profile) => TenantAccess::code($profile) === 'desenvolvedor');
    }
}
