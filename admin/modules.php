<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';
require __DIR__ . '/lib/uploads.php';

require_auth();
$pdo = db();
$locales = $config['app']['supported_locales'];
$languageCodePattern = '/^[a-z]{2,5}$/';

function assertLanguageCode(string $value, string $pattern): bool
{
    return (bool) preg_match($pattern, strtolower(trim($value)));
}

function makeModuleSlug(int $moduleNumber, array $locales, array $post): string
{
    $title = '';
    foreach ($locales as $locale) {
        $candidate = trim((string) ($post['title_' . $locale] ?? ''));
        if ($candidate !== '') {
            $title = $candidate;
            break;
        }
    }
    $raw = trim((string) $moduleNumber) . ' ' . $title;
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
    if ($ascii === false) {
        $ascii = $raw;
    }
    $slug = strtolower((string) $ascii);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        return 'module-' . max(1, $moduleNumber);
    }
    return $slug;
}

function nextSortOrder(PDO $pdo, string $table, int $moduleId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM {$table} WHERE module_id = :module_id");
    $stmt->execute(['module_id' => $moduleId]);
    $row = $stmt->fetch();
    return (int) ($row['next_sort'] ?? 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $action = (string) ($_POST['action'] ?? '');
    $moduleId = (int) ($_POST['id'] ?? 0);

    if ($action === 'save_modules_page_hero') {
        $heroRow = $pdo->prepare('SELECT id, background_image_path, subtitle_enabled FROM hero_sections WHERE page_key = :page_key LIMIT 1');
        $heroRow->execute(['page_key' => 'modules']);
        $hero = $heroRow->fetch() ?: null;
        $heroBg = trim((string) ($_POST['hero_modules_background_image_path'] ?? ($hero['background_image_path'] ?? '')));
        try {
            $uploadedHero = upload_public_file('hero_modules_background_file', 'hero', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
            if ($uploadedHero) {
                $heroBg = $uploadedHero;
            }
        } catch (Throwable $e) {
            redirect('/admin/modules.php?error=' . urlencode($e->getMessage()));
        }
        if ($hero && (int) $hero['id'] > 0) {
            $heroId = (int) $hero['id'];
            $pdo->prepare('UPDATE hero_sections SET subtitle_enabled = :subtitle_enabled, background_image_path = :background_image_path WHERE id = :id')
                ->execute([
                    'id' => $heroId,
                    'subtitle_enabled' => (int) ($_POST['hero_modules_subtitle_enabled'] ?? 1) > 0 ? 1 : 0,
                    'background_image_path' => $heroBg,
                ]);
        } else {
            $pdo->prepare('INSERT INTO hero_sections (page_key, subtitle_enabled, background_image_path) VALUES (:page_key, :subtitle_enabled, :background_image_path)')
                ->execute([
                    'page_key' => 'modules',
                    'subtitle_enabled' => (int) ($_POST['hero_modules_subtitle_enabled'] ?? 1) > 0 ? 1 : 0,
                    'background_image_path' => $heroBg,
                ]);
            $heroId = (int) $pdo->lastInsertId();
        }
        foreach ($locales as $locale) {
            $pdo->prepare('INSERT INTO hero_sections_translations (hero_section_id, locale, title, subtitle)
              VALUES (:hero_section_id, :locale, :title, :subtitle)
              ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle)')
                ->execute([
                    'hero_section_id' => $heroId,
                    'locale' => $locale,
                    'title' => $locale === 'ru' ? 'Модули' : 'Modules',
                    'subtitle' => trim((string) ($_POST['hero_modules_subtitle_' . $locale] ?? '')),
                ]);
        }
        redirect('/admin/modules.php?saved_hero=1');
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM modules WHERE id = :id')->execute(['id' => $moduleId]);
        redirect('/admin/modules.php');
    }

    if ($action === 'regenerate_slugs') {
        $mods = $pdo->query('SELECT id, module_number FROM modules ORDER BY id ASC')->fetchAll();
        foreach ($mods as $mod) {
            $modId = (int) $mod['id'];
            $title = '';
            foreach ($locales as $locale) {
                $trStmt = $pdo->prepare('SELECT title FROM modules_translations WHERE module_id = :module_id AND locale = :locale LIMIT 1');
                $trStmt->execute(['module_id' => $modId, 'locale' => $locale]);
                $tr = $trStmt->fetch();
                $candidate = trim((string) ($tr['title'] ?? ''));
                if ($candidate !== '') {
                    $title = $candidate;
                    break;
                }
            }
            $slug = makeModuleSlug((int) $mod['module_number'], $locales, ['title_' . ($locales[0] ?? 'ru') => $title]);
            $pdo->prepare('UPDATE modules SET slug = :slug WHERE id = :id')->execute(['slug' => $slug, 'id' => $modId]);
        }
        redirect('/admin/modules.php?saved=1');
    }

    if (in_array($action, ['add_lecture_video', 'update_lecture_video', 'add_presentation_video', 'update_presentation_video'], true)) {
        $languageCode = strtolower(trim((string) ($_POST['video_language_code'] ?? '')));
        if (!assertLanguageCode($languageCode, $languageCodePattern)) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=invalid_lang');
        }
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $table = str_contains($action, 'lecture') ? 'module_lecture_videos' : 'module_presentation_videos';
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        try {
            $uploadedVideo = upload_public_file('video_file', 'module-videos', ['mp4', 'webm', 'ogg']);
            if ($uploadedVideo) {
                $videoUrl = $uploadedVideo;
            }
        } catch (Throwable $e) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode($e->getMessage()));
        }
        if ($videoUrl === '') {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode('Provide video URL or upload a file.'));
        }
        $payload = [
            'module_id' => $moduleId,
            'language_code' => $languageCode,
            'video_url' => $videoUrl,
            'video_alt' => trim((string) ($_POST['video_alt'] ?? '')),
            'sort_order' => (int) ($_POST['video_sort_order'] ?? 0),
        ];
        if ($action === 'add_lecture_video' || $action === 'add_presentation_video') {
            $payload['sort_order'] = nextSortOrder($pdo, $table, $moduleId);
            $pdo->prepare("INSERT INTO {$table} (module_id, language_code, video_url, video_alt, sort_order)
              VALUES (:module_id, :language_code, :video_url, :video_alt, :sort_order)")->execute($payload);
        } else {
            $pdo->prepare("UPDATE {$table} SET language_code=:language_code, video_url=:video_url, video_alt=:video_alt, sort_order=:sort_order
              WHERE id=:video_id AND module_id=:module_id")->execute($payload + ['video_id' => $videoId]);
        }
        redirect('/admin/modules.php?edit=' . $moduleId);
    }

    if (in_array($action, ['delete_lecture_video', 'delete_presentation_video'], true)) {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $table = $action === 'delete_lecture_video' ? 'module_lecture_videos' : 'module_presentation_videos';
        $pdo->prepare("DELETE FROM {$table} WHERE id = :id AND module_id = :module_id")->execute([
            'id' => $videoId,
            'module_id' => $moduleId,
        ]);
        redirect('/admin/modules.php?edit=' . $moduleId);
    }

    if (in_array($action, ['save_transcript', 'delete_transcript'], true)) {
        if ($action === 'delete_transcript') {
            $id = (int) ($_POST['transcript_id'] ?? 0);
            $pdo->prepare('DELETE FROM module_transcripts WHERE id = :id AND module_id = :module_id')->execute(['id' => $id, 'module_id' => $moduleId]);
            redirect('/admin/modules.php?edit=' . $moduleId);
        }
        $id = (int) ($_POST['transcript_id'] ?? 0);
        $filePath = trim((string) ($_POST['file_path'] ?? ''));
        $languageCode = strtolower(trim((string) ($_POST['language_code'] ?? '')));
        if (!assertLanguageCode($languageCode, $languageCodePattern)) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=invalid_lang');
        }
        try {
            $uploadedTranscript = upload_public_file('transcript_file', 'module-transcripts', ['pdf', 'doc', 'docx', 'txt']);
            if ($uploadedTranscript) {
                $filePath = $uploadedTranscript;
            }
        } catch (Throwable $e) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode($e->getMessage()));
        }
        $payload = [
            'module_id' => $moduleId,
            'file_path' => $filePath,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
        if ($payload['file_path'] === '') {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode('Transcript file path is required.'));
        }
        if ($id > 0) {
            $pdo->prepare('UPDATE module_transcripts SET file_path=:file_path, sort_order=:sort_order WHERE id=:id AND module_id=:module_id')
                ->execute($payload + ['id' => $id]);
        } else {
            $payload['sort_order'] = nextSortOrder($pdo, 'module_transcripts', $moduleId);
            $pdo->prepare('INSERT INTO module_transcripts (module_id, file_path, sort_order) VALUES (:module_id,:file_path,:sort_order)')
                ->execute($payload);
            $id = (int) $pdo->lastInsertId();
        }
        foreach ($locales as $locale) {
            $pdo->prepare('INSERT INTO module_transcripts_translations (module_transcript_id, locale, display_name)
              VALUES (:id,:locale,:display_name)
              ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)')
                ->execute([
                    'id' => $id,
                    'locale' => $locale,
                    'display_name' => $languageCode,
                ]);
        }
        redirect('/admin/modules.php?edit=' . $moduleId);
    }

    if (in_array($action, ['save_reading', 'delete_reading'], true)) {
        if ($action === 'delete_reading') {
            $id = (int) ($_POST['reading_id'] ?? 0);
            $pdo->prepare('DELETE FROM module_readings WHERE id = :id AND module_id = :module_id')->execute(['id' => $id, 'module_id' => $moduleId]);
            redirect('/admin/modules.php?edit=' . $moduleId);
        }
        $id = (int) ($_POST['reading_id'] ?? 0);
        $linked = (int) ($_POST['linked_publication_id'] ?? 0);
        $customUrl = trim((string) ($_POST['custom_url'] ?? ''));
        $customFile = trim((string) ($_POST['custom_file_path'] ?? ''));
        $customCover = trim((string) ($_POST['custom_cover_image_path'] ?? ''));
        try {
            $uploadedReadingFile = upload_public_file('custom_file_upload', 'module-readings', ['pdf', 'doc', 'docx', 'txt']);
            if ($uploadedReadingFile) {
                $customFile = $uploadedReadingFile;
            }
            $uploadedReadingCover = upload_public_file('custom_cover_upload', 'module-reading-covers', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
            if ($uploadedReadingCover) {
                $customCover = $uploadedReadingCover;
            }
        } catch (Throwable $e) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode($e->getMessage()));
        }
        if ($linked > 0) {
            $customUrl = '';
            $customFile = '';
            $customCover = '';
        }
        if ($linked === 0 && (($customUrl === '' && $customFile === '') || ($customUrl !== '' && $customFile !== ''))) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=reading_xor');
        }
        $payload = [
            'module_id' => $moduleId,
            'linked_publication_id' => $linked > 0 ? $linked : null,
            'custom_url' => $customUrl,
            'custom_file_path' => $customFile,
            'custom_cover_image_path' => $customCover,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE module_readings SET linked_publication_id=:linked_publication_id, custom_url=:custom_url, custom_file_path=:custom_file_path,
              custom_cover_image_path=:custom_cover_image_path, sort_order=:sort_order WHERE id=:id AND module_id=:module_id')
                ->execute($payload + ['id' => $id]);
        } else {
            $payload['sort_order'] = nextSortOrder($pdo, 'module_readings', $moduleId);
            $pdo->prepare('INSERT INTO module_readings (module_id, linked_publication_id, custom_url, custom_file_path, custom_cover_image_path, sort_order)
              VALUES (:module_id,:linked_publication_id,:custom_url,:custom_file_path,:custom_cover_image_path,:sort_order)')
                ->execute($payload);
            $id = (int) $pdo->lastInsertId();
        }
        foreach ($locales as $locale) {
            $customTitle = $linked > 0 ? '' : trim((string) ($_POST['custom_title_' . $locale] ?? ''));
            $pdo->prepare('INSERT INTO module_readings_translations (module_reading_id, locale, custom_title)
              VALUES (:id,:locale,:custom_title)
              ON DUPLICATE KEY UPDATE custom_title = VALUES(custom_title)')
                ->execute([
                    'id' => $id,
                    'locale' => $locale,
                    'custom_title' => $customTitle,
                ]);
        }
        redirect('/admin/modules.php?edit=' . $moduleId);
    }

    $id = $moduleId;
    $heroBackground = trim((string) ($_POST['hero_background_image_path'] ?? ''));
    $presentationFile = trim((string) ($_POST['presentation_file_path'] ?? ''));
    try {
        $uploadedHero = upload_public_file('hero_background_file', 'module-hero', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
        if ($uploadedHero) {
            $heroBackground = $uploadedHero;
        }
        $uploadedPresentation = upload_public_file('presentation_file_upload', 'module-presentations', ['pdf', 'ppt', 'pptx']);
        if ($uploadedPresentation) {
            $presentationFile = $uploadedPresentation;
        }
    } catch (Throwable $e) {
        redirect('/admin/modules.php?edit=' . $moduleId . '&error=' . urlencode($e->getMessage()));
    }

    $moduleNumber = (int) ($_POST['sort_order'] ?? 0);
    $base = [
        'slug' => makeModuleSlug($moduleNumber, $locales, $_POST),
        'module_number' => $moduleNumber,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'languages' => trim((string) ($_POST['languages'] ?? '')),
        'formats' => trim((string) ($_POST['formats'] ?? '')),
        'list_duration_display' => trim((string) ($_POST['list_duration_display'] ?? '')),
        'hero_background_image_path' => $heroBackground,
        'presentation_file_path' => $presentationFile,
    ];

    if ($id > 0) {
        $pdo->prepare('UPDATE modules SET slug=:slug,module_number=:module_number,sort_order=:sort_order,languages=:languages,
          formats=:formats,list_duration_display=:list_duration_display,hero_background_image_path=:hero_background_image_path,presentation_file_path=:presentation_file_path WHERE id=:id')
            ->execute($base + ['id' => $id]);
    } else {
        $pdo->prepare('INSERT INTO modules (slug,module_number,sort_order,languages,formats,list_duration_display,hero_background_image_path,presentation_file_path)
          VALUES (:slug,:module_number,:sort_order,:languages,:formats,:list_duration_display,:hero_background_image_path,:presentation_file_path)')
            ->execute($base);
        $id = (int) $pdo->lastInsertId();
    }

    foreach ($locales as $locale) {
        $shortDescription = trim((string) ($_POST['short_description_' . $locale] ?? ''));
        $pdo->prepare('INSERT INTO modules_translations (module_id, locale, title, short_description, hero_kicker, hero_subtitle, lecture_title, presentation_title, literature_html)
          VALUES (:module_id,:locale,:title,:short_description,:hero_kicker,:hero_subtitle,:lecture_title,:presentation_title,:literature_html)
          ON DUPLICATE KEY UPDATE title = VALUES(title), short_description = VALUES(short_description), hero_kicker = VALUES(hero_kicker),
          hero_subtitle = VALUES(hero_subtitle), lecture_title = VALUES(lecture_title), presentation_title = VALUES(presentation_title), literature_html = VALUES(literature_html)')
            ->execute([
                'module_id' => $id,
                'locale' => $locale,
                'title' => trim((string) ($_POST['title_' . $locale] ?? '[empty]')),
                'short_description' => $shortDescription,
                'hero_kicker' => '',
                'hero_subtitle' => $shortDescription,
                'lecture_title' => trim((string) ($_POST['lecture_title_' . $locale] ?? '')),
                'presentation_title' => trim((string) ($_POST['presentation_title_' . $locale] ?? '')),
                'literature_html' => (string) ($_POST['literature_html_' . $locale] ?? ''),
            ]);
    }
    redirect('/admin/modules.php?edit=' . $id . '&saved=1');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
$trMap = [];
$lectureVideos = [];
$presentationVideos = [];
$transcripts = [];
$readings = [];
$publicationOptions = [];
$editLectureVideoId = (int) ($_GET['lecture_video'] ?? 0);
$editPresentationVideoId = (int) ($_GET['presentation_video'] ?? 0);
$editLectureVideo = null;
$editPresentationVideo = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM modules WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editRow = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, title, short_description, hero_kicker, hero_subtitle, lecture_title, presentation_title, literature_html FROM modules_translations WHERE module_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr;
    }
    $lectureVideos = $pdo->prepare('SELECT * FROM module_lecture_videos WHERE module_id = :id ORDER BY sort_order ASC, id ASC');
    $lectureVideos->execute(['id' => $editId]);
    $lectureVideos = $lectureVideos->fetchAll();
    foreach ($lectureVideos as $videoRow) {
        if ((int) $videoRow['id'] === $editLectureVideoId) {
            $editLectureVideo = $videoRow;
            break;
        }
    }

    $presentationVideos = $pdo->prepare('SELECT * FROM module_presentation_videos WHERE module_id = :id ORDER BY sort_order ASC, id ASC');
    $presentationVideos->execute(['id' => $editId]);
    $presentationVideos = $presentationVideos->fetchAll();
    foreach ($presentationVideos as $videoRow) {
        if ((int) $videoRow['id'] === $editPresentationVideoId) {
            $editPresentationVideo = $videoRow;
            break;
        }
    }

    $transcripts = $pdo->prepare('SELECT mt.*, mtt.display_name
      FROM module_transcripts mt
      LEFT JOIN module_transcripts_translations mtt
      ON mtt.module_transcript_id = mt.id AND mtt.locale = :locale
      WHERE mt.module_id = :id
      ORDER BY mt.sort_order ASC, mt.id ASC');
    $transcripts->execute(['id' => $editId, 'locale' => admin_locale()]);
    $transcripts = $transcripts->fetchAll();

    $readings = $pdo->prepare('SELECT mr.* FROM module_readings mr WHERE mr.module_id = :id ORDER BY mr.sort_order ASC, mr.id ASC');
    $readings->execute(['id' => $editId]);
    $readings = $readings->fetchAll();
}

$publicationOptions = $pdo->query('SELECT id FROM publications ORDER BY display_order ASC, id ASC')->fetchAll();
$rowsStmt = $pdo->prepare('SELECT
    m.id,
    m.sort_order,
    m.languages,
    COALESCE(mt.title, "") AS title,
    CASE WHEN EXISTS (
      SELECT 1 FROM module_lecture_videos lv WHERE lv.module_id = m.id LIMIT 1
    ) THEN 1 ELSE 0 END AS has_lecture,
    CASE WHEN EXISTS (
      SELECT 1 FROM module_presentation_videos pv WHERE pv.module_id = m.id LIMIT 1
    ) THEN 1 ELSE 0 END AS has_presentation
  FROM modules m
  LEFT JOIN modules_translations mt
    ON mt.module_id = m.id AND mt.locale = :locale
  ORDER BY m.sort_order ASC, m.id ASC');
$rowsStmt->execute(['locale' => admin_locale()]);
$rows = $rowsStmt->fetchAll();
$heroModulesStmt = $pdo->prepare('SELECT * FROM hero_sections WHERE page_key = :page_key LIMIT 1');
$heroModulesStmt->execute(['page_key' => 'modules']);
$heroModules = $heroModulesStmt->fetch() ?: [];
$heroModulesTrRows = [];
if (!empty($heroModules['id'])) {
    $heroTrStmt = $pdo->prepare('SELECT locale, title, subtitle FROM hero_sections_translations WHERE hero_section_id = :hero_section_id');
    $heroTrStmt->execute(['hero_section_id' => (int) $heroModules['id']]);
    foreach ($heroTrStmt->fetchAll() as $row) {
        $heroModulesTrRows[$row['locale']] = $row;
    }
}

$isModuleFormOpen = $editRow || (string) ($_GET['form'] ?? '') === '1';
admin_header(tr('Модули', 'Modules'));
?>
<style>
.module-section{margin-top:14px}
.module-section summary{cursor:pointer;font-weight:700;padding:10px 12px;background:#f2f2f5;border:1px solid #ddd;border-radius:8px}
.module-section[open] summary{border-bottom-left-radius:0;border-bottom-right-radius:0}
.module-section__body{border:1px solid #ddd;border-top:none;border-bottom-left-radius:8px;border-bottom-right-radius:8px;padding:12px;background:#fff}
.inline-help{font-size:12px;color:#666}
.compact-inputs input{padding:6px 8px;font-size:13px}
.table-scroll{max-height:280px;overflow:auto;border:1px solid #e2e2e2;border-radius:8px}
.table-scroll table{margin:0}
.table-scroll thead th{position:sticky;top:0;background:#fafafa;z-index:1}
.filter-row{display:flex;gap:8px;align-items:center;margin:8px 0 10px}
.filter-row input{max-width:140px}
</style>
<?php if (!$isModuleFormOpen): ?>
<div class="card">
  <h2><?= h(tr('Hero блока "Модули"', 'Modules hero block')) ?></h2>
  <?php if (!empty($_GET['saved_hero'])): ?><p class="ok"><?= h(tr('Hero сохранен.', 'Hero saved.')) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_modules_page_hero">
    <div class="grid">
      <div><label><?= h(tr('Фон hero (путь)', 'Hero background (path)')) ?></label><input name="hero_modules_background_image_path" value="<?= h((string) ($heroModules['background_image_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить фон hero', 'Upload hero background')) ?></label><input type="file" name="hero_modules_background_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Показывать subtitle', 'Show subtitle')) ?></label><select name="hero_modules_subtitle_enabled"><option value="1" <?= ((int) ($heroModules['subtitle_enabled'] ?? 1) === 1) ? 'selected' : '' ?>><?= h(tr('Да', 'Yes')) ?></option><option value="0" <?= ((int) ($heroModules['subtitle_enabled'] ?? 1) === 0) ? 'selected' : '' ?>><?= h(tr('Нет', 'No')) ?></option></select></div>
    </div>
    <hr style="margin:12px 0">
    <?php
      $heroLeftLocale = $locales[0] ?? 'ru';
      $heroRightLocale = $locales[1] ?? ($locales[0] ?? 'en');
    ?>
    <div class="grid" style="margin-bottom:8px">
      <div><label>Hero subtitle (<?= h(strtoupper($heroLeftLocale)) ?>)</label><input name="hero_modules_subtitle_<?= h($heroLeftLocale) ?>" value="<?= h((string) ($heroModulesTrRows[$heroLeftLocale]['subtitle'] ?? '')) ?>"></div>
      <div><label>Hero subtitle (<?= h(strtoupper($heroRightLocale)) ?>)</label><input name="hero_modules_subtitle_<?= h($heroRightLocale) ?>" value="<?= h((string) ($heroModulesTrRows[$heroRightLocale]['subtitle'] ?? '')) ?>"></div>
    </div>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить hero для страницы модулей', 'Save modules hero')) ?></button></div>
  </form>
</div>

<div class="card">
  <div class="kant-section-head">
    <h2><?= h(tr('Модули', 'Modules')) ?></h2>
    <div class="actions">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="regenerate_slugs">
        <button type="submit" class="btn btn-secondary"><?= h(tr('Обновить slug', 'Regenerate slugs')) ?></button>
      </form>
      <a class="btn" href="/admin/modules.php?form=1"><?= h(tr('Добавить +', 'Add +')) ?></a>
    </div>
  </div>
  <table>
    <thead><tr><th><?= h(tr('Номер', 'Number')) ?></th><th><?= h(tr('Название', 'Title')) ?></th><th><?= h(tr('Языки', 'Languages')) ?></th><th><?= h(tr('Лекция', 'Lecture')) ?></th><th><?= h(tr('Презентация', 'Presentation')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= h((string) $row['sort_order']) ?></td>
        <td><a href="/admin/modules.php?form=1&edit=<?= h((string) $row['id']) ?>"><?= h((string) $row['title']) ?></a></td>
        <td><?= h($row['languages']) ?></td>
        <td><?= h(((int) $row['has_lecture']) > 0 ? tr('Да', 'Yes') : tr('Нет', 'No')) ?></td>
        <td><?= h(((int) $row['has_presentation']) > 0 ? tr('Да', 'Yes') : tr('Нет', 'No')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($isModuleFormOpen): ?>
<div class="card">
  <div class="kant-drawer-actions">
    <h2><?= h($editRow ? tr('Редактирование модуля', 'Edit module') : tr('Добавление модуля', 'Add module')) ?></h2>
    <a class="btn btn-secondary" href="/admin/modules.php"><?= h(tr('Назад к списку', 'Back to list')) ?></a>
  </div>
  <h1><?= h(tr('Модули', 'Modules')) ?></h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'invalid_lang'): ?><p class="err"><?= h(tr('Неверный формат кода языка. Используйте только буквы, 2-5 символов (например: en, ru, arm).', 'Language code format is invalid. Use only letters, 2-5 chars (e.g. en, ru, arm).')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'reading_xor'): ?><p class="err"><?= h(tr('Для материала без связанной публикации нужно указать только одно: URL или путь к файлу.', 'For reading without linked publication, exactly one of URL or file path is required.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && !in_array((string) $_GET['error'], ['invalid_lang', 'reading_xor'], true)): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($editRow['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label><?= h(tr('Slug (автоматически)', 'Slug (auto)')) ?></label><input name="slug" readonly value="<?= h((string) ($editRow['slug'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Номер модуля', 'Module Number')) ?></label><input type="number" name="sort_order" required value="<?= h((string) ($editRow['sort_order'] ?? 1)) ?>"></div>
      <div><label>Languages</label><input name="languages" required value="<?= h((string) ($editRow['languages'] ?? 'EN, RU')) ?>"></div>
      <div><label>Formats</label><input name="formats" value="<?= h((string) ($editRow['formats'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Длительность', 'Duration')) ?></label><input name="list_duration_display" value="<?= h((string) ($editRow['list_duration_display'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Путь к hero-фону', 'Hero background image path')) ?></label><input name="hero_background_image_path" value="<?= h((string) ($editRow['hero_background_image_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Путь к файлу презентации (необязательно)', 'Presentation file path (optional)')) ?></label><input name="presentation_file_path" value="<?= h((string) ($editRow['presentation_file_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить hero-изображение', 'Upload hero image')) ?></label><input type="file" name="hero_background_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Загрузить PDF/PPT презентации (необязательно)', 'Upload presentation PDF/PPT (optional)')) ?></label><input type="file" name="presentation_file_upload" accept=".pdf,.ppt,.pptx"></div>
    </div>
    <hr style="margin:16px 0">
    <?php
      $leftLocale = $locales[0] ?? 'ru';
      $rightLocale = $locales[1] ?? ($locales[0] ?? 'en');
    ?>
    <p class="muted"><?= h(tr('Таблица локализации: слева', 'Localization table: left column is')) ?> <?= h(strtoupper($leftLocale)) ?>, <?= h(tr('справа', 'right column is')) ?> <?= h(strtoupper($rightLocale)) ?>.</p>
    <table style="margin-bottom:12px">
      <thead><tr><th><?= h(tr('Поле', 'Field')) ?></th><th><?= h(strtoupper($leftLocale)) ?></th><th><?= h(strtoupper($rightLocale)) ?></th></tr></thead>
      <tbody>
        <?php
        $translationFields = [
          'title' => tr('Название модуля', 'Module title'),
          'short_description' => tr('Короткое описание', 'Short description'),
          'lecture_title' => 'Lecture title',
          'presentation_title' => 'Presentation title',
          'literature_html' => 'Literature text (WYSIWYG)',
        ];
        foreach ($translationFields as $fieldKey => $label):
          $leftValue = (string) ($trMap[$leftLocale][$fieldKey] ?? '');
          $rightValue = (string) ($trMap[$rightLocale][$fieldKey] ?? '');
          $isLong = in_array($fieldKey, ['short_description', 'literature_html'], true);
          $textareaClass = $fieldKey === 'literature_html' ? 'wysiwyg' : '';
        ?>
        <tr>
          <td><strong><?= h($label) ?></strong></td>
          <td>
            <?php if ($isLong): ?>
              <textarea class="<?= h($textareaClass) ?>" rows="4" name="<?= h($fieldKey . '_' . $leftLocale) ?>"><?= h($leftValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($fieldKey . '_' . $leftLocale) ?>" value="<?= h($leftValue) ?>">
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isLong): ?>
              <textarea class="<?= h($textareaClass) ?>" rows="4" name="<?= h($fieldKey . '_' . $rightLocale) ?>"><?= h($rightValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($fieldKey . '_' . $rightLocale) ?>" value="<?= h($rightValue) ?>">
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="actions">
      <button type="submit"><?= $editRow ? h(tr('Обновить модуль', 'Update module')) : h(tr('Создать модуль', 'Create module')) ?></button>
      <a class="btn btn-secondary" href="/admin/modules.php"><?= h(tr('Новый', 'New')) ?></a>
    </div>
  </form>
</div>

<?php if ($editRow): ?>
<details class="module-section card" open>
  <summary><?= h(tr('Видео лекции', 'Lecture Videos')) ?></summary>
  <div class="module-section__body">
  <div class="kant-section-head">
    <h3><?= h(tr('Список видео лекций', 'Lecture videos list')) ?></h3>
    <button type="button" class="btn" data-toggle-form="lecture-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <div class="filter-row">
    <label for="lecture-filter"><strong><?= h(tr('Фильтр по языку:', 'Filter by language:')) ?></strong></label>
    <input id="lecture-filter" type="text" placeholder="<?= h(tr('например: ru, en', 'e.g. ru, en')) ?>">
  </div>
  <div class="table-scroll">
  <table id="lecture-table"><thead><tr><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Language')) ?></th><th><?= h(tr('Видео (ссылка/файл)', 'Video (link/file)')) ?></th><th><?= h(tr('Подпись к видео', 'Video caption')) ?></th><th><?= h(tr('Действия', 'Actions')) ?></th></tr></thead><tbody>
    <?php foreach ($lectureVideos as $video): ?>
      <tr data-language-code="<?= h(strtolower((string) $video['language_code'])) ?>">
        <td><?= h((string) $video['sort_order']) ?></td><td><?= h((string) $video['language_code']) ?></td><td><?= h((string) $video['video_url']) ?></td><td><?= h((string) $video['video_alt']) ?></td>
        <td class="actions compact-inputs">
          <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>&lecture_video=<?= h((string) $video['id']) ?>"><?= h(tr('Изменить', 'Edit')) ?></a>
          <form method="post" onsubmit="return confirm('<?= h(tr('Удалить видео лекции?', 'Delete lecture video?')) ?>')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_lecture_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>"><button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <p class="inline-help"><?= h(tr('Подсказка: используйте embed URL, например https://www.youtube.com/embed/... или Vimeo player URL.', 'URL hint: use embed URL, e.g. https://www.youtube.com/embed/... or Vimeo player URL.')) ?></p>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="lecture-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= h($editLectureVideo ? 'update_lecture_video' : 'add_lecture_video') ?>">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <?php if ($editLectureVideo): ?><input type="hidden" name="video_id" value="<?= h((string) $editLectureVideo['id']) ?>"><?php endif; ?>
    <div class="grid">
      <div><label><?= h(tr('Код языка', 'Language code')) ?></label><input name="video_language_code" placeholder="en / ru / arm" required pattern="[A-Za-z]{2,5}" value="<?= h((string) ($editLectureVideo['language_code'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Ссылка на видео (embed) или путь к файлу', 'Video URL (embed) or file path')) ?></label><input name="video_url" value="<?= h((string) ($editLectureVideo['video_url'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить видеофайл', 'Upload video file')) ?></label><input type="file" name="video_file" accept=".mp4,.webm,.ogg"></div>
      <div><label><?= h(tr('Подпись к видео', 'Video caption')) ?></label><input name="video_alt" value="<?= h((string) ($editLectureVideo['video_alt'] ?? '')) ?>"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
  <p class="muted"><?= h(tr('Предпросмотр вкладок:', 'Preview tabs:')) ?> <?php foreach ($lectureVideos as $v): ?><span style="display:inline-block;padding:2px 8px;border:1px solid #ccc;border-radius:14px;margin-right:6px"><?= h(strtoupper((string) $v['language_code'])) ?></span><?php endforeach; ?></p>
  </div>
</details>

<details class="module-section card" open>
  <summary><?= h(tr('Видео презентации', 'Presentation Videos')) ?></summary>
  <div class="module-section__body">
  <div class="kant-section-head">
    <h3><?= h(tr('Список видео презентаций', 'Presentation videos list')) ?></h3>
    <button type="button" class="btn" data-toggle-form="presentation-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <div class="filter-row">
    <label for="presentation-filter"><strong><?= h(tr('Фильтр по языку:', 'Filter by language:')) ?></strong></label>
    <input id="presentation-filter" type="text" placeholder="<?= h(tr('например: ru, en', 'e.g. ru, en')) ?>">
  </div>
  <div class="table-scroll">
  <table id="presentation-table"><thead><tr><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Language')) ?></th><th><?= h(tr('Видео (ссылка/файл)', 'Video (link/file)')) ?></th><th><?= h(tr('Подпись к видео', 'Video caption')) ?></th><th><?= h(tr('Действия', 'Actions')) ?></th></tr></thead><tbody>
    <?php foreach ($presentationVideos as $video): ?>
      <tr data-language-code="<?= h(strtolower((string) $video['language_code'])) ?>">
        <td><?= h((string) $video['sort_order']) ?></td><td><?= h((string) $video['language_code']) ?></td><td><?= h((string) $video['video_url']) ?></td><td><?= h((string) $video['video_alt']) ?></td>
        <td class="actions compact-inputs">
          <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>&presentation_video=<?= h((string) $video['id']) ?>"><?= h(tr('Изменить', 'Edit')) ?></a>
          <form method="post" onsubmit="return confirm('<?= h(tr('Удалить видео презентации?', 'Delete presentation video?')) ?>')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_presentation_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>"><button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="presentation-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="<?= h($editPresentationVideo ? 'update_presentation_video' : 'add_presentation_video') ?>"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <?php if ($editPresentationVideo): ?><input type="hidden" name="video_id" value="<?= h((string) $editPresentationVideo['id']) ?>"><?php endif; ?>
    <div class="grid">
      <div><label><?= h(tr('Код языка', 'Language code')) ?></label><input name="video_language_code" placeholder="en / ru / arm" required pattern="[A-Za-z]{2,5}" value="<?= h((string) ($editPresentationVideo['language_code'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Ссылка на видео (embed) или путь к файлу', 'Video URL (embed) or file path')) ?></label><input name="video_url" value="<?= h((string) ($editPresentationVideo['video_url'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить видеофайл', 'Upload video file')) ?></label><input type="file" name="video_file" accept=".mp4,.webm,.ogg"></div>
      <div><label><?= h(tr('Подпись к видео', 'Video caption')) ?></label><input name="video_alt" value="<?= h((string) ($editPresentationVideo['video_alt'] ?? '')) ?>"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
  <p class="muted"><?= h(tr('Предпросмотр вкладок:', 'Preview tabs:')) ?> <?php foreach ($presentationVideos as $v): ?><span style="display:inline-block;padding:2px 8px;border:1px solid #ccc;border-radius:14px;margin-right:6px"><?= h(strtoupper((string) $v['language_code'])) ?></span><?php endforeach; ?></p>
  </div>
</details>

<details class="module-section card">
  <summary><?= h(tr('Транскрипции (для этого модуля)', 'Transcripts (for this module)')) ?></summary>
  <div class="module-section__body">
  <div class="kant-section-head">
    <h3><?= h(tr('Список транскрипций', 'Transcripts list')) ?></h3>
    <button type="button" class="btn" data-toggle-form="transcript-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <table><thead><tr><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Language')) ?></th><th><?= h(tr('Файл', 'File')) ?></th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead><tbody>
  <?php foreach ($transcripts as $t): ?>
    <tr><td><?= h((string) $t['sort_order']) ?></td><td><?= h(strtoupper((string) ($t['display_name'] ?? ''))) ?></td><td><?= h((string) $t['file_path']) ?></td><td><form method="post" onsubmit="return confirm('<?= h(tr('Удалить транскрипцию?', 'Delete transcript?')) ?>')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="transcript_id" value="<?= h((string) $t['id']) ?>"><button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="transcript-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid"><div><label><?= h(tr('Загрузить файл транскрипции', 'Upload transcript file')) ?></label><input type="file" name="transcript_file" accept=".pdf,.doc,.docx,.txt" required></div><div><label><?= h(tr('Язык', 'Language')) ?></label><input name="language_code" placeholder="ru / en" required pattern="[A-Za-z]{2,5}"></div></div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
  </div>
</details>

<details class="module-section card">
  <summary><?= h(tr('Материалы для чтения (для этого модуля)', 'Readings (for this module)')) ?></summary>
  <div class="module-section__body">
  <div class="kant-section-head">
    <h3><?= h(tr('Список материалов', 'Readings list')) ?></h3>
    <button type="button" class="btn" data-toggle-form="reading-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <table><thead><tr><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Связанная публикация', 'Linked publication')) ?></th><th><?= h(tr('Цель (файл/ссылка)', 'Target (file/link)')) ?></th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead><tbody>
  <?php foreach ($readings as $r): ?>
    <tr><td><?= h((string) $r['sort_order']) ?></td><td><?= h((string) ($r['linked_publication_id'] ?: '-')) ?></td><td><?= h((string) ($r['custom_file_path'] ?: $r['custom_url'])) ?></td><td><form method="post" onsubmit="return confirm('<?= h(tr('Удалить материал?', 'Delete reading?')) ?>')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_reading"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="reading_id" value="<?= h((string) $r['id']) ?>"><button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="reading-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_reading"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid">
      <div><label>Публикация из базы (необязательно)</label><select name="linked_publication_id" id="linked-publication-select"><option value="">Не выбрано</option><?php foreach ($publicationOptions as $p): ?><option value="<?= h((string) $p['id']) ?>">#<?= h((string) $p['id']) ?></option><?php endforeach; ?></select></div>
      <div><label><?= h(tr('Ссылка на материал', 'Reading URL')) ?></label><input name="custom_url" id="reading-custom-url"></div>
      <div><label><?= h(tr('Путь к файлу материала (если без загрузки)', 'Reading file path (if no upload)')) ?></label><input name="custom_file_path" id="reading-custom-file-path"></div>
      <div><label><?= h(tr('Загрузить файл материала', 'Upload reading file')) ?></label><input type="file" name="custom_file_upload" id="reading-custom-file-upload" accept=".pdf,.doc,.docx,.txt"></div>
      <div><label><?= h(tr('Путь к обложке (если без загрузки)', 'Cover image path (if no upload)')) ?></label><input name="custom_cover_image_path" id="reading-custom-cover-path"></div>
      <div><label><?= h(tr('Загрузить изображение обложки', 'Upload cover image')) ?></label><input type="file" name="custom_cover_upload" id="reading-custom-cover-upload" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
    </div>
    <div id="reading-custom-titles">
      <?php foreach ($locales as $locale): ?><div style="margin-top:8px"><label><?= h(tr('Заголовок (', 'Title (')) ?><?= h(strtoupper($locale)) ?>)</label><input name="custom_title_<?= h($locale) ?>"></div><?php endforeach; ?>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
  </div>
</details>
<?php endif; ?>
<?php endif; ?>

<script>
function applyLanguageFilter(inputId, tableId) {
  var input = document.getElementById(inputId);
  var table = document.getElementById(tableId);
  if (!input || !table) return;
  var rows = table.querySelectorAll('tbody tr[data-language-code]');
  var run = function () {
    var q = String(input.value || '').trim().toLowerCase();
    rows.forEach(function (row) {
      var lang = row.getAttribute('data-language-code') || '';
      row.style.display = q === '' || lang.indexOf(q) !== -1 ? '' : 'none';
    });
  };
  input.addEventListener('input', run);
}

applyLanguageFilter('lecture-filter', 'lecture-table');
applyLanguageFilter('presentation-filter', 'presentation-table');

document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var formId = btn.getAttribute('data-toggle-form');
    var form = formId ? document.getElementById(formId) : null;
    if (!form) return;
    var isOpen = !form.hasAttribute('hidden');
    if (isOpen) {
      form.setAttribute('hidden', 'hidden');
    } else {
      form.removeAttribute('hidden');
    }
    btn.textContent = isOpen ? <?= json_encode(h(tr('Добавить +', 'Add +'))) ?> : <?= json_encode(h(tr('Скрыть форму', 'Hide form'))) ?>;
  });
});

document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
  var formId = btn.getAttribute('data-toggle-form');
  var form = formId ? document.getElementById(formId) : null;
  if (form) form.setAttribute('hidden', 'hidden');
});
<?php if ($editLectureVideo): ?>
(function () {
  var form = document.getElementById('lecture-add-form');
  var btn = document.querySelector('[data-toggle-form="lecture-add-form"]');
  if (form) form.removeAttribute('hidden');
  if (btn) btn.textContent = <?= json_encode(h(tr('Скрыть форму', 'Hide form'))) ?>;
})();
<?php endif; ?>
<?php if ($editPresentationVideo): ?>
(function () {
  var form = document.getElementById('presentation-add-form');
  var btn = document.querySelector('[data-toggle-form="presentation-add-form"]');
  if (form) form.removeAttribute('hidden');
  if (btn) btn.textContent = <?= json_encode(h(tr('Скрыть форму', 'Hide form'))) ?>;
})();
<?php endif; ?>

var drawer = document.querySelector('.kant-drawer');
if (drawer) {
  var savedDrawerScroll = sessionStorage.getItem('kantModulesDrawerScrollTop');
  if (savedDrawerScroll !== null) {
    drawer.scrollTop = parseInt(savedDrawerScroll, 10) || 0;
    sessionStorage.removeItem('kantModulesDrawerScrollTop');
  }
  drawer.querySelectorAll('form[method="post"]').forEach(function (form) {
    form.addEventListener('submit', function () {
      sessionStorage.setItem('kantModulesDrawerScrollTop', String(drawer.scrollTop || 0));
    });
  });
}

var linkedPublicationSelect = document.getElementById('linked-publication-select');
function applyReadingMode() {
  if (!linkedPublicationSelect) return;
  var linked = linkedPublicationSelect.value !== '';
  ['reading-custom-url', 'reading-custom-file-path', 'reading-custom-file-upload', 'reading-custom-cover-path', 'reading-custom-cover-upload'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.disabled = linked;
  });
  var titlesWrap = document.getElementById('reading-custom-titles');
  if (titlesWrap) {
    titlesWrap.style.opacity = linked ? '0.45' : '1';
    titlesWrap.querySelectorAll('input').forEach(function (input) {
      input.disabled = linked;
    });
  }
}
if (linkedPublicationSelect) {
  linkedPublicationSelect.addEventListener('change', applyReadingMode);
  applyReadingMode();
}

function moduleSlugify(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

var slugInput = document.querySelector('input[name="slug"]');
var moduleNumberInput = document.querySelector('input[name="sort_order"]');
var moduleTitleInputs = document.querySelectorAll('input[name^="title_"]');
function refreshModuleSlug() {
  if (!slugInput || !moduleNumberInput) return;
  var moduleNumber = String(moduleNumberInput.value || '').trim();
  var title = '';
  moduleTitleInputs.forEach(function (input) {
    if (!title && String(input.value || '').trim() !== '') {
      title = String(input.value || '').trim();
    }
  });
  var combined = [moduleNumber, title].filter(Boolean).join(' ');
  var slug = moduleSlugify(combined);
  slugInput.value = slug || ('module-' + (moduleNumber || '1'));
}
if (slugInput && moduleNumberInput) {
  moduleNumberInput.addEventListener('input', refreshModuleSlug);
  moduleTitleInputs.forEach(function (input) {
    input.addEventListener('input', refreshModuleSlug);
  });
  refreshModuleSlug();
}
</script>
<?php
admin_footer();
