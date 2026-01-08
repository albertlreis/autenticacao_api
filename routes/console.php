<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agenda tarefas após o app bootar (compatível com várias versões)
app()->booted(function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    // Limpa tokens expirados do Sanctum diariamente
    $schedule->command('sanctum:prune-expired --hours=24')->daily();
});
