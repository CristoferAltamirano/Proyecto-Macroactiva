<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\CondoMacrosServiceProvider::class,
        // Illuminate\Broadcasting\BroadcastServiceProvider::class,
        // Illuminate\Bus\BusServiceProvider::class,
        // Illuminate\Cache\CacheServiceProvider::class,
        // Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        // Illuminate\Cookie\CookieServiceProvider::class,
        // Illuminate\Database\DatabaseServiceProvider::class,
        // Illuminate\Encryption\EncryptionServiceProvider::class,
        // Illuminate\Filesystem\FilesystemServiceProvider::class,
        // Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        // Illuminate\Hashing\HashServiceProvider::class,
        // Illuminate\Mail\MailServiceProvider::class,
        // Illuminate\Notifications\NotificationServiceProvider::class,
        // Illuminate\Pagination\PaginationServiceProvider::class,
        // Illuminate\Pipeline\PipelineServiceProvider::class,
        // Illuminate\Queue\QueueServiceProvider::class,
        // Illuminate\Redis\RedisServiceProvider::class,
        // Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        // Illuminate\Session\SessionServiceProvider::class,
        // Illuminate\Translation\TranslationServiceProvider::class,
        // Illuminate\Validation\ValidationServiceProvider::class,
        // Illuminate\View\ViewServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();