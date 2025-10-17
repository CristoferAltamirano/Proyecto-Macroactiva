<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Carga config/security.php
        $this->mergeConfigFrom(config_path('security.php'), 'security');
    }

    public function boot(): void
    {
        // Forzar https (URLs generadas) si está activo y en prod
        if (app()->environment('production') && config('security.force_https')) {
            URL::forceScheme('https');
        }

        // Rate limiters nombrados (útiles en rutas: throttle:login, etc.)
        RateLimiter::for('login', function (Request $request) {
            $max = (int) config('security.rate_limits.login_per_min', 8);
            // liga por IP + email para evitar bypass
            $key = strtolower($request->ip().'|'.$request->input('email', ''));
            return [Limit::perMinute($max)->by($key)];
        });

        RateLimiter::for('password-email', function (Request $request) {
            $max = (int) config('security.rate_limits.password_email_per_min', 5);
            return [Limit::perMinute($max)->by($request->ip())];
        });

        RateLimiter::for('exports', function (Request $request) {
            $max = (int) config('security.rate_limits.exports_per_min', 20);
            return [Limit::perMinute($max)->by($request->user()?->id ?? $request->ip())];
        });
    }
}
