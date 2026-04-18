<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_db_name',
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'default_locale' => 'ru',
        'supported_locales' => ['ru', 'en'],
        'session_name' => 'kant_admin',
        'install_secret' => 'replace-with-long-random-value',
    ],
];
