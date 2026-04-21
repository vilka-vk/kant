<?php
declare(strict_types=1);

require __DIR__ . '/../admin/lib/bootstrap.php';
require __DIR__ . '/../admin/lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$defaultLocale = $config['app']['default_locale'];
$locale = normalize_locale($_GET['lang'] ?? $defaultLocale);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$prefix = '/api/';
$route = str_starts_with($uriPath, $prefix) ? substr($uriPath, strlen($prefix)) : '';
$route = trim($route, '/');

function out(array $data, string $lang, bool $fallbackUsed = false): void
{
    echo json_encode([
        'data' => $data,
        'meta' => [
            'lang' => $lang,
            'fallback_used' => $fallbackUsed,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalize_public_asset_path(string $path): string
{
    $value = trim(str_replace('\\', '/', $path));
    if ($value === '') {
        return '';
    }
    if (preg_match('#^([a-z]+:)?//#i', $value) || str_starts_with($value, 'data:')) {
        return $value;
    }
    $uploadsPos = stripos($value, '/uploads/');
    if ($uploadsPos !== false) {
        $value = substr($value, $uploadsPos);
    } elseif (!str_starts_with($value, '/')) {
        $value = '/' . $value;
    }
    return $value;
}

if ($route === 'site-settings') {
    $base = $pdo->query('SELECT * FROM site_settings WHERE id = 1')->fetch() ?: [];
    $tr = translated_row($pdo, 'site_settings_translations', 'site_settings_id', 1, $locale, $defaultLocale) ?: [];
    out(array_merge($base, $tr), $locale);
}

if ($route === 'about-project') {
    $base = $pdo->query('SELECT * FROM about_project WHERE id = 1')->fetch() ?: [];
    $tr = translated_row($pdo, 'about_project_translations', 'about_project_id', 1, $locale, $defaultLocale) ?: [];
    $videosStmt = $pdo->query('SELECT language_code, video_url, video_alt, sort_order FROM about_project_videos WHERE about_project_id = 1 ORDER BY sort_order ASC, id ASC');
    $videos = $videosStmt->fetchAll();
    $payload = array_merge($base, $tr);
    $payload['videos'] = $videos;
    out($payload, $locale);
}

if ($route === 'our-position') {
    $base = $pdo->query('SELECT * FROM our_position WHERE id = 1')->fetch() ?: [];
    $tr = translated_row($pdo, 'our_position_translations', 'our_position_id', 1, $locale, $defaultLocale) ?: [];
    $payload = array_merge($base, $tr);
    $payload['objectives'] = array_values(array_filter([
        trim((string) ($payload['objective_1'] ?? '')),
        trim((string) ($payload['objective_2'] ?? '')),
        trim((string) ($payload['objective_3'] ?? '')),
        trim((string) ($payload['objective_4'] ?? '')),
        trim((string) ($payload['objective_5'] ?? '')),
        trim((string) ($payload['objective_6'] ?? '')),
    ], static fn ($value): bool => $value !== ''));
    out($payload, $locale);
}

if ($route === 'modules') {
    $rows = $pdo->query('SELECT * FROM modules ORDER BY sort_order ASC, id ASC')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'modules_translations', 'module_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $result[] = array_merge($row, $tr);
    }
    out($result, $locale);
}

if (preg_match('#^modules/([^/]+)$#', $route, $m)) {
    $slug = $m[1];
    $stmt = $pdo->prepare('SELECT * FROM modules WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        out([], $locale);
    }
    $tr = translated_row($pdo, 'modules_translations', 'module_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
    $allStmt = $pdo->prepare('SELECT locale, lecture_title, lecture_video_title_primary, title FROM modules_translations WHERE module_id = :module_id');
    $allStmt->execute(['module_id' => (int) $row['id']]);
    $translations = [];
    foreach ($allStmt->fetchAll() as $trRow) {
        $translations[strtolower((string) $trRow['locale'])] = $trRow;
    }
    $videosStmt = $pdo->prepare('SELECT language_code, video_url, video_alt, sort_order FROM module_lecture_videos WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $videosStmt->execute(['module_id' => (int) $row['id']]);
    $lectureVideos = $videosStmt->fetchAll();
    $videosStmt = $pdo->prepare('SELECT language_code, video_url, video_alt, sort_order FROM module_presentation_videos WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $videosStmt->execute(['module_id' => (int) $row['id']]);
    $presentationVideos = $videosStmt->fetchAll();
    $payload = array_merge($row, $tr);
    $payload['translations'] = $translations;
    $payload['lecture_videos'] = $lectureVideos;
    $payload['presentation_videos'] = $presentationVideos;
    $transcriptsStmt = $pdo->prepare('SELECT * FROM module_transcripts WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $transcriptsStmt->execute(['module_id' => (int) $row['id']]);
    $transcriptRows = $transcriptsStmt->fetchAll();
    $payload['transcripts'] = [];
    foreach ($transcriptRows as $transcriptRow) {
        $transcriptTr = translated_row($pdo, 'module_transcripts_translations', 'module_transcript_id', (int) $transcriptRow['id'], $locale, $defaultLocale) ?: [];
        $payload['transcripts'][] = array_merge($transcriptRow, $transcriptTr);
    }
    out($payload, $locale);
}

if (preg_match('#^modules/(\d+)/transcripts$#', $route, $m)) {
    $moduleId = (int) $m[1];
    $stmt = $pdo->prepare('SELECT * FROM module_transcripts WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['module_id' => $moduleId]);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'module_transcripts_translations', 'module_transcript_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $result[] = array_merge($row, $tr);
    }
    out($result, $locale);
}

if (preg_match('#^modules/(\d+)/readings$#', $route, $m)) {
    $moduleId = (int) $m[1];
    $stmt = $pdo->prepare('SELECT * FROM module_readings WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['module_id' => $moduleId]);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'module_readings_translations', 'module_reading_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $item = array_merge($row, $tr);
        $item['display_title'] = trim((string) ($item['custom_title'] ?? ''));
        if ($item['display_title'] === '') {
            $titleStmt = $pdo->prepare('SELECT custom_title FROM module_readings_translations
              WHERE module_reading_id = :id AND TRIM(COALESCE(custom_title, \'\')) <> \'\'
              ORDER BY locale ASC LIMIT 1');
            $titleStmt->execute(['id' => (int) $row['id']]);
            $anyCustomTitle = $titleStmt->fetchColumn();
            if ($anyCustomTitle !== false) {
                $item['display_title'] = trim((string) $anyCustomTitle);
            }
        }
        if ((int) ($row['linked_publication_id'] ?? 0) > 0) {
            $pubStmt = $pdo->prepare('SELECT * FROM publications WHERE id = :id LIMIT 1');
            $pubStmt->execute(['id' => (int) $row['linked_publication_id']]);
            $pubBase = $pubStmt->fetch() ?: null;
            if ($pubBase) {
                $pubTr = translated_row($pdo, 'publications_translations', 'publication_id', (int) $pubBase['id'], $locale, $defaultLocale) ?: [];
                $linkedPublication = array_merge($pubBase, $pubTr);
                if (trim((string) ($linkedPublication['title'] ?? '')) === '') {
                    $pubTitleStmt = $pdo->prepare('SELECT title FROM publications_translations
                      WHERE publication_id = :id AND TRIM(COALESCE(title, \'\')) <> \'\'
                      ORDER BY locale ASC LIMIT 1');
                    $pubTitleStmt->execute(['id' => (int) $pubBase['id']]);
                    $anyPubTitle = $pubTitleStmt->fetchColumn();
                    if ($anyPubTitle !== false) {
                        $linkedPublication['title'] = (string) $anyPubTitle;
                    }
                }
                $item['linked_publication'] = $linkedPublication;
            }
        }
        $result[] = $item;
    }
    out($result, $locale);
}

if ($route === 'publication-types') {
    $rows = $pdo->query('SELECT * FROM publication_types ORDER BY sort_order ASC, id ASC')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'publication_types_translations', 'publication_type_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $result[] = array_merge($row, $tr);
    }
    out($result, $locale);
}

if ($route === 'publications') {
    $sql = 'SELECT p.*, pt.slug AS publication_type_slug
            FROM publications p
            LEFT JOIN publication_types pt ON pt.id = p.publication_type_id
            ORDER BY p.display_order ASC, p.published_at DESC, p.id ASC';
    $rows = $pdo->query($sql)->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'publications_translations', 'publication_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $typeTr = translated_row($pdo, 'publication_types_translations', 'publication_type_id', (int) ($row['publication_type_id'] ?? 0), $locale, $defaultLocale) ?: [];
        $merged = array_merge($row, $tr);
        if (trim((string) ($merged['title'] ?? '')) === '') {
            $anyTitleStmt = $pdo->prepare('SELECT title FROM publications_translations WHERE publication_id = :id AND TRIM(COALESCE(title, \'\')) <> \'\' ORDER BY locale ASC LIMIT 1');
            $anyTitleStmt->execute(['id' => (int) $row['id']]);
            $anyTitle = $anyTitleStmt->fetchColumn();
            if ($anyTitle !== false) {
                $merged['title'] = (string) $anyTitle;
            }
        }
        $merged['publication_type_name'] = (string) ($typeTr['name'] ?? '');
        $result[] = $merged;
    }
    out($result, $locale);
}

if ($route === 'authors') {
    $rows = $pdo->query('SELECT * FROM authors ORDER BY display_order ASC, id ASC')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tr = translated_row($pdo, 'authors_translations', 'author_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $result[] = array_merge($row, $tr);
    }
    out($result, $locale);
}

if ($route === 'hero-sections') {
    $pageKey = trim((string) ($_GET['page_key'] ?? ''));
    if ($pageKey === '') {
        out([], $locale);
    }
    $stmt = $pdo->prepare('SELECT * FROM hero_sections WHERE page_key = :page_key LIMIT 1');
    $stmt->execute(['page_key' => $pageKey]);
    $row = $stmt->fetch();
    if (!$row) {
        out([], $locale);
    }
    $tr = translated_row($pdo, 'hero_sections_translations', 'hero_section_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
    $payload = array_merge($row, $tr);
    $payload['background_image_path'] = normalize_public_asset_path((string) ($payload['background_image_path'] ?? ''));
    out($payload, $locale);
}

http_response_code(404);
out(['message' => 'Not Found'], $locale);
