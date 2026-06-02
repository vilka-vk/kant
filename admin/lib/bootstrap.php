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

require_once __DIR__ . '/i18n.php';
if (isset($_GET['lang'])) {
    set_admin_locale((string) $_GET['lang']);
}
if (!isset($_SESSION['admin_locale'])) {
    set_admin_locale((string) ($config['app']['default_locale'] ?? 'ru'));
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

function wysiwyg_decode(string $value): string
{
    $decoded = $value;
    for ($i = 0; $i < 5; $i++) {
        $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }

    return $decoded;
}

function wysiwyg_normalize(string $value): string
{
    return wysiwyg_decode(trim($value));
}

function wysiwyg_textarea_value(string $value): string
{
    $decoded = wysiwyg_decode($value);

    return str_ireplace('</textarea>', '&lt;/textarea&gt;', $decoded);
}

function kant_wants_json_response(): bool
{
    return ($_SERVER['HTTP_X_KANT_REORDER'] ?? '') === '1';
}

function kant_reorder_response(string $redirectUrl): void
{
    if (kant_wants_json_response()) {
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":true}';
        exit;
    }
    redirect($redirectUrl);
}
