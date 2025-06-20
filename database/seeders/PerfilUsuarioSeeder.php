<?php

namespace Database\Seeders;

use App\Enums\PerfilEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerfilUsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('acesso_perfis')->insertOrIgnore([
            ['nome' => PerfilEnum::ADMINISTRADOR->value, 'descricao' => 'Acesso total ao sistema','created_at' => $now, 'updated_at' => $now],
            ['nome' => PerfilEnum::VENDEDOR->value, 'descricao' => 'Acesso comercial restrito','created_at' => $now, 'updated_at' => $now],
            ['nome' => PerfilEnum::DESENVOLVEDOR->value, 'descricao' => 'Acesso técnico irrestrito','created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
