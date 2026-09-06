<?php

return [
    'auth_username' => env('STUDIO_AUTH_USERNAME', env('SUPADATA_STUDIO_AUTH_USERNAME', 'studio')),
    'auth_password' => env('STUDIO_AUTH_PASSWORD', env('SUPADATA_STUDIO_AUTH_PASSWORD', 'password')),
    'client_shell' => env('STUDIO_CLIENT_SHELL', public_path('studio/index.html')),
    'registry_path' => env('SUPADATA_REGISTRY_PATH', env('SUPADATA_DATA_DIR', '/var/lib/supadata') . '/registry.json'),
    'rest_url' => rtrim(env('SUPABASE_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/') . '/rest/v1',
    'db_host' => env('DB_HOST', 'localhost'),
    'db_port' => (int) env('DB_PORT', 5432),
    'projects' => [
        [
            'id' => 1,
            'ref' => 'default',
            'name' => 'Default Project',
            'organization_id' => 1,
            'cloud_provider' => 'localhost',
            'status' => 'ACTIVE_HEALTHY',
            'region' => 'local',
            'inserted_at' => '2021-08-02T06:40:40.646Z',
            'current' => true,
        ],
    ],
];
