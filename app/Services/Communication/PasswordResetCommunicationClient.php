<?php

namespace App\Services\Communication;

use App\Models\AcessoUsuario;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PasswordResetCommunicationClient
{
    /** @return array{request_id:int|string|null,correlation_id:string} */
    public function queue(AcessoUsuario $usuario, string $resetUrl): array
    {
        $baseUrl = rtrim((string) config('services.comms.base_url'), '/');
        $apiKey = trim((string) config('services.comms.api_key'));
        $apiSecret = trim((string) config('services.comms.api_secret'));
        $storeOnly = (bool) config('services.comms.store_only', true);

        if (! config('services.comms.enabled') || $baseUrl === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Communication API is not configured.');
        }

        $correlationId = (string) Str::uuid();
        $reference = 'password-reset:' . $correlationId;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('services.comms.timeout', 10)))
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'X-API-SECRET' => $apiSecret,
                    'X-Correlation-Id' => $correlationId,
                ])
                ->post($baseUrl . '/requests', [
                    'source' => 'sierra-auth',
                    'campaign' => 'password_reset',
                    'external_id' => $reference,
                    'correlation_id' => $correlationId,
                    'store_only' => $storeOnly,
                    'payload' => [
                        'messages' => [[
                            'channel' => 'email',
                            'to_email' => $usuario->email,
                            'template_code' => 'auth_password_reset',
                            'client_reference' => $reference,
                            'variables' => [
                                'expiration_minutes' => (int) config('auth.passwords.users.expire', 60),
                                'logo_url' => (string) config('acesso.password_reset_logo_url'),
                            ],
                            'sensitive_variables' => [
                                'reset_url' => $resetUrl,
                            ],
                        ]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Communication API connection failed.', 0, $e);
        }

        $messages = $response->json('messages');
        $expectedStatus = $storeOnly ? 'stored' : 'queued';
        $queued = $response->successful()
            && $response->json('status') === $expectedStatus
            && is_array($messages)
            && count($messages) > 0
            && collect($messages)->every(fn ($message) => ($message['status'] ?? null) === $expectedStatus);

        if (!$queued) {
            throw new RuntimeException('Communication API did not queue the message.');
        }

        return [
            'request_id' => $response->json('id'),
            'correlation_id' => $correlationId,
        ];
    }
}
