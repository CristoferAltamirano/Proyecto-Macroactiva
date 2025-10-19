<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Model => Policy
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Grant all abilities to super-admin
        Gate::before(function (User $user, $ability) {
            if ($user->tipo_usuario === 'super-admin') {
                return true;
            }
        });

        // Define gate for residente guard
        Gate::define('residente', function(User $user) {
            return $user->tipo_usuario === 'residente';
        });
    }
}