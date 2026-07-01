<?php

namespace App\Providers;

use App\Support\Logging\SierraLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        config([
            'database.connections.mysql.charset' => 'utf8mb4',
            'database.connections.mysql.collation' => 'utf8mb4_0900_ai_ci',
        ]);

        try {
            DB::connection()->getPdo();
            DB::statement("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_0900_ai_ci'");
        } catch (QueryException|\PDOException $e) {
            if (!$this->app->environment('testing')) {
                SierraLog::system('system.mysql.charset_failed', [
                    'exception' => $e,
                ]);
            }
        }
    }
}
