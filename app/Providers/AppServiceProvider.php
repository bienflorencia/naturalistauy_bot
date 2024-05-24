<?php

namespace App\Providers;

use App\Services\Naturalist;
use App\Services\Tacuruses;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bindMethod('App\Console\Commands\CheckOnDate@handle', function ($command, $app) {
            $api = new Tacuruses($app->config->get('services.tacuruses.naturalista.host'), $app->config->get('services.tacuruses.naturalista.apikey'));
            return $command->handle(fediApi: $api, natuApi: $app->make(Naturalist::class));
        });

        $this->app->bindMethod('App\Console\Commands\TopIdentifiers@handle', function ($command, $app) {
            $api = new Tacuruses($app->config->get('services.tacuruses.naturalista.host'), $app->config->get('services.tacuruses.naturalista.apikey'));
            return $command->handle(fediApi: $api, natuApi: $app->make(Naturalist::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Pluralizer::useLanguage('spanish');
    }
}
