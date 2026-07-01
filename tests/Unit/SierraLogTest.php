<?php

namespace Tests\Unit;

use App\Support\Logging\SierraLog;
use PHPUnit\Framework\TestCase;

class SierraLogTest extends TestCase
{
    public function test_normalizes_event_names_to_dot_notation(): void
    {
        $this->assertSame(
            'auth.permissions.cache_failed',
            SierraLog::normalizeEventName('Auth.Permissions.Cache Failed')
        );
    }

    public function test_falls_back_when_event_has_no_domain(): void
    {
        $this->assertSame(
            'system.log.unclassified',
            SierraLog::normalizeEventName('falha')
        );
    }
}
