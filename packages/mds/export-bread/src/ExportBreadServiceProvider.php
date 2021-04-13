<?php

namespace Manuel90\ExportBread;

use Illuminate\Support\ServiceProvider;


class ExportBreadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'exportbread');
        $this->loadTranslationsFrom(__DIR__.'/../publishable/lang', 'exportbread');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        
    }
}
