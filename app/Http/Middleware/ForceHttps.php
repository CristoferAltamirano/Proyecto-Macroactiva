<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        $force = filter_var(env('SEC_FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);

        if ($force && !$request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        // ayuda cuando hay proxy/reverse-proxy
        if ($force) {
            $request->server->set('HTTPS', true);
        }

        return $next($request);
    }
}
