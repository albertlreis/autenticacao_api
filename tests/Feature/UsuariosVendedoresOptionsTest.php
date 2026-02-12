<?php

namespace Tests\Feature;

use App\Enums\PerfilEnum;
use App\Models\AcessoPerfil;
use App\Models\AcessoPermissao;
use App\Models\AcessoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuariosVendedoresOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsComPermissao(string $slug): AcessoUsuario
    {
        $usuario = AcessoUsuario::create([
            'nome' => 'Usuario Permissao',
            'email' => 'perm@test.local',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $perfil = AcessoPerfil::create([
            'nome' => 'Gestor',
            'descricao' => 'Perfil de teste',
        ]);

        $permissao = AcessoPermissao::create([
            'slug' => $slug,
            'nome' => $slug,
            'descricao' => null,
        ]);

        $perfil->permissoes()->sync([$permissao->id]);
        $usuario->perfis()->sync([$perfil->id]);

        Sanctum::actingAs($usuario);

        return $usuario;
    }

    public function test_lista_apenas_vendedores_ativos(): void
    {
        $this->actingAsComPermissao('pedidos.selecionar_vendedor');

        $perfilVendedor = AcessoPerfil::create([
            'nome' => PerfilEnum::VENDEDOR->value,
            'descricao' => 'Perfil vendedor',
        ]);

        $perfilOutro = AcessoPerfil::create([
            'nome' => 'Outro',
            'descricao' => 'Perfil outro',
        ]);

        $vendedorAtivo = AcessoUsuario::create([
            'nome' => 'Vendedor Ativo',
            'email' => 'vendedor.ativo@test.local',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);
        $vendedorAtivo->perfis()->sync([$perfilVendedor->id]);

        $vendedorInativo = AcessoUsuario::create([
            'nome' => 'Vendedor Inativo',
            'email' => 'vendedor.inativo@test.local',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => false,
        ]);
        $vendedorInativo->perfis()->sync([$perfilVendedor->id]);

        $naoVendedor = AcessoUsuario::create([
            'nome' => 'Usuario Outro',
            'email' => 'outro@test.local',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);
        $naoVendedor->perfis()->sync([$perfilOutro->id]);

        $response = $this->getJson('/api/v1/usuarios/opcoes/vendedores');

        $response->assertOk();

        $payload = $response->json();
        $this->assertTrue(collect($payload)->contains('id', $vendedorAtivo->id));
        $this->assertFalse(collect($payload)->contains('id', $vendedorInativo->id));
        $this->assertFalse(collect($payload)->contains('id', $naoVendedor->id));
        $this->assertArrayHasKey('nome', $payload[0]);
    }
}
