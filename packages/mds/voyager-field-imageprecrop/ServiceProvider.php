<?php

namespace MDS\Fields\ImagePreCrop;

use TCG\Voyager\Facades\Voyager;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'cropimage');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'cropimage');
        $this->publishes([__DIR__."/assets" => public_path('mds/cropimage')], 'public');

        Voyager::addFormField(FormField::class);
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
