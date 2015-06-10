<?php

namespace Jacob1237\LaravelHybridAuth;

use Hybrid_Auth;
use Illuminate\Support\ServiceProvider;


class HybridAuthServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('jacob1237/laravelhybridauth');
        require_once(__DIR__ . '/routes.php');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerHybridAuth();
        $this->registerLaravelAuth();
    }

    private function registerHybridAuth()
    {
        $this->app['hybridauth'] = $this->app->share(function($app) {
            $config = $app['config']['laravelhybridauth::hybridauth'];
            $config['base_url'] = $app['url']->route('laravelhybridauth.routes.endpoint');

            return new Hybrid_Auth($config);
        });
    }

    private function registerLaravelAuth()
    {
        $this->app['laravelhybridauth'] = $this->app->share(function($app) {
            $config = array(
                'db' => $app['config']['laravelhybridauth::db'],
                'hybridauth' => $app['config']['laravelhybridauth::hybridauth'],
                'models' => $app['config']['laravelhybridauth::models'],
                'routes' => $app['config']['laravelhybridauth::routes'],
            );

            return new HybridAuth($config, $this->app['hybridauth']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('laravelhybridauth', 'hybridauth');
    }
}