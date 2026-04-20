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

function admin_locale(): string
{
    return normalize_locale((string) ($_SESSION['admin_locale'] ?? null));
}

function set_admin_locale(?string $locale): void
{
    $_SESSION['admin_locale'] = normalize_locale($locale);
}

function admin_i18n(): array
{
    return [
        'nav.dashboard' => ['ru' => 'Дашборд', 'en' => 'Dashboard'],
        'nav.modules' => ['ru' => 'Модули', 'en' => 'Modules'],
        'nav.site_settings' => ['ru' => 'Настройки сайта', 'en' => 'Site settings'],
        'nav.about_project' => ['ru' => 'О проекте', 'en' => 'About project'],
        'nav.our_position' => ['ru' => 'Наша позиция', 'en' => 'Our position'],
        'nav.publication_types' => ['ru' => 'Типы публикаций', 'en' => 'Publication types'],
        'nav.publications' => ['ru' => 'Публикации', 'en' => 'Publications'],
        'nav.authors' => ['ru' => 'Авторы', 'en' => 'Authors'],
        'nav.migrations' => ['ru' => 'Миграции', 'en' => 'Migrations'],
        'ui.logout' => ['ru' => 'Выйти', 'en' => 'Logout'],
        'ui.login' => ['ru' => 'Войти', 'en' => 'Login'],
        'ui.install' => ['ru' => 'Установить', 'en' => 'Install'],
        'ui.email' => ['ru' => 'Email', 'en' => 'Email'],
        'ui.password' => ['ru' => 'Пароль', 'en' => 'Password'],
        'login.title' => ['ru' => 'Вход в админку KANT', 'en' => 'KANT Admin Login'],
        'login.heading' => ['ru' => 'Вход в админку', 'en' => 'Admin Login'],
        'login.invalid_credentials' => ['ru' => 'Неверный email или пароль.', 'en' => 'Invalid credentials.'],
        'dashboard.title' => ['ru' => 'Панель управления KANT', 'en' => 'KANT Admin Dashboard'],
        'dashboard.heading' => ['ru' => 'Управление контентом', 'en' => 'Content dashboard'],
        'dashboard.description' => ['ru' => 'Используйте разделы ниже для редактирования контента, переводов, медиа и служебных задач.', 'en' => 'Use the sections below to update content, translations, media, and maintenance tasks.'],
        'dashboard.quick_reminders' => ['ru' => 'Быстрые напоминания', 'en' => 'Quick reminders'],
        'dashboard.reminder_locales' => ['ru' => 'Заполняйте RU и EN вместе, чтобы переключатель языков был полным.', 'en' => 'Use RU and EN fields together so the language switcher stays complete.'],
        'dashboard.reminder_migrations' => ['ru' => 'После pull с backend-изменениями один раз запускайте миграции в админке.', 'en' => 'After pulling backend updates, run migrations once in the admin panel.'],
        'dashboard.reminder_uploads' => ['ru' => 'По возможности используйте загрузку файлов, чтобы избежать нерабочих внешних ссылок.', 'en' => 'Use uploaded files whenever possible to avoid dead external links.'],
        'dashboard.modules_desc' => ['ru' => 'Редактирование страниц модулей, видео, транскрипций, материалов и hero-блока.', 'en' => 'Edit module pages, videos, transcripts, readings, and hero settings.'],
        'dashboard.publications_desc' => ['ru' => 'Управление карточками публикаций, файлами и hero-блоком страницы публикаций.', 'en' => 'Manage publication cards, files, and publication page hero content.'],
        'dashboard.authors_desc' => ['ru' => 'Ведение профилей авторов и загруженных фото.', 'en' => 'Maintain author profiles and uploaded author photos.'],
        'dashboard.about_desc' => ['ru' => 'Обновление текстов, видео и переводов блока «О проекте».', 'en' => 'Update intro block text, videos, and section translations.'],
        'dashboard.position_desc' => ['ru' => 'Редактирование концепции, принципов, целей и медиа блока на главной.', 'en' => 'Edit concept, principles, objectives, and media for the homepage block.'],
        'dashboard.types_desc' => ['ru' => 'Настройка категорий для вкладок публикаций.', 'en' => 'Configure category filters for publication tabs.'],
        'dashboard.settings_desc' => ['ru' => 'Глобальные настройки, используемые на страницах сайта.', 'en' => 'Change global settings used across pages.'],
        'dashboard.migrations_desc' => ['ru' => 'Применение миграций базы после backend-обновлений.', 'en' => 'Apply database migration scripts when pulling backend changes.'],
        'migrate.title' => ['ru' => 'Запуск миграций', 'en' => 'Run Migration'],
        'migrate.heading' => ['ru' => 'Миграции базы данных', 'en' => 'Database Migrations'],
        'migrate.description' => ['ru' => 'Применяет идемпотентные миграции: очистка legacy video и таблицы Our Position.', 'en' => 'Applies idempotent migrations: legacy video cleanup and Our Position tables.'],
        'migrate.applied' => ['ru' => 'Миграции применены.', 'en' => 'Migrations applied.'],
        'migrate.file_not_found' => ['ru' => 'Файл миграции не найден', 'en' => 'Migration file not found'],
        'migrate.run' => ['ru' => 'Запустить миграции', 'en' => 'Run migrations'],
        'migrate.confirm' => ['ru' => 'Применить миграции сейчас?', 'en' => 'Apply migration now?'],
    ];
}

function t(string $key, ?string $fallback = null): string
{
    $dict = admin_i18n();
    $locale = admin_locale();
    if (isset($dict[$key][$locale])) {
        return (string) $dict[$key][$locale];
    }
    if (isset($dict[$key]['en'])) {
        return (string) $dict[$key]['en'];
    }
    return $fallback ?? $key;
}

function tr(string $ru, string $en): string
{
    return admin_locale() === 'ru' ? $ru : $en;
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
