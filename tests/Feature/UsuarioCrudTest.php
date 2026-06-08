<?php

namespace Tests\Feature;

use App\Models\AcessoPermissao;
use App\Models\AcessoPerfil;
use App\Models\AcessoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUsuarioComPermissoes(array $slugs): AcessoUsuario
    {
        $email = 'admin+' . str_replace('.', '', uniqid('', true)) . '@example.test';

        $usuario = AcessoUsuario::create([
            'nome' => 'Admin Teste',
            'email' => $email,
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $perfil = AcessoPerfil::create([
            'nome' => 'Administrador ' . $usuario->id,
            'descricao' => 'Perfil de teste',
        ]);

        $permissoes = collect($slugs)->map(function (string $slug) {
            return AcessoPermissao::firstOrCreate(
                ['slug' => $slug],
                ['nome' => $slug, 'descricao' => null]
            );
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
        $this->assertTrue((bool) $usuario->forcar_troca_senha);

        $this->assertDatabaseHas('auditoria_logs', [
            'modulo' => 'acessos',
            'acao' => 'usuario.created',
            'entity_type' => AcessoUsuario::class,
            'entity_id' => (string) $usuario->id,
            'source_system' => 'auth',
        ]);

        $logId = DB::table('auditoria_logs')
            ->where('acao', 'usuario.created')
            ->where('entity_id', (string) $usuario->id)
            ->value('id');

        $this->assertDatabaseHas('auditoria_log_mudancas', [
            'auditoria_log_id' => $logId,
            'campo' => 'email',
            'new_value' => $payload['email'],
        ]);
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
        $this->assertFalse((bool) $alvo->forcar_troca_senha);

        $logId = DB::table('auditoria_logs')
            ->where('acao', 'usuario.updated')
            ->where('entity_id', (string) $alvo->id)
            ->latest('id')
            ->value('id');

        $this->assertNotNull($logId);
        $this->assertDatabaseHas('auditoria_log_mudancas', [
            'auditoria_log_id' => $logId,
            'campo' => 'senha',
            'old_value' => '[REDACTED]',
            'new_value' => '[REDACTED]',
        ]);

        $mudancasSenha = DB::table('auditoria_log_mudancas')
            ->where('auditoria_log_id', $logId)
            ->where('campo', 'senha')
            ->first();
        $this->assertStringNotContainsString($alvo->senha, (string) $mudancasSenha->new_value);
    }

    public function test_admin_marca_usuario_para_troca_obrigatoria_de_senha(): void
    {
        $this->actingAsUsuarioComPermissoes(['usuarios.editar']);

        $alvo = AcessoUsuario::create([
            'nome' => 'Usuario Alvo',
            'email' => 'alvo@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
            'forcar_troca_senha' => false,
        ]);

        $response = $this->putJson("/api/v1/usuarios/{$alvo->id}", [
            'forcar_troca_senha' => true,
        ]);

        $response->assertOk();

        $this->assertTrue((bool) $alvo->refresh()->forcar_troca_senha);
    }

    public function test_audita_perfis_e_permissoes(): void
    {
        $admin = $this->actingAsUsuarioComPermissoes(['usuarios.atribuir_perfil', 'usuarios.remover_perfil']);

        $permissao = AcessoPermissao::create([
            'slug' => 'relatorios.visualizar',
            'nome' => 'Relatorios visualizar',
        ]);

        $perfilResponse = $this->postJson('/api/v1/perfis', [
            'nome' => 'Auditor',
            'descricao' => 'Perfil auditado',
            'permissoes' => [$permissao->id],
        ]);
        $perfilResponse->assertCreated();
        $perfilId = $perfilResponse->json('id');

        $this->assertDatabaseHas('auditoria_logs', [
            'modulo' => 'acessos',
            'acao' => 'perfil.created',
            'entity_type' => AcessoPerfil::class,
            'entity_id' => (string) $perfilId,
        ]);

        $alvo = AcessoUsuario::create([
            'nome' => 'Usuario Perfil',
            'email' => 'usuario.perfil@example.test',
            'senha' => Hash::make('SenhaForte123'),
            'ativo' => true,
        ]);

        $this->postJson("/api/v1/usuarios/{$alvo->id}/perfis", [
            'perfis' => [$perfilId],
        ])->assertOk();

        $logId = DB::table('auditoria_logs')
            ->where('acao', 'perfil.attached')
            ->where('entity_id', (string) $alvo->id)
            ->latest('id')
            ->value('id');

        $this->assertDatabaseHas('auditoria_log_mudancas', [
            'auditoria_log_id' => $logId,
            'campo' => 'perfis',
        ]);

        $this->putJson("/api/v1/perfis/{$perfilId}", [
            'nome' => 'Auditor Atualizado',
            'permissoes' => [],
        ])->assertOk();

        $syncLogId = DB::table('auditoria_logs')
            ->where('acao', 'permissoes.synced')
            ->where('entity_id', (string) $perfilId)
            ->latest('id')
            ->value('id');

        $this->assertDatabaseHas('auditoria_log_mudancas', [
            'auditoria_log_id' => $syncLogId,
            'campo' => 'permissoes',
        ]);

        $this->postJson('/api/v1/permissoes', [
            'slug' => 'auditoria.teste',
            'nome' => 'Auditoria Teste',
        ])->assertCreated();

        $this->assertDatabaseHas('auditoria_logs', [
            'modulo' => 'acessos',
            'acao' => 'permissao.created',
        ]);
    }
}
