<?php

namespace Database\Seeders;

use App\Support\InitialData\AccessInitialDataService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seeders = [
            PerfilUsuarioSeeder::class,
            PermissoesSeeder::class,
            AssociacoesSeeder::class,
        ];

        if (app(AccessInitialDataService::class)->shouldSeedUsuariosPadrao()) {
            array_splice($seeders, 1, 0, [UsuarioSeeder::class]);
        } else {
            $this->command?->warn('Usuarios padrao pulados fora de local/testing.');
        }

        $this->call($seeders);

        // Reconstroi o cache de permissões após as seeds
        Artisan::call('permissao:refresh-cache');
        $this->command->info('✔ Cache de permissões reconstruído automaticamente.');
    }
}
