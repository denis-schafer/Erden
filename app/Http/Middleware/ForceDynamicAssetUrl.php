<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceDynamicAssetUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $protocol = $request->secure() ? 'https' : 'http';
        $host = $request->getHost();
        $baseUrl = $protocol . '://' . $host;

        config(['app.url' => $baseUrl]);
        config(['app.asset_url' => $baseUrl]);

        return $next($request);
    }
}
