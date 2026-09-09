<?php

namespace App\Saas;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;

final class TenantRateLimiter extends RateLimiter
{
    public function __construct(RateLimiter $original)
    {
        parent::__construct($original->cache);
        $this->limiters = $original->limiters;
    }

    public function useStore(Repository $cache): void
    {
        $this->cache = $cache;
    }
}
