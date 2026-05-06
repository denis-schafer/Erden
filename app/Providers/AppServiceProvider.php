<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $request = $this->app->make('request');
        $protocol = $request->secure() ? 'https' : 'http';
        $host = $request->getHost() ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        $this->app['config']->set('app.url', $baseUrl);
        $this->app['config']->set('app.asset_url', $baseUrl);
    }
}
