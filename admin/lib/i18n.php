<?php
declare(strict_types=1);

function normalize_locale(?string $locale): string
{
    global $config;
    $default = $config['app']['default_locale'];
    $supported = $config['app']['supported_locales'];
    $value = strtolower(trim((string) $locale));
    return in_array($value, $supported, true) ? $value : $default;
}

function translated_row(PDO $pdo, string $table, string $fkField, int $id, string $locale, string $defaultLocale = 'en'): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$fkField} = :id AND locale = :locale LIMIT 1");
    $stmt->execute(['id' => $id, 'locale' => $locale]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $fallback = $pdo->prepare("SELECT * FROM {$table} WHERE {$fkField} = :id AND locale = :locale LIMIT 1");
    $fallback->execute(['id' => $id, 'locale' => $defaultLocale]);
    return $fallback->fetch() ?: null;
}
