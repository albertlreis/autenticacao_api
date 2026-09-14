<?php

namespace Tests\Unit;

use App\Models\AcessoUsuario;
use App\Services\Communication\PasswordResetCommunicationClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PasswordResetCommunicationClientTest extends TestCase
{
    public function test_envia_token_como_variavel_sensivel_e_aceita_apenas_queued(): void
    {
        config([
            'services.comms' => ['base_url' => 'https://communication.test/api', 'api_key' => 'key', 'api_secret' => 'secret', 'timeout' => 3, 'enabled' => true, 'store_only' => false],
            'acesso.password_reset_logo_url' => 'https://belem.test/logo.png',
            'auth.passwords.users.expire' => 60,
        ]);
        Http::fake(['https://communication.test/api/requests' => Http::response([
            'id' => 99,
            'status' => 'queued',
            'messages' => [['status' => 'queued']],
        ], 201)]);

        $usuario = new AcessoUsuario(['email' => 'user@example.test']);
        $result = app(PasswordResetCommunicationClient::class)->queue(
            $usuario,
            'https://belem.test/resetar-senha?token=token-secreto'
        );

        $this->assertSame(99, $result['request_id']);
        Http::assertSent(fn ($request) => $request['store_only'] === false
            && $request['payload']['messages'][0]['template_code'] === 'auth_password_reset'
            && $request['payload']['messages'][0]['variables']['logo_url'] === 'https://belem.test/logo.png'
            && str_starts_with($request['payload']['messages'][0]['variables']['logo_url'], 'https://')
            && $request['payload']['messages'][0]['sensitive_variables']['reset_url'] === 'https://belem.test/resetar-senha?token=token-secreto'
            && !array_key_exists('reset_url', $request['payload']['messages'][0]['variables']));
    }

    public function test_configura_logo_publico_como_fallback(): void
    {
        $this->assertSame(
            'https://sierra.acadsoft.com.br/logo.png',
            config('acesso.password_reset_logo_url')
        );
    }

    public function test_aceita_resposta_stored_quando_store_only_esta_ativo(): void
    {
        config(['services.comms' => ['base_url' => 'https://communication.test/api', 'api_key' => 'key', 'api_secret' => 'secret', 'timeout' => 3, 'enabled' => true, 'store_only' => true]]);
        Http::fake(['*' => Http::response(['id' => 99, 'status' => 'stored', 'messages' => [['status' => 'stored']]], 201)]);

        $result = app(PasswordResetCommunicationClient::class)->queue(
            new AcessoUsuario(['email' => 'user@example.test']),
            'https://belem.test/resetar-senha?token=token-secreto'
        );

        $this->assertSame(99, $result['request_id']);
        Http::assertSent(fn ($request) => $request['store_only'] === true);
    }

    public function test_recusa_envio_quando_integracao_esta_desabilitada(): void
    {
        config(['services.comms.enabled' => false]);
        Http::fake();

        $this->expectException(RuntimeException::class);
        app(PasswordResetCommunicationClient::class)->queue(
            new AcessoUsuario(['email' => 'user@example.test']),
            'https://belem.test/resetar-senha?token=token-secreto'
        );
    }
}
