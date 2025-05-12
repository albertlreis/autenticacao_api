<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PerfilUsuarioSeeder::class,
            PermissoesSeeder::class,
            AssociacoesSeeder::class,
        ]);

        // Reconstroi o cache de permissões após as seeds
        Artisan::call('permissao:refresh-cache');
        $this->command->info('✔ Cache de permissões reconstruído automaticamente.');
    }
}
