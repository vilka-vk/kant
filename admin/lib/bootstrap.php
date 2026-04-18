<?php
declare(strict_types=1);

const KANT_ROOT = __DIR__ . '/../../';

$baseConfig = require __DIR__ . '/config.php';
$localConfigPath = __DIR__ . '/config.local.php';
$config = $baseConfig;
if (file_exists($localConfigPath)) {
    $local = require $localConfigPath;
    $config = array_replace_recursive($baseConfig, $local);
}

date_default_timezone_set('UTC');
ini_set('display_errors', '0');

session_name($config['app']['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
