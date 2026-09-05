<?php

return [
    'auth_username' => env('STUDIO_AUTH_USERNAME', 'studio'),
    'auth_password' => env('STUDIO_AUTH_PASSWORD', 'password'),
    'client_shell' => env('STUDIO_CLIENT_SHELL', public_path('studio/index.html')),
    'rest_url' => rtrim(env('SUPABASE_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/') . '/rest/v1',
    'db_host' => env('DB_HOST', 'localhost'),
    'db_port' => (int) env('DB_PORT', 5432),
    'projects' => [
        [
            'id' => 'default',
            'name' => 'Default Project',
            'status' => 'ready',
            'current' => true,
        ],
    ],
];
