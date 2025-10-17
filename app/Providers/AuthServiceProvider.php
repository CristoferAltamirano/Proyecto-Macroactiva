<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Model => Policy
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Pueden ver el libro: super_admin y admin
        Gate::define('ver-libro', function ($user) {
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole('super_admin') || $user->hasRole('admin');
            }
            return in_array(($user->role ?? $user->rol ?? null), ['super_admin', 'admin'], true);
        });
    }
}