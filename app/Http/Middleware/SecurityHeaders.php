<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Skips configurables (ej.: callbacks de Webpay)
        $skipPrefixes = (array) config('security.csp.skip_on_prefix', ['pagos/webpay/']);
        $skipCsp = false;
        foreach ($skipPrefixes as $pref) {
            if ($pref && str_starts_with($request->path(), $pref)) {
                $skipCsp = true; break;
            }
        }

        // ====== CSP ======
        if (config('security.csp.enabled', true) && !$skipCsp) {
            $allowInline = config('security.csp.allow_inline', true);

            // En local permitimos Vite/CDNs para no romper el front
            $isLocal = app()->environment('local');
            $devOrigins = $isLocal
                ? (array) config('security.csp.dev_origins', ['http://127.0.0.1:5173', 'http://localhost:5173'])
                : [];
            $devCdns = $isLocal
                ? (array) config('security.csp.dev_cdns', [
                    'https://cdn.jsdelivr.net', 'https://unpkg.com',
                    'https://fonts.googleapis.com', 'https://fonts.gstatic.com'
                ])
                : [];

            $styleSrc   = array_unique(array_filter(array_merge(
                ["'self'", $allowInline ? "'unsafe-inline'" : null],
                $devOrigins, $devCdns
            )));
            $scriptSrc  = array_unique(array_filter(array_merge(
                ["'self'", $allowInline ? "'unsafe-inline'" : null],
                $devOrigins, $devCdns
            )));
            $fontSrc    = array_unique(array_filter(array_merge(
                ["'self'", 'data:'],
                in_array('https://fonts.gstatic.com', $devCdns, true) ? ['https://fonts.gstatic.com'] : []
            )));
            $connectSrc = array_unique(array_filter(array_merge(
                ["'self'"], $devOrigins
            )));
            $imgSrc     = array_unique(array_filter(array_merge(
                ["'self'", 'data:', 'blob:'], $devOrigins
            )));

            $csp = "default-src 'self'; "
                 . 'img-src ' . implode(' ', $imgSrc) . '; '
                 . 'style-src ' . implode(' ', $styleSrc) . '; '
                 . 'script-src ' . implode(' ', $scriptSrc) . '; '
                 . 'font-src ' . implode(' ', $fontSrc) . '; '
                 . 'connect-src ' . implode(' ', $connectSrc) . '; '
                 . "frame-ancestors 'self';";

            $response->headers->set('Content-Security-Policy', trim($csp));
        }

        // ====== Otros headers seguros ======
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=()");
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // HSTS si está activado y la request ya viene por HTTPS
        if ($request->isSecure() && config('security.hsts.enabled', false)) {
            $max = (int) config('security.hsts.max_age', 31536000);
            $sub = config('security.hsts.include_subdomains', false) ? '; includeSubDomains' : '';
            $pre = config('security.hsts.preload', false) ? '; preload' : '';
            $response->headers->set('Strict-Transport-Security', "max-age={$max}{$sub}{$pre}");
        }

        return $response;
    }
}
