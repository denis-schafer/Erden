<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class DynamicAssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(Request $request): void
    {
        $protocol = $request->secure() ? 'https' : 'http';
        $host = $request->getHost();
        $baseUrl = $protocol . '://' . $host;
        
        // Configurar URL base de la aplicación
        config(['app.url' => $baseUrl]);
        
        // Configurar assets para usar la URL dinámica
        config(['app.asset_url' => $baseUrl]);
    }
}
