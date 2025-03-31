<?php

namespace Webbycrown\QueryBuilder\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;


/**
 * Service provider for the QueryBuilder package.
 * Handles the registration and bootstrapping of routes, views, and helper files.
 */
class QueryBuilderServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * This method is called after all other service providers have been registered,
     * allowing for initialization such as loading routes, views, and helper functions.
     *
     * @param Router $router The router instance.
     * @return void
     */
    public function boot(Router $router)
    {
        // Load the package routes from the defined web.php file.
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        // Load views from the package's Resources/views directory and assign a namespace.
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'wc_querybuilder');

        $this->publishes([
            __DIR__.'/../Config/querybuilder.php' => config_path('querybuilder.php'),
        ], 'querybuilder');

        // Include a helpers file containing custom utility functions.
        require_once __DIR__ . '/../Helpers/helpers.php';

    }
    
    /**
     * Register any application services.
     *
     * This method is used to bind services into the service container.
     * Currently, no additional bindings are defined.
     *
     * @return void
     */
    public function register()
    {
        // This function is left empty, but can be used to register bindings.
        // $this->mergeConfigFrom(
        //     dirname(__DIR__) . '/Config/querybuilder.php', 'querybuilder'
        // );
    }
    

}
