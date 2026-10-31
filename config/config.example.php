<?php

declare(strict_types=1);

return [
    'installed' => false,
    'environment' => 'production',
    'base_path' => '',
    'app_key' => 'generated-by-installer',
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'wayfairs',
        'username' => 'wayfairs_app',
        'password' => 'set-during-install',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'cookie' => 'wayfairs_session',
        'lifetime_minutes' => 720,
        'secure' => true,
    ],
];
