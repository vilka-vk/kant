<?php
declare(strict_types=1);

function module_components_table_exists(PDO $pdo): bool
{
    return (bool) $pdo->query("SELECT COUNT(*)
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'module_components'")->fetchColumn();
}

function fetch_module_components(PDO $pdo, int $moduleId, string $locale): array
{
    $stmt = $pdo->prepare('SELECT
        mc.*,
        mct.block_title,
        mct.name,
        (
          SELECT GROUP_CONCAT(DISTINCT UPPER(mcv.language_code) ORDER BY mcv.sort_order ASC, mcv.id ASC SEPARATOR ", ")
          FROM module_component_videos mcv
          WHERE mcv.module_component_id = mc.id
        ) AS video_languages,
        EXISTS(
          SELECT 1 FROM module_transcripts mt WHERE mt.module_component_id = mc.id LIMIT 1
        ) AS has_transcripts,
        EXISTS(
          SELECT 1 FROM module_components_translations mctl
          WHERE mctl.module_component_id = mc.id AND TRIM(COALESCE(mctl.literature_html, "")) <> ""
        ) AS has_literature
      FROM module_components mc
      LEFT JOIN module_components_translations mct
        ON mct.module_component_id = mc.id AND mct.locale = :locale
      WHERE mc.module_id = :module_id
      ORDER BY mc.sort_order ASC, mc.id ASC');
    $stmt->execute(['module_id' => $moduleId, 'locale' => $locale]);
    return $stmt->fetchAll();
}

function fetch_component_translations(PDO $pdo, int $componentId): array
{
    $stmt = $pdo->prepare('SELECT locale, block_title, name, literature_html
      FROM module_components_translations WHERE module_component_id = :id');
    $stmt->execute(['id' => $componentId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['locale']] = $row;
    }
    return $map;
}

function fetch_component_videos(PDO $pdo, int $componentId): array
{
    $stmt = $pdo->prepare('SELECT * FROM module_component_videos
      WHERE module_component_id = :id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['id' => $componentId]);
    return $stmt->fetchAll();
}

function fetch_component_transcripts(PDO $pdo, int $componentId, string $locale): array
{
    $stmt = $pdo->prepare('SELECT mt.*, mtt.display_name
      FROM module_transcripts mt
      LEFT JOIN module_transcripts_translations mtt
        ON mtt.module_transcript_id = mt.id AND mtt.locale = :locale
      WHERE mt.module_component_id = :component_id
      ORDER BY mt.sort_order ASC, mt.id ASC');
    $stmt->execute(['component_id' => $componentId, 'locale' => $locale]);
    return $stmt->fetchAll();
}

function delete_module_component_files(PDO $pdo, int $componentId): void
{
    $videoStmt = $pdo->prepare('SELECT video_url FROM module_component_videos WHERE module_component_id = :id');
    $videoStmt->execute(['id' => $componentId]);
    foreach ($videoStmt->fetchAll() as $videoRow) {
        delete_public_file((string) ($videoRow['video_url'] ?? ''));
    }

    $transcriptStmt = $pdo->prepare('SELECT file_path FROM module_transcripts WHERE module_component_id = :id');
    $transcriptStmt->execute(['id' => $componentId]);
    foreach ($transcriptStmt->fetchAll() as $transcriptRow) {
        delete_public_file((string) ($transcriptRow['file_path'] ?? ''));
    }
}

function save_component_translations(PDO $pdo, int $componentId, array $locales, array $post): void
{
    foreach ($locales as $locale) {
        $pdo->prepare('INSERT INTO module_components_translations
          (module_component_id, locale, block_title, name, literature_html)
          VALUES (:component_id, :locale, :block_title, :name, :literature_html)
          ON DUPLICATE KEY UPDATE
            block_title = VALUES(block_title),
            name = VALUES(name),
            literature_html = VALUES(literature_html)')
            ->execute([
                'component_id' => $componentId,
                'locale' => $locale,
                'block_title' => trim((string) ($post['block_title_' . $locale] ?? '')),
                'name' => trim((string) ($post['name_' . $locale] ?? '')),
                'literature_html' => (string) ($post['literature_html_' . $locale] ?? ''),
            ]);
    }
}
