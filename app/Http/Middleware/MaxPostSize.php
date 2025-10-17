<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaxPostSize
{
    public function handle(Request $request, Closure $next)
    {
        $maxMb = (int) env('SEC_MAX_POST_MB', 16);
        if ($maxMb > 0 && in_array($request->method(), ['POST','PUT','PATCH'], true)) {
            $contentLength = (int) $request->header('Content-Length', 0);
            if ($contentLength > ($maxMb * 1024 * 1024)) {
                throw new HttpException(413, 'Payload Too Large');
            }
        }
        return $next($request);
    }
}
