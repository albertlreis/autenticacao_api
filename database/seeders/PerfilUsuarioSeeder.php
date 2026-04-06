<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Support\InitialData\AccessInitialDataService;

class PerfilUsuarioSeeder extends Seeder
{
    public function run(): void
    {
        app(AccessInitialDataService::class)->seedPerfis();
    }
}
