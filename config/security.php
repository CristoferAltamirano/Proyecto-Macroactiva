<?php

return [
    'force_https' => env('SEC_FORCE_HTTPS', false),

    'hsts' => [
        'enabled' => env('SEC_HSTS', false),
        'max_age' => env('SEC_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('SEC_HSTS_SUBDOMAINS', false),
        'preload' => env('SEC_HSTS_PRELOAD', false),
    ],

    'max_post_mb' => env('SEC_MAX_POST_MB', 10),

    'csp' => [
        'enabled' => env('SEC_CSP', true),
        'allow_inline' => env('SEC_CSP_INLINE', true),

        // Excluir rutas (ej.: callbacks externos)
        'skip_on_prefix' => [
            'pagos/webpay/',
        ],

        // 👇 NUEVO: fuentes permitidas SOLO EN LOCAL para que el front se vea bien
        'dev_origins' => array_filter(array_map('trim', explode(',', env('SEC_CSP_DEV_ORIGINS',
            'http://127.0.0.1:5173,http://localhost:5173'
        )))),
        'dev_cdns' => array_filter(array_map('trim', explode(',', env('SEC_CSP_DEV_CDNS',
            'https://cdn.jsdelivr.net,https://unpkg.com,https://fonts.googleapis.com,https://fonts.gstatic.com'
        )))),
    ],

    'admin_ip_allowlist' => array_filter(array_map('trim', explode(',', env('SEC_ADMIN_IPS', '')))),

    'rate_limits' => [
        'login_per_min'           => env('SEC_RATE_LOGIN_PER_MIN', 8),
        'password_email_per_min'  => env('SEC_RATE_PW_EMAIL_PER_MIN', 5),
        'exports_per_min'         => env('SEC_RATE_EXPORTS_PER_MIN', 20),
    ],
];
