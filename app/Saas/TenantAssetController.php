<?php

namespace App\Saas;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class TenantAssetController
{
    public function __invoke(Request $request, string $path)
    {
        abort_unless(config('saas.enabled') && app(TenantContext::class)->tenant(), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0"), 404);
        $disk = (string) $request->query('disk', 'public');
        abort_unless(in_array($disk, ['public', 'local'], true), 403);
        $expires = (string) $request->query('expires', '');
        $key = (string) config('saas.asset_key');
        abort_unless(ctype_digit($expires) && (int) $expires >= time() && strlen($key) >= 32, 403);
        $signature = hash_hmac('sha256', $request->getHost().'|'.$disk.'|'.$path.'|'.$expires, $key);
        abort_unless(hash_equals($signature, (string) $request->query('signature', '')), 403);
        abort_unless(Storage::disk($disk)->exists($path), 404);
        return Storage::disk($disk)->response($path, null, ['Cache-Control' => 'private, max-age=60', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox']);
    }
}
