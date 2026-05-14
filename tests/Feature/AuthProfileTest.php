<?php

namespace Tests\Feature;

use App\Models\AcessoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    private function fakePngUpload(string $name = 'avatar.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar-test-');
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_login_retorna_flag_de_troca_obrigatoria_de_senha(): void
    {
        AcessoUsuario::create([
            'nome' => 'Usuario Forcado',
            'email' => 'forcado@example.test',
            'telefone' => '11999998888',
            'cargo' => 'Vendedor',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'forcado@example.test',
            'senha' => 'SenhaForte123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.forcar_troca_senha', true)
            ->assertJsonPath('user.telefone', '11999998888')
            ->assertJsonPath('user.cargo', 'Vendedor')
            ->assertJsonStructure(['user' => ['avatar_url', 'ultimo_login_em', 'senha_alterada_em']]);
    }

    public function test_login_retorna_expiracao_de_access_token_de_24_horas(): void
    {
        AcessoUsuario::create([
            'nome' => 'Usuario Sessao',
            'email' => 'sessao@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'sessao@example.test',
            'senha' => 'SenhaForte123',
        ]);

        $response->assertOk();
        $expiresIn = (int) $response->json('expires_in');

        $this->assertGreaterThanOrEqual(1439 * 60, $expiresIn);
        $this->assertLessThanOrEqual(1440 * 60, $expiresIn);
    }

    public function test_usuario_com_troca_obrigatoria_recebe_423_em_rotas_bloqueadas(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Bloqueado',
            'email' => 'bloqueado@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/usuarios')
            ->assertStatus(423)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_auth_me_sem_usuario_resolve_com_401(): void
    {
        $request = Request::create('/api/v1/auth/me', 'GET');
        $response = app(AuthController::class)->me($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthenticated.'], $response->getData(true));
    }

    public function test_usuario_troca_senha_propria_e_limpa_obrigatoriedade(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Perfil',
            'email' => 'perfil@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->putJson('/api/v1/auth/password', [
            'senha_atual' => 'SenhaForte123',
            'nova_senha' => 'SenhaNova123',
            'nova_senha_confirmation' => 'SenhaNova123',
        ]);

        $response->assertOk()
            ->assertJsonPath('forcar_troca_senha', false);

        $usuario->refresh();
        $this->assertTrue(Hash::check('SenhaNova123', $usuario->senha));
        $this->assertFalse((bool) $usuario->forcar_troca_senha);
        $this->assertNotNull($usuario->senha_alterada_em);
    }

    public function test_usuario_atualiza_dados_do_proprio_perfil(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Perfil',
            'email' => 'perfil-dados@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->patchJson('/api/v1/auth/me', [
            'nome' => 'Usuario Atualizado',
            'telefone' => ' 11988887777 ',
            'cargo' => ' Consultor ',
        ]);

        $response->assertOk()
            ->assertJsonPath('nome', 'Usuario Atualizado')
            ->assertJsonPath('telefone', '11988887777')
            ->assertJsonPath('cargo', 'Consultor');

        $usuario->refresh();
        $this->assertSame('Usuario Atualizado', $usuario->nome);
        $this->assertSame('11988887777', $usuario->telefone);
        $this->assertSame('Consultor', $usuario->cargo);
    }

    public function test_usuario_envia_avatar_valido(): void
    {
        Storage::fake('public');

        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Avatar',
            'email' => 'avatar@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->post('/api/v1/auth/avatar', [
            'avatar' => $this->fakePngUpload(),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonStructure(['avatar_url']);

        $usuario->refresh();
        $this->assertNotNull($usuario->avatar_path);
        Storage::disk('public')->assertExists($usuario->avatar_path);
    }

    public function test_usuario_substitui_avatar_e_remove_arquivo_anterior(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old-avatar');

        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Avatar',
            'email' => 'avatar-substituir@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'avatar_path' => 'avatars/old.png',
        ]);

        Sanctum::actingAs($usuario);

        $this->post('/api/v1/auth/avatar', [
            'avatar' => $this->fakePngUpload(),
        ], ['Accept' => 'application/json'])->assertOk();

        $usuario->refresh();
        Storage::disk('public')->assertMissing('avatars/old.png');
        Storage::disk('public')->assertExists($usuario->avatar_path);
    }

    public function test_upload_de_avatar_rejeita_arquivo_invalido(): void
    {
        Storage::fake('public');

        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Avatar',
            'email' => 'avatar-invalido@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $this->post('/api/v1/auth/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($usuario->refresh()->avatar_path);
    }

    public function test_senha_atual_incorreta_nao_altera_senha(): void
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Perfil',
            'email' => 'perfil2@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => true,
        ]);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/password', [
            'senha_atual' => 'SenhaErrada123',
            'nova_senha' => 'SenhaNova123',
            'nova_senha_confirmation' => 'SenhaNova123',
        ])->assertStatus(422);

        $usuario->refresh();
        $this->assertTrue(Hash::check('SenhaForte123', $usuario->senha));
        $this->assertTrue((bool) $usuario->forcar_troca_senha);
    }
}
