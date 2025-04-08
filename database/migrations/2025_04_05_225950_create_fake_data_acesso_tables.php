<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CreateFakeDataAcessoTables extends Migration
{
    /**
     * Executa as migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();

        // Inserir Perfis
        DB::table('acesso_perfis')->insert([
            [
                'nome' => 'Administrador',
                'descricao' => 'Acesso total ao sistema',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Usuário',
                'descricao' => 'Acesso limitado ao sistema',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Gerente',
                'descricao' => 'Acesso intermediário ao sistema',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ]);

        // Inserir Permissões
        DB::table('acesso_permissoes')->insert([
            [
                'nome' => 'Criar',
                'descricao' => 'Permite criar registros',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Editar',
                'descricao' => 'Permite editar registros',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Excluir',
                'descricao' => 'Permite excluir registros',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Visualizar',
                'descricao' => 'Permite visualizar registros',
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);

        // Inserir Usuários
        DB::table('acesso_usuarios')->insert([
            [
                'nome' => 'Admin Teste',
                'email' => 'admin@teste.com',
                'senha' => Hash::make('senha123'),
                'ativo' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'nome' => 'Usuario Teste',
                'email' => 'usuario@teste.com',
                'senha' => Hash::make('senha123'),
                'ativo' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);

        // Recuperar IDs para relacionamentos
        $adminPerfil = DB::table('acesso_perfis')->where('nome', 'Administrador')->first();
        $usuarioPerfil = DB::table('acesso_perfis')->where('nome', 'Usuário')->first();

        $adminUser = DB::table('acesso_usuarios')->where('email', 'admin@teste.com')->first();
        $usuarioUser = DB::table('acesso_usuarios')->where('email', 'usuario@teste.com')->first();

        // Inserir associação entre usuários e perfis
        DB::table('acesso_usuario_perfil')->insert([
            [
                'id_usuario' => $adminUser->id,
                'id_perfil' => $adminPerfil->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id_usuario' => $usuarioUser->id,
                'id_perfil' => $usuarioPerfil->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);

        // Recuperar permissões
        $permCriar      = DB::table('acesso_permissoes')->where('nome', 'Criar')->first();
        $permEditar     = DB::table('acesso_permissoes')->where('nome', 'Editar')->first();
        $permExcluir    = DB::table('acesso_permissoes')->where('nome', 'Excluir')->first();
        $permVisualizar = DB::table('acesso_permissoes')->where('nome', 'Visualizar')->first();

        // Associação de permissões ao perfil Administrador (todas as permissões)
        DB::table('acesso_perfil_permissao')->insert([
            [
                'id_perfil' => $adminPerfil->id,
                'id_permissao' => $permCriar->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id_perfil' => $adminPerfil->id,
                'id_permissao' => $permEditar->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id_perfil' => $adminPerfil->id,
                'id_permissao' => $permExcluir->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'id_perfil' => $adminPerfil->id,
                'id_permissao' => $permVisualizar->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);

        // Associação de permissões ao perfil Usuário (somente Visualizar)
        DB::table('acesso_perfil_permissao')->insert([
            [
                'id_perfil' => $usuarioPerfil->id,
                'id_permissao' => $permVisualizar->id,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);
    }

    /**
     * Reverte as inserções.
     *
     * @return void
     */
    public function down()
    {
        // A ordem é importante para evitar violações de constraint.
        DB::table('acesso_perfil_permissao')->truncate();
        DB::table('acesso_usuario_perfil')->truncate();
        DB::table('acesso_permissoes')->truncate();
        DB::table('acesso_perfis')->truncate();
        DB::table('acesso_usuarios')->truncate();
    }
}
