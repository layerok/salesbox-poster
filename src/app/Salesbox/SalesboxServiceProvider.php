<?php

namespace App\Salesbox;

use App\Salesbox\Stores\SalesboxStore;
use Illuminate\Support\ServiceProvider;
use Layerok\SalesboxApi\SalesboxApi;
use Layerok\SalesboxApi\SalesboxApiV4;

class SalesboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('salesboxapi', function () {
            return new SalesboxApi(config('salesbox'));
        });
        $this->app->singleton('salesboxapi.v4', function () {
            return new SalesboxApiV4(config('salesbox'));
        });
        $this->app->singleton('salesbox.store', function ()  {
            return new SalesboxStore();
        });
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'salesbox');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
