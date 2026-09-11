<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestContextMiddlewareTest extends TestCase
{
    public function test_health_response_preserves_request_id_header(): void
    {
        $this->getJson('/api/v1/health', ['X-Request-Id' => 'test-request-123'])
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'autenticacao-api',
            ])
            ->assertHeader('X-Request-Id', 'test-request-123');
    }

    public function test_saas_liveness_preserves_the_public_health_contract(): void
    {
        config([
            'saas.enabled' => true,
            'saas.dedicated_tenant_id' => null,
            'saas.platform_host' => 'platform.sierra.test',
        ]);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'autenticacao-api',
            ]);
    }
}
