<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\AcessoUsuario;
use App\Models\AcessoPerfil;
use App\Observers\AcessoUsuarioObserver;
use App\Observers\AcessoPerfilObserver;

class ObserverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AcessoUsuario::observe(AcessoUsuarioObserver::class);
        AcessoPerfil::observe(AcessoPerfilObserver::class);
    }
}
