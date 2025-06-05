<?php

namespace Webbycrown\QueryBuilder\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;
use Webbycrown\QueryBuilder\Console\Commands\GenerateScheduledReports;

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

        // Publish the package's configuration file to the application's config directory.
        // This allows users to customize package settings without modifying the core package files.
        $this->publishes([
            __DIR__.'/../Config/querybuilder.php' => config_path('querybuilder.php'),
        ], 'querybuilder');

        // Include a helpers file containing custom utility functions.
        require_once __DIR__ . '/../Helpers/helpers.php';

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'querybuilder');

        $this->publishes([
            __DIR__.'/../Resources/lang' => resource_path('lang/vendor/querybuilder'),
        ], 'translations');

         // Register commands in the console.
        $this->registerCommands();

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
    }


    /**
     * Schedule the command to run.
     *
     * @return void
     */
    public function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
        // Schedule your custom command to run daily (or any other frequency you prefer)
        $this->commands([
            GenerateScheduledReports::class,
        ]);
    }
    

}
