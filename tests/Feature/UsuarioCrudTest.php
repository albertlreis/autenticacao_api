<?php

namespace Tests\Feature;

use App\Models\AcessoPermissao;
use App\Models\AcessoPerfil;
use App\Models\AcessoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUsuarioComPermissoes(array $slugs): AcessoUsuario
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Admin Teste',
            'email' => 'admin@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $perfil = AcessoPerfil::create([
            'nome' => 'Administrador',
            'descricao' => 'Perfil de teste',
        ]);

        $permissoes = collect($slugs)->map(function (string $slug) {
            return AcessoPermissao::create([
                'slug' => $slug,
                'nome' => $slug,
                'descricao' => null,
            ]);
        });

        $perfil->permissoes()->sync($permissoes->pluck('id')->all());
        $usuario->perfis()->sync([$perfil->id]);

        Sanctum::actingAs($usuario);

        return $usuario;
    }

    public function test_lista_usuarios(): void
    {
        $this->actingAsUsuarioComPermissoes(['usuarios.visualizar']);

        AcessoUsuario::create([
            'nome' => 'Usuario Listagem',
            'email' => 'listagem@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/v1/usuarios');

        $response->assertOk();

        $payload = $response->json('data');
        $payload = is_array($payload) ? $payload : $response->json();

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertArrayHasKey('id', $payload[0]);
        $this->assertArrayHasKey('nome', $payload[0]);
        $this->assertArrayHasKey('email', $payload[0]);
    }

    public function test_cria_usuario(): void
    {
        $this->actingAsUsuarioComPermissoes(['usuarios.criar']);

        $payload = [
            'nome' => 'Novo Usuario',
            'email' => 'novo@example.test',
            'senha' => 'SenhaForte123',
            'ativo' => true,
        ];

        $response = $this->postJson('/api/v1/usuarios', $payload);

        $response->assertStatus(201);

        $created = $response->json('data');
        $created = is_array($created) ? $created : $response->json();
        $this->assertSame($payload['email'], $created['email'] ?? null);

        $usuario = AcessoUsuario::where('email', $payload['email'])->first();
        $this->assertNotNull($usuario);
        $this->assertTrue(Hash::check($payload['senha'], $usuario->senha));
    }

    public function test_atualiza_usuario(): void
    {
        $this->actingAsUsuarioComPermissoes(['usuarios.editar']);

        $alvo = AcessoUsuario::create([
            'nome' => 'Usuario Antigo',
            'email' => 'antigo@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $payload = [
            'nome' => 'Usuario Atualizado',
            'email' => 'atualizado@example.test',
            'senha' => 'SenhaNova123',
        ];

        $response = $this->putJson("/api/v1/usuarios/{$alvo->id}", $payload);

        $response->assertOk();

        $updated = $response->json('data');
        $updated = is_array($updated) ? $updated : $response->json();
        $this->assertSame($payload['email'], $updated['email'] ?? null);

        $alvo->refresh();
        $this->assertSame($payload['nome'], $alvo->nome);
        $this->assertTrue(Hash::check($payload['senha'], $alvo->senha));
    }
}
