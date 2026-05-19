<?php

namespace App\Providers;

use App\Console\Commands\ImportAlphaVantageCommand;
use App\Console\Commands\ImportDohodCommand;
use App\Console\Commands\ImportFinnhubCommand;
use App\Console\Commands\ImportSmartLabCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            ImportFinnhubCommand::class,
            ImportAlphaVantageCommand::class,
            ImportSmartLabCommand::class,
            ImportDohodCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
