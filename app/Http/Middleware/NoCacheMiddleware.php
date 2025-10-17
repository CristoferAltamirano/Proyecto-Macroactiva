<?php

namespace App\Http\Middleware;

use Closure;

class NoCacheMiddleware
{
    public function handle($request, Closure $next)
    {
        $resp = $next($request);
        return $resp->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma','no-cache')
                    ->header('Expires','Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
