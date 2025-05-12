<?php

namespace App\Console;

use App\Console\Commands\RefreshPermissoesCache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registra os comandos customizados da aplicação.
     */
    protected $commands = [
        RefreshPermissoesCache::class,
    ];

    /**
     * Define agendamentos automáticos.
     */
    protected function schedule(Schedule $schedule)
    {
        // Exemplo (se desejar rodar diariamente)
        // $schedule->command('permissao:rebuild-cache')->daily();
    }

    /**
     * Carrega comandos da pasta Console/Commands.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
