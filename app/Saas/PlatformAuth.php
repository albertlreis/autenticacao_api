<?php

namespace App\Saas;

use Closure;
use Illuminate\Support\Facades\DB;

final class PlatformAuth
{
    public function handle($request, Closure $next)
    {
        abort_unless(config('saas.enabled') && strtolower($request->getHost()) === strtolower(config('saas.platform_host')), 404);
        $token = DB::connection('saas_central')->table('saas_admin_tokens as tokens')
            ->join('saas_admins as admins', 'admins.id', '=', 'tokens.admin_id')
            ->where('token_hash', hash('sha256', (string) $request->bearerToken()))
            ->where('expires_at', '>', now())->where('admins.active', true)
            ->select('tokens.id', 'tokens.admin_id')->first();
        abort_unless($token, 401);
        $request->attributes->set('platform_admin_id', $token->admin_id);
        $request->attributes->set('platform_token_id', $token->id);
        return $next($request);
    }
}
