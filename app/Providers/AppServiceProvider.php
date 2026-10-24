<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lets views write Site::name() instead of the full class name.
        \Illuminate\Foundation\AliasLoader::getInstance()->alias('Site', \App\Support\Site::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
