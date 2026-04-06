<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Support\InitialData\AccessInitialDataService;

class AssociacoesSeeder extends Seeder
{
    public function run(): void
    {
        app(AccessInitialDataService::class)->seedAssociacoes();
    }
}
