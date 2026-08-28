<?php
declare(strict_types=1);

require __DIR__ . '/../admin/lib/bootstrap.php';
require __DIR__ . '/../admin/lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = db();
$defaultLocale = $config['app']['default_locale'];
$locale = normalize_locale($_GET['lang'] ?? $defaultLocale);
$moduleTranslationsHasFormats = (bool) $pdo->query("SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules_translations'
    AND COLUMN_NAME = 'formats'")->fetchColumn();
$moduleComponentsEnabled = (bool) $pdo->query("SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'module_components'")->fetchColumn();

$routeFromQuery = trim((string) ($_GET['route'] ?? ''));
if ($routeFromQuery !== '') {
    $route = trim($routeFromQuery, '/');
} else {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $prefix = '/api/';
    $route = str_starts_with($uriPath, $prefix) ? substr($uriPath, strlen($prefix)) : '';
    $route = trim($route, '/');
}

function translation_html_has_content(string $html): bool
{
    return trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== '';
}

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

function fetch_module_transcripts_payload(PDO $pdo, int $moduleId, ?int $componentId, string $locale, string $defaultLocale): array
{
    if ($componentId) {
        $stmt = $pdo->prepare('SELECT * FROM module_transcripts
          WHERE module_component_id = :component_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['component_id' => $componentId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM module_transcripts
          WHERE module_id = :module_id AND (module_component_id IS NULL OR module_component_id = 0)
          ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['module_id' => $moduleId]);
    }
    $transcripts = [];
    foreach ($stmt->fetchAll() as $transcriptRow) {
        $transcriptTr = translated_row($pdo, 'module_transcripts_translations', 'module_transcript_id', (int) $transcriptRow['id'], $locale, $defaultLocale) ?: [];
        $transcriptPayload = array_merge($transcriptRow, $transcriptTr);
        $transcriptPayload['file_path'] = normalize_public_asset_path((string) ($transcriptPayload['file_path'] ?? ''));
        $transcripts[] = $transcriptPayload;
    }

    return $transcripts;
}

function push_module_component_if_renderable(array &$payload, array $component): void
{
    $hasVideos = !empty($component['videos']);
    $hasTranscripts = !empty($component['transcripts']);
    $hasLiterature = translation_html_has_content((string) ($component['literature_html'] ?? ''));
    if (!$hasVideos && !$hasTranscripts && !$hasLiterature) {
        return;
    }
    $payload['components'][] = $component;
}

function resolve_component_literature_html(PDO $pdo, int $componentId, string $locale, string $literatureHtml): string
{
    $literatureHtml = (string) $literatureHtml;
    if (translation_html_has_content($literatureHtml)) {
        return $literatureHtml;
    }
    $anyLitStmt = $pdo->prepare('SELECT literature_html FROM module_components_translations
      WHERE module_component_id = :id AND TRIM(COALESCE(literature_html, "")) <> ""
      ORDER BY CASE WHEN locale = :locale THEN 0 ELSE 1 END, locale ASC
      LIMIT 1');
    $anyLitStmt->execute(['id' => $componentId, 'locale' => $locale]);
    $anyLit = $anyLitStmt->fetchColumn();
    if ($anyLit !== false && translation_html_has_content((string) $anyLit)) {
        return (string) $anyLit;
    }

    return '';
}

function load_legacy_module_components(PDO $pdo, int $moduleId, string $locale, string $defaultLocale, array $moduleTr): array
{
    $components = [];
    $lectureVideosStmt = $pdo->prepare('SELECT language_code, video_url, video_alt, sort_order
      FROM module_lecture_videos WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $lectureVideosStmt->execute(['module_id' => $moduleId]);
    $lectureVideos = $lectureVideosStmt->fetchAll();
    if ($lectureVideos) {
        $components[] = [
            'block_title' => trim((string) ($moduleTr['lecture_title'] ?? '')),
            'name' => trim((string) ($moduleTr['lecture_video_title_primary'] ?? '')),
            'videos' => $lectureVideos,
            'transcripts' => fetch_module_transcripts_payload($pdo, $moduleId, null, $locale, $defaultLocale),
            'literature_html' => translation_html_has_content((string) ($moduleTr['literature_html'] ?? ''))
                ? (string) $moduleTr['literature_html']
                : '',
        ];
    }

    $presentationVideosStmt = $pdo->prepare('SELECT language_code, video_url, video_alt, sort_order
      FROM module_presentation_videos WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
    $presentationVideosStmt->execute(['module_id' => $moduleId]);
    $presentationVideos = $presentationVideosStmt->fetchAll();
    if ($presentationVideos) {
        $components[] = [
            'block_title' => trim((string) ($moduleTr['presentation_title'] ?? '')),
            'name' => trim((string) ($moduleTr['presentation_video_title_primary'] ?? '')),
            'videos' => $presentationVideos,
            'transcripts' => [],
            'literature_html' => '',
        ];
    }

    return $components;
}

function merge_entity_with_translation(array $base, array $translation): array
{
    if ($translation === []) {
        return $base;
    }
    $entityId = (int) ($base['id'] ?? 0);
    unset($translation['id']);
    $merged = array_merge($base, $translation);
    if ($entityId > 0) {
        $merged['id'] = $entityId;
    }

    return $merged;
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

function fetch_module_card_meta(PDO $pdo, int $moduleId, string $locale, string $defaultLocale, bool $moduleComponentsEnabled): array
{
    if (!$moduleComponentsEnabled) {
        return ['titles' => [], 'titles_display' => '', 'languages' => [], 'languages_display' => ''];
    }
    $compStmt = $pdo->prepare('SELECT id FROM module_components WHERE module_id = :mid ORDER BY sort_order ASC, id ASC');
    $compStmt->execute(['mid' => $moduleId]);
    $compIds = $compStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!$compIds) {
        return ['titles' => [], 'titles_display' => '', 'languages' => [], 'languages_display' => ''];
    }
    $titles = [];
    $seenTitles = [];
    $languagesOrdered = [];
    $seenLang = [];
    foreach ($compIds as $cid) {
        $cidInt = (int) $cid;
        $tr = translated_row($pdo, 'module_components_translations', 'module_component_id', $cidInt, $locale, $defaultLocale);
        $title = trim((string) ($tr['block_title'] ?? ''));
        if ($title === '') {
            $anyStmt = $pdo->prepare('SELECT block_title FROM module_components_translations WHERE module_component_id = :cid AND TRIM(COALESCE(block_title, "")) <> "" ORDER BY CASE WHEN locale = :locale THEN 0 WHEN locale = :def THEN 1 ELSE 2 END, locale ASC LIMIT 1');
            $anyStmt->execute(['cid' => $cidInt, 'locale' => $locale, 'def' => $defaultLocale]);
            $any = $anyStmt->fetchColumn();
            if ($any !== false && $any !== null) {
                $title = trim((string) $any);
            }
        }
        if ($title !== '') {
            $key = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
            if (!isset($seenTitles[$key])) {
                $seenTitles[$key] = true;
                $titles[] = $title;
            }
        }
        $vidStmt = $pdo->prepare('SELECT language_code FROM module_component_videos WHERE module_component_id = :cid ORDER BY sort_order ASC, id ASC');
        $vidStmt->execute(['cid' => $cidInt]);
        foreach ($vidStmt->fetchAll(PDO::FETCH_COLUMN) as $lc) {
            $upper = strtoupper(trim((string) $lc));
            if ($upper === '') continue;
            if (!isset($seenLang[$upper])) {
                $seenLang[$upper] = true;
                $languagesOrdered[] = $upper;
            }
        }
    }
    return [
        'titles' => $titles,
        'titles_display' => implode(', ', $titles),
        'languages' => $languagesOrdered,
        'languages_display' => implode(', ', $languagesOrdered),
    ];
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
    $hasLectureSql = $moduleComponentsEnabled
        ? 'CASE WHEN EXISTS (
            SELECT 1 FROM module_components mc
            JOIN module_component_videos mcv ON mcv.module_component_id = mc.id
            WHERE mc.module_id = m.id
          ) THEN 1 ELSE 0 END'
        : 'CASE WHEN EXISTS (
            SELECT 1 FROM module_lecture_videos mlv WHERE mlv.module_id = m.id
          ) THEN 1 ELSE 0 END';
    $hasPresentationSql = $moduleComponentsEnabled
        ? 'CASE WHEN EXISTS (
            SELECT 1 FROM module_components mc
            JOIN module_component_videos mcv ON mcv.module_component_id = mc.id
            WHERE mc.module_id = m.id AND mc.sort_order >= 2
          ) THEN 1 ELSE 0 END'
        : 'CASE WHEN EXISTS (
            SELECT 1 FROM module_presentation_videos mpv WHERE mpv.module_id = m.id
          ) THEN 1 ELSE 0 END';
    $rows = $pdo->query("SELECT m.*,
      {$hasLectureSql} AS has_lecture,
      {$hasPresentationSql} AS has_presentation
      FROM modules m
      ORDER BY m.sort_order ASC, m.id ASC")->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $row['hero_background_image_path'] = normalize_public_asset_path((string) ($row['hero_background_image_path'] ?? ''));
        $row['presentation_file_path'] = normalize_public_asset_path((string) ($row['presentation_file_path'] ?? ''));
        $tr = translated_row($pdo, 'modules_translations', 'module_id', (int) $row['id'], $locale, $defaultLocale) ?: [];
        $merged = merge_entity_with_translation($row, $tr);
        $merged['module_id'] = (int) $row['id'];
        $meta = fetch_module_card_meta($pdo, (int) $row['id'], $locale, $defaultLocale, $moduleComponentsEnabled);
        $merged['component_titles'] = $meta['titles'];
        $merged['component_titles_display'] = $meta['titles_display'];
        $merged['component_languages'] = $meta['languages'];
        $merged['component_languages_display'] = $meta['languages_display'];
        $result[] = $merged;
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
    $allTranslationsSql = $moduleTranslationsHasFormats
        ? 'SELECT locale, lecture_title, lecture_video_title_primary, title, formats FROM modules_translations WHERE module_id = :module_id'
        : 'SELECT locale, lecture_title, lecture_video_title_primary, title FROM modules_translations WHERE module_id = :module_id';
    $allStmt = $pdo->prepare($allTranslationsSql);
    $allStmt->execute(['module_id' => (int) $row['id']]);
    $translations = [];
    foreach ($allStmt->fetchAll() as $trRow) {
        $translations[strtolower((string) $trRow['locale'])] = $trRow;
    }
    $payload = merge_entity_with_translation($row, $tr);
    $payload['module_id'] = (int) $row['id'];
    $payload['hero_background_image_path'] = normalize_public_asset_path((string) ($payload['hero_background_image_path'] ?? ''));
    $payload['presentation_file_path'] = normalize_public_asset_path((string) ($payload['presentation_file_path'] ?? ''));
    $payload['translations'] = $translations;
    $payload['components'] = [];
    $payload['lecture_videos'] = [];
    $payload['presentation_videos'] = [];
    $payload['transcripts'] = [];
    $moduleId = (int) $row['id'];
    if ($moduleComponentsEnabled) {
        $componentsStmt = $pdo->prepare('SELECT * FROM module_components WHERE module_id = :module_id ORDER BY sort_order ASC, id ASC');
        $componentsStmt->execute(['module_id' => $moduleId]);
        foreach ($componentsStmt->fetchAll() as $componentRow) {
            $componentTr = translated_row($pdo, 'module_components_translations', 'module_component_id', (int) $componentRow['id'], $locale, $defaultLocale) ?: [];
            $componentPayload = array_merge($componentRow, $componentTr);
            $componentPayload['block_title'] = trim((string) ($componentPayload['block_title'] ?? ''));
            $componentPayload['name'] = trim((string) ($componentPayload['name'] ?? ''));
            $componentPayload['literature_html'] = resolve_component_literature_html(
                $pdo,
                (int) $componentRow['id'],
                $locale,
                (string) ($componentPayload['literature_html'] ?? '')
            );

            $videosStmt = $pdo->prepare('SELECT language_code, video_url, video_alt, sort_order
              FROM module_component_videos WHERE module_component_id = :component_id ORDER BY sort_order ASC, id ASC');
            $videosStmt->execute(['component_id' => (int) $componentRow['id']]);
            $componentPayload['videos'] = $videosStmt->fetchAll();
            $componentPayload['transcripts'] = fetch_module_transcripts_payload(
                $pdo,
                $moduleId,
                (int) $componentRow['id'],
                $locale,
                $defaultLocale
            );
            push_module_component_if_renderable($payload, $componentPayload);
        }
        if (empty($payload['components'])) {
            foreach (load_legacy_module_components($pdo, $moduleId, $locale, $defaultLocale, $payload) as $legacyComponent) {
                push_module_component_if_renderable($payload, $legacyComponent);
            }
        }
    } else {
        foreach (load_legacy_module_components($pdo, $moduleId, $locale, $defaultLocale, $payload) as $legacyComponent) {
            push_module_component_if_renderable($payload, $legacyComponent);
        }
    }
    foreach ($payload['components'] as $componentRow) {
        if (!empty($componentRow['transcripts'])) {
            $payload['transcripts'] = array_merge($payload['transcripts'], $componentRow['transcripts']);
        }
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
        $result[] = merge_entity_with_translation($row, $tr);
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
        $item = merge_entity_with_translation($row, $tr);
        $item['display_title'] = trim((string) ($item['custom_title'] ?? ''));
        if ($item['display_title'] === '') {
            $titleStmt = $pdo->prepare('SELECT custom_title FROM module_readings_translations
              WHERE module_reading_id = :id AND TRIM(COALESCE(custom_title, \'\')) <> \'\'
              ORDER BY CASE WHEN locale = :locale THEN 0 ELSE 1 END, locale ASC
              LIMIT 1');
            $titleStmt->execute(['id' => (int) $row['id'], 'locale' => $locale]);
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
                $linkedPublication = merge_entity_with_translation($pubBase, $pubTr);
                if (trim((string) ($linkedPublication['title'] ?? '')) === '') {
                    $pubTitleStmt = $pdo->prepare('SELECT title FROM publications_translations
                      WHERE publication_id = :id AND TRIM(COALESCE(title, \'\')) <> \'\'
                      ORDER BY CASE WHEN locale = :locale THEN 0 ELSE 1 END, locale ASC
                      LIMIT 1');
                    $pubTitleStmt->execute(['id' => (int) $pubBase['id'], 'locale' => $locale]);
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
