<?php

return [
    'defaults' => [
        'guard' => 'web', // El guard por defecto sigue siendo 'web' (para admins)
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // --- NUEVO GUARDIA DE SEGURIDAD PARA RESIDENTES ---
        'residente' => [
            'driver' => 'session',
            'provider' => 'unidades',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        // --- NUEVO PROVEEDOR DE DATOS PARA RESIDENTES ---
        'unidades' => [
            'driver' => 'eloquent',
            'model' => App\Models\Unidad::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];