<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Support\InitialData\AccessInitialDataService;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AccessInitialDataService::class);

        if (!$service->shouldSeedUsuariosPadrao()) {
            $this->command?->warn('Usuarios padrao pulados fora de local/testing.');

            return;
        }

        $service->seedUsuariosPadrao();
    }
}
