<?php
declare(strict_types=1);

/**
 * Copy to config.local.php and fill real values.
 * config.local.php must stay out of git.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'kant_db',
        'user' => 'kant_user',
        'pass' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'default_locale' => 'ru',
        'supported_locales' => ['ru', 'en'],
        'session_name' => 'kant_admin',
        'install_secret' => 'change-install-secret',
    ],
];
