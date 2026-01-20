<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcessoRefreshToken;
use App\Models\AcessoUsuario;
use App\Services\PermissoesCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly PermissoesCacheService $permissoesCache) {}

    public function me(Request $request): JsonResponse
    {
        /** @var AcessoUsuario $user */
        $user = $request->user();

        $permissoes = [];
        try {
            $permissoes = $this->permissoesCache->get($user);
        } catch (Throwable $e) {
            Log::error("Erro ao carregar permissões do usuário [{$user->id}]: ".$e->getMessage());
        }

        return response()->json([
            'id'         => $user->id,
            'nome'       => $user->nome,
            'email'      => $user->email,
            'ativo'      => $user->ativo,
            'perfis'     => $user->perfis()->pluck('nome')->toArray(),
            'permissoes' => $permissoes,
        ]);
    }

    /**
     * ERP interno: não expor register público.
     */
    public function register(): JsonResponse
    {
        return response()->json(['message' => 'Endpoint desabilitado (ERP interno)'], 404);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:100',
            'senha' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $email = Str::lower(trim($request->email));
        $key = "login:{$email}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Muitas tentativas. Tente novamente em alguns instantes.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        /** @var AcessoUsuario|null $usuario */
        $usuario = AcessoUsuario::where('email', $email)->first();

        if ($usuario && $usuario->isBloqueado()) {
            return response()->json([
                'message' => 'Usuário temporariamente bloqueado. Tente mais tarde.',
                'bloqueado_ate' => optional($usuario->bloqueado_ate)->toISOString(),
            ], 423);
        }

        if (!$usuario || !Hash::check($request->senha, $usuario->senha)) {
            RateLimiter::hit($key, 60);

            if ($usuario) {
                $usuario->tentativas_login = (int) $usuario->tentativas_login + 1;

                if ($usuario->tentativas_login >= 10) {
                    $usuario->bloqueado_ate = now()->addMinutes(10);
                    $usuario->tentativas_login = 0;
                }

                $usuario->save();
            }

            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if (!$usuario->ativo) {
            return response()->json(['message' => 'Usuário inativo'], 403);
        }

        RateLimiter::clear($key);
        $usuario->tentativas_login = 0;
        $usuario->bloqueado_ate = null;
        $usuario->ultimo_login_em = now();
        $usuario->ultimo_login_ip = $request->ip();
        $usuario->ultimo_login_user_agent = substr((string) $request->userAgent(), 0, 255);
        $usuario->save();

        $accessExpiresAt = now()->addMinutes((int) config('acesso.access_token_ttl_minutes', 15));
        $deviceName = $request->input('device_name') ?: ('web-' . substr(sha1((string) $request->userAgent()), 0, 8));

        $newAccess = $usuario->createToken($deviceName, ['*'], $accessExpiresAt);

        $plainRefresh = $this->newRefreshTokenPlain();
        $refresh = AcessoRefreshToken::create([
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', $plainRefresh),
            'expires_at' => now()->addDays((int) config('acesso.refresh_token_ttl_days', 30)),
            'created_ip' => $request->ip(),
            'created_user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        try {
            $this->permissoesCache->forget((int) $usuario->id);
            $permissoes = $this->permissoesCache->get($usuario);
        } catch (Throwable $e) {
            $permissoes = [];
            Log::error("Erro ao cachear permissões no login [$usuario->id]: ".$e->getMessage());
        }

        return response()
            ->json([
                'access_token' => $newAccess->plainTextToken,
                'token_type'   => 'Bearer',
                'expires_in'   => $accessExpiresAt->diffInSeconds(now()),
                'user' => [
                    'id'         => $usuario->id,
                    'nome'       => $usuario->nome,
                    'email'      => $usuario->email,
                    'ativo'      => $usuario->ativo,
                    'perfis'     => $usuario->perfis()->pluck('nome')->toArray(),
                    'permissoes' => $permissoes,
                ],
            ])
            ->withCookie($this->refreshCookie($plainRefresh));
    }

    public function refresh(Request $request): JsonResponse
    {
        $key = "refresh:{$request->ip()}";
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Muitas requisições de refresh. Tente novamente em instantes.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }
        RateLimiter::hit($key);

        $plain = (string) ($request->cookie(config('acesso.refresh_cookie.name')) ?: $request->input('refresh_token'));

        if ($plain === '') {
            return response()->json(['message' => 'Refresh token ausente'], 401);
        }

        $hash = hash('sha256', $plain);

        /** @var AcessoRefreshToken|null $refresh */
        $refresh = AcessoRefreshToken::where('token_hash', $hash)->first();

        if (!$refresh || !$refresh->isValid()) {
            return response()->json(['message' => 'Refresh token inválido'], 401);
        }

        $usuario = $refresh->usuario;
        if (!$usuario || !$usuario->ativo) {
            return response()->json(['message' => 'Usuário inválido/inativo'], 403);
        }

        $plainNew = $this->newRefreshTokenPlain();
        $newRefresh = AcessoRefreshToken::create([
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', $plainNew),
            'expires_at' => now()->addDays((int) config('acesso.refresh_token_ttl_days', 30)),
            'created_ip' => $request->ip(),
            'created_user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $refresh->revoked_at = now();
        $refresh->last_used_at = now();
        $refresh->replaced_by_id = $newRefresh->id;
        $refresh->save();

        $accessExpiresAt = now()->addMinutes((int) config('acesso.access_token_ttl_minutes', 15));
        $newAccess = $usuario->createToken('web-refresh', ['*'], $accessExpiresAt);

        return response()
            ->json([
                'access_token' => $newAccess->plainTextToken,
                'token_type'   => 'Bearer',
                'expires_in'   => $accessExpiresAt->diffInSeconds(now()),
            ])
            ->withCookie($this->refreshCookie($plainNew));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AcessoUsuario $user */
        $user = $request->user();

        $request->user()->currentAccessToken()?->delete();

        $plain = (string) $request->cookie(config('acesso.refresh_cookie.name'));
        if ($plain !== '') {
            $hash = hash('sha256', $plain);
            AcessoRefreshToken::where('usuario_id', $user->id)
                ->where('token_hash', $hash)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'last_used_at' => now()]);
        }

        return response()
            ->json(['message' => 'Logout realizado com sucesso'])
            ->withCookie($this->forgetRefreshCookie());
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var AcessoUsuario $user */
        $user = $request->user();

        $user->tokens()->delete();

        AcessoRefreshToken::where('usuario_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'last_used_at' => now()]);

        return response()
            ->json(['message' => 'Logout global realizado com sucesso'])
            ->withCookie($this->forgetRefreshCookie());
    }

    private function newRefreshTokenPlain(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function refreshCookie(string $plain): Cookie
    {
        $name = (string) config('acesso.refresh_cookie.name');
        $minutes = ((int) config('acesso.refresh_token_ttl_days', 30)) * 24 * 60;

        return cookie(
            $name,
            $plain,
            $minutes,
            (string) config('acesso.refresh_cookie.path', '/'),
            config('acesso.refresh_cookie.domain'),
            (bool) config('acesso.refresh_cookie.secure', true),
            true,
            false,
            (string) config('acesso.refresh_cookie.same_site', 'None'),
        );
    }

    private function forgetRefreshCookie(): Cookie
    {
        return cookie()->forget(
            (string) config('acesso.refresh_cookie.name'),
            (string) config('acesso.refresh_cookie.path', '/'),
            config('acesso.refresh_cookie.domain')
        );
    }
}
