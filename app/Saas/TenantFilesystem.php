<?php

namespace App\Saas;

use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

final class TenantFilesystem extends FilesystemAdapter
{
    public function url($path)
    {
        $key = config('saas.asset_key');
        if (strlen((string) $key) < 32) throw new RuntimeException('SAAS_ASSET_KEY must contain at least 32 characters.');
        $host = app(TenantContext::class)->tenant()->slug.'.'.config('saas.base_domain');
        $expires = time() + 900;
        $path = ltrim($path, '/');
        $disk = $this->config['tenant_disk'];
        $signature = hash_hmac('sha256', $host.'|'.$disk.'|'.$path.'|'.$expires, $key);
        $authority = $host.(config('saas.url_port') ? ':'.(int) config('saas.url_port') : '');
        return config('saas.scheme').'://'.$authority.'/storage/'.implode('/', array_map('rawurlencode', explode('/', $path))).'?'.http_build_query(['disk' => $disk, 'expires' => $expires, 'signature' => $signature]);
    }
}
