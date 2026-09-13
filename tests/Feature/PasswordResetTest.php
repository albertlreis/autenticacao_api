<?php

namespace Tests\Feature;

use App\Models\AcessoRefreshToken;
use App\Models\AcessoUsuario;
use App\Notifications\ResetPasswordNotification;
use App\Services\Communication\PasswordResetCommunicationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SENT_MESSAGE = 'Solicitação recebida. O e-mail será enviado em instantes.';

    public function test_usuario_ativo_solicita_reset_e_enfileira_na_comunicacao(): void
    {
        Notification::fake();
        config(['acesso.password_reset_frontend_url' => 'https://sierra.acadsoft.com.br']);
        config(['acesso.password_reset_logo_url' => 'https://sierra.acadsoft.com.br/logo.png']);

        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Reset',
            'email' => 'reset@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $this->mock(PasswordResetCommunicationClient::class)
            ->shouldReceive('queue')
            ->once()
            ->withArgs(function (AcessoUsuario $received, string $url) use ($usuario): bool {
                return $received->is($usuario)
                    && str_starts_with($url, 'https://sierra.acadsoft.com.br/resetar-senha?')
                    && str_contains($url, 'token=')
                    && str_contains($url, 'email=reset%40example.test');
            })
            ->andReturn(['request_id' => 123, 'correlation_id' => 'corr-test']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'RESET@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', self::SENT_MESSAGE)
            ->assertJsonPath('retry_after_seconds', 60);

        Notification::assertNothingSent();
    }

    public function test_notificacao_usa_frontend_local_na_porta_5173(): void
    {
        config(['acesso.password_reset_frontend_url' => 'http://localhost:5173']);
        config(['acesso.password_reset_logo_url' => 'https://sierra.acadsoft.com.br/logo.png']);

        $usuario = (object) ['email' => 'local@example.test'];
        $mail = (new ResetPasswordNotification('token-local'))->toMail($usuario);

        $this->assertStringStartsWith('http://localhost:5173/resetar-senha?', $mail->viewData['resetUrl']);
        $this->assertStringContainsString('token=token-local', $mail->viewData['resetUrl']);
        $this->assertStringContainsString('email=local%40example.test', $mail->viewData['resetUrl']);
        $this->assertStringNotContainsString('localhost:3000', $mail->viewData['resetUrl']);
    }

    public function test_email_inexistente_retorna_mensagem_generica_e_nao_envia_notificacao(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nao-existe@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', self::SENT_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_usuario_inativo_retorna_mensagem_generica_e_nao_envia_notificacao(): void
    {
        Notification::fake();

        AcessoUsuario::create([
            'nome' => 'Usuario Inativo',
            'email' => 'inativo@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => false,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'inativo@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('message', self::SENT_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_limitador_da_aplicacao_informa_tempo_restante(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Muitas Solicitacoes',
            'email' => 'muitas@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $this->mock(PasswordResetCommunicationClient::class)->shouldReceive('queue')->once()
            ->andReturn(['request_id' => 124, 'correlation_id' => 'corr-limit']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $usuario->email])->assertOk();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $usuario->email])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Aguarde alguns instantes antes de solicitar um novo e-mail de redefinição.');

        $this->assertGreaterThan(0, $response->json('retry_after_seconds'));
        $this->assertLessThanOrEqual(60, $response->json('retry_after_seconds'));
    }

    public function test_recusa_da_comunicacao_nao_confirma_envio_e_exclui_token(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Falha',
            'email' => 'falha@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $this->mock(PasswordResetCommunicationClient::class)->shouldReceive('queue')->once()
            ->andThrow(new \RuntimeException('Mensagem não enfileirada'));

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $usuario->email])
            ->assertOk()
            ->assertJsonPath('message', self::SENT_MESSAGE);
        $this->assertDatabaseMissing('password_resets', ['email' => $usuario->email]);
    }

    public function test_timeout_da_comunicacao_nao_confirma_envio(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Erro SMTP',
            'email' => 'smtp@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $this->mock(PasswordResetCommunicationClient::class)->shouldReceive('queue')->once()
            ->andThrow(new \RuntimeException('Timeout simulado'));

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $usuario->email])
            ->assertOk()
            ->assertJsonPath('message', self::SENT_MESSAGE);
    }

    public function test_token_valido_redefine_senha_limpa_obrigatoriedade_e_revoga_sessoes(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Redefinir',
            'email' => 'redefinir@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        Sanctum::actingAs($usuario);
        $usuario->createToken('web-test');

        AcessoRefreshToken::create([
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', 'refresh-token-test'),
            'expires_at' => now()->addDay(),
        ]);

        $token = Password::createToken($usuario);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'redefinir@example.test',
            'token' => $token,
            'nova_senha' => 'SenhaNova123',
            'nova_senha_confirmation' => 'SenhaNova123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Senha redefinida com sucesso.');

        $usuario->refresh();
        $this->assertTrue(Hash::check('SenhaNova123', $usuario->senha));
        $this->assertFalse((bool) $usuario->forcar_troca_senha);
        $this->assertNotNull($usuario->senha_alterada_em);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertNotNull(AcessoRefreshToken::where('usuario_id', $usuario->id)->first()->revoked_at);
    }

    public function test_token_invalido_nao_altera_senha(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Token Invalido',
            'email' => 'token-invalido@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'token-invalido@example.test',
            'token' => 'token-invalido',
            'nova_senha' => 'SenhaNova123',
            'nova_senha_confirmation' => 'SenhaNova123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Link inválido ou expirado. Solicite uma nova redefinição de senha.');

        $usuario->refresh();
        $this->assertTrue(Hash::check('SenhaForte123', $usuario->senha));
        $this->assertTrue((bool) $usuario->forcar_troca_senha);
    }

    public function test_token_expirado_nao_altera_senha(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Token Expirado',
            'email' => 'token-expirado@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $token = Password::createToken($usuario);

        DB::table('password_resets')
            ->where('email', 'token-expirado@example.test')
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'token-expirado@example.test',
            'token' => $token,
            'nova_senha' => 'SenhaNova123',
            'nova_senha_confirmation' => 'SenhaNova123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Link inválido ou expirado. Solicite uma nova redefinição de senha.');

        $this->assertTrue(Hash::check('SenhaForte123', $usuario->refresh()->senha));
    }

    public function test_validacao_rejeita_senha_curta_e_confirmacao_divergente(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Validacao',
            'email' => 'validacao@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $token = Password::createToken($usuario);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'validacao@example.test',
            'token' => $token,
            'nova_senha' => 'curta',
            'nova_senha_confirmation' => 'diferente',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nova_senha']);

        $this->assertTrue(Hash::check('SenhaForte123', $usuario->refresh()->senha));
    }
}
