<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
$pdo = db();
$locales = $config['app']['supported_locales'];
$languageCodePattern = '/^[a-z]{2,5}$/';

function assertLanguageCode(string $value, string $pattern): bool
{
    return (bool) preg_match($pattern, strtolower(trim($value)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $action = (string) ($_POST['action'] ?? '');
    $moduleId = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM modules WHERE id = :id')->execute(['id' => $moduleId]);
        redirect('/admin/modules.php');
    }

    if (in_array($action, ['add_lecture_video', 'update_lecture_video', 'add_presentation_video', 'update_presentation_video'], true)) {
        $languageCode = strtolower(trim((string) ($_POST['video_language_code'] ?? '')));
        if (!assertLanguageCode($languageCode, $languageCodePattern)) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=invalid_lang');
        }
        $videoId = (int) ($_POST['video_id'] ?? 0);
        $table = str_contains($action, 'lecture') ? 'module_lecture_videos' : 'module_presentation_videos';
        $payload = [
            'module_id' => $moduleId,
            'language_code' => $languageCode,
            'video_url' => trim((string) ($_POST['video_url'] ?? '')),
            'video_alt' => trim((string) ($_POST['video_alt'] ?? '')),
            'sort_order' => (int) ($_POST['video_sort_order'] ?? 0),
        ];
        if ($action === 'add_lecture_video' || $action === 'add_presentation_video') {
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
        $payload = [
            'module_id' => $moduleId,
            'file_path' => trim((string) ($_POST['file_path'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE module_transcripts SET file_path=:file_path, sort_order=:sort_order WHERE id=:id AND module_id=:module_id')
                ->execute($payload + ['id' => $id]);
        } else {
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
                    'display_name' => trim((string) ($_POST['display_name_' . $locale] ?? '[empty]')),
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
        if ($linked === 0 && (($customUrl === '' && $customFile === '') || ($customUrl !== '' && $customFile !== ''))) {
            redirect('/admin/modules.php?edit=' . $moduleId . '&error=reading_xor');
        }
        $payload = [
            'module_id' => $moduleId,
            'linked_publication_id' => $linked > 0 ? $linked : null,
            'custom_url' => $customUrl,
            'custom_file_path' => $customFile,
            'custom_cover_image_path' => trim((string) ($_POST['custom_cover_image_path'] ?? '')),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];
        if ($id > 0) {
            $pdo->prepare('UPDATE module_readings SET linked_publication_id=:linked_publication_id, custom_url=:custom_url, custom_file_path=:custom_file_path,
              custom_cover_image_path=:custom_cover_image_path, sort_order=:sort_order WHERE id=:id AND module_id=:module_id')
                ->execute($payload + ['id' => $id]);
        } else {
            $pdo->prepare('INSERT INTO module_readings (module_id, linked_publication_id, custom_url, custom_file_path, custom_cover_image_path, sort_order)
              VALUES (:module_id,:linked_publication_id,:custom_url,:custom_file_path,:custom_cover_image_path,:sort_order)')
                ->execute($payload);
            $id = (int) $pdo->lastInsertId();
        }
        foreach ($locales as $locale) {
            $pdo->prepare('INSERT INTO module_readings_translations (module_reading_id, locale, custom_title)
              VALUES (:id,:locale,:custom_title)
              ON DUPLICATE KEY UPDATE custom_title = VALUES(custom_title)')
                ->execute([
                    'id' => $id,
                    'locale' => $locale,
                    'custom_title' => trim((string) ($_POST['custom_title_' . $locale] ?? '')),
                ]);
        }
        redirect('/admin/modules.php?edit=' . $moduleId);
    }

    $id = $moduleId;
    $base = [
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'module_number' => (int) ($_POST['module_number'] ?? 0),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'languages' => trim((string) ($_POST['languages'] ?? '')),
        'formats' => trim((string) ($_POST['formats'] ?? '')),
        'list_duration_display' => trim((string) ($_POST['list_duration_display'] ?? '')),
        'hero_background_image_path' => trim((string) ($_POST['hero_background_image_path'] ?? '')),
        'presentation_file_path' => trim((string) ($_POST['presentation_file_path'] ?? '')),
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
        $pdo->prepare('INSERT INTO modules_translations (module_id, locale, title, short_description, hero_kicker, hero_subtitle, lecture_title, presentation_title, literature_html)
          VALUES (:module_id,:locale,:title,:short_description,:hero_kicker,:hero_subtitle,:lecture_title,:presentation_title,:literature_html)
          ON DUPLICATE KEY UPDATE title = VALUES(title), short_description = VALUES(short_description), hero_kicker = VALUES(hero_kicker),
          hero_subtitle = VALUES(hero_subtitle), lecture_title = VALUES(lecture_title), presentation_title = VALUES(presentation_title), literature_html = VALUES(literature_html)')
            ->execute([
                'module_id' => $id,
                'locale' => $locale,
                'title' => trim((string) ($_POST['title_' . $locale] ?? '[empty]')),
                'short_description' => trim((string) ($_POST['short_description_' . $locale] ?? '')),
                'hero_kicker' => trim((string) ($_POST['hero_kicker_' . $locale] ?? '')),
                'hero_subtitle' => trim((string) ($_POST['hero_subtitle_' . $locale] ?? '')),
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

    $presentationVideos = $pdo->prepare('SELECT * FROM module_presentation_videos WHERE module_id = :id ORDER BY sort_order ASC, id ASC');
    $presentationVideos->execute(['id' => $editId]);
    $presentationVideos = $presentationVideos->fetchAll();

    $transcripts = $pdo->prepare('SELECT mt.* FROM module_transcripts mt WHERE mt.module_id = :id ORDER BY mt.sort_order ASC, mt.id ASC');
    $transcripts->execute(['id' => $editId]);
    $transcripts = $transcripts->fetchAll();

    $readings = $pdo->prepare('SELECT mr.* FROM module_readings mr WHERE mr.module_id = :id ORDER BY mr.sort_order ASC, mr.id ASC');
    $readings->execute(['id' => $editId]);
    $readings = $readings->fetchAll();
}

$publicationOptions = $pdo->query('SELECT id FROM publications ORDER BY display_order ASC, id ASC')->fetchAll();
$rows = $pdo->query('SELECT id, slug, module_number, sort_order, languages FROM modules ORDER BY sort_order ASC, id ASC')->fetchAll();

admin_header('Modules');
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
<div class="card">
  <h1>Modules</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'invalid_lang'): ?><p class="err">Language code format is invalid. Use only letters, 2-5 chars (e.g. en, ru, arm).</p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'reading_xor'): ?><p class="err">For reading without linked publication, exactly one of URL or file path is required.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($editRow['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label>Slug</label><input name="slug" required value="<?= h((string) ($editRow['slug'] ?? '')) ?>"></div>
      <div><label>Module Number</label><input type="number" name="module_number" required value="<?= h((string) ($editRow['module_number'] ?? 1)) ?>"></div>
      <div><label>Sort Order</label><input type="number" name="sort_order" required value="<?= h((string) ($editRow['sort_order'] ?? 1)) ?>"></div>
      <div><label>Languages</label><input name="languages" required value="<?= h((string) ($editRow['languages'] ?? 'EN, RU')) ?>"></div>
      <div><label>Formats</label><input name="formats" value="<?= h((string) ($editRow['formats'] ?? '')) ?>"></div>
      <div><label>Duration</label><input name="list_duration_display" value="<?= h((string) ($editRow['list_duration_display'] ?? '')) ?>"></div>
      <div><label>Hero background image path</label><input name="hero_background_image_path" value="<?= h((string) ($editRow['hero_background_image_path'] ?? '')) ?>"></div>
      <div><label>Presentation file path (optional)</label><input name="presentation_file_path" value="<?= h((string) ($editRow['presentation_file_path'] ?? '')) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px"><label>Title (<?= h(strtoupper($locale)) ?>)</label><input name="title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['title'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Short Description (<?= h(strtoupper($locale)) ?>)</label><textarea rows="3" name="short_description_<?= h($locale) ?>"><?= h((string) ($trMap[$locale]['short_description'] ?? '')) ?></textarea></div>
      <div style="margin-bottom:12px"><label>Hero kicker (<?= h(strtoupper($locale)) ?>)</label><input name="hero_kicker_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['hero_kicker'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Hero subtitle (<?= h(strtoupper($locale)) ?>)</label><input name="hero_subtitle_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['hero_subtitle'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Lecture title (<?= h(strtoupper($locale)) ?>)</label><input name="lecture_title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['lecture_title'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Presentation title (<?= h(strtoupper($locale)) ?>)</label><input name="presentation_title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['presentation_title'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Literature text (WYSIWYG, <?= h(strtoupper($locale)) ?>)</label><textarea class="wysiwyg" rows="6" name="literature_html_<?= h($locale) ?>"><?= h((string) ($trMap[$locale]['literature_html'] ?? '')) ?></textarea></div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $editRow ? 'Update module' : 'Create module' ?></button>
      <a class="btn btn-secondary" href="/admin/modules.php">New</a>
    </div>
  </form>
</div>

<?php if ($editRow): ?>
<details class="module-section card" open>
  <summary>Lecture Videos</summary>
  <div class="module-section__body">
  <p class="inline-help">URL hint: use embed URL, e.g. https://www.youtube.com/embed/... or Vimeo player URL.</p>
  <form method="post" style="margin-bottom:12px" class="compact-inputs">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_lecture_video">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid">
      <div><label>language_code</label><input name="video_language_code" placeholder="en / ru / arm" required pattern="[A-Za-z]{2,5}"></div>
      <div><label>video_url</label><input name="video_url" required></div>
      <div><label>video_alt</label><input name="video_alt"></div>
      <div><label>sort_order</label><input type="number" name="video_sort_order" value="0"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit">Add video</button></div>
  </form>
  <div class="filter-row">
    <label for="lecture-filter"><strong>Filter language_code:</strong></label>
    <input id="lecture-filter" type="text" placeholder="e.g. ru, en">
  </div>
  <div class="table-scroll">
  <table id="lecture-table"><thead><tr><th>language_code</th><th>video_url</th><th>video_alt</th><th>sort_order</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($lectureVideos as $video): ?>
      <tr data-language-code="<?= h(strtolower((string) $video['language_code'])) ?>">
        <td><?= h((string) $video['language_code']) ?></td><td><?= h((string) $video['video_url']) ?></td><td><?= h((string) $video['video_alt']) ?></td><td><?= h((string) $video['sort_order']) ?></td>
        <td class="actions compact-inputs">
          <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_lecture_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>">
            <input name="video_language_code" value="<?= h((string) $video['language_code']) ?>" pattern="[A-Za-z]{2,5}" style="max-width:70px">
            <input name="video_url" value="<?= h((string) $video['video_url']) ?>" style="max-width:230px">
            <input name="video_alt" value="<?= h((string) $video['video_alt']) ?>" style="max-width:160px">
            <input name="video_sort_order" type="number" value="<?= h((string) $video['sort_order']) ?>" style="max-width:70px">
            <button type="submit">Edit</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete lecture video?')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_lecture_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>"><button type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <p class="muted">Preview tabs: <?php foreach ($lectureVideos as $v): ?><span style="display:inline-block;padding:2px 8px;border:1px solid #ccc;border-radius:14px;margin-right:6px"><?= h(strtoupper((string) $v['language_code'])) ?></span><?php endforeach; ?></p>
  </div>
</details>

<details class="module-section card" open>
  <summary>Presentation Videos</summary>
  <div class="module-section__body">
  <form method="post" style="margin-bottom:12px" class="compact-inputs">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="add_presentation_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid">
      <div><label>language_code</label><input name="video_language_code" placeholder="en / ru / arm" required pattern="[A-Za-z]{2,5}"></div>
      <div><label>video_url</label><input name="video_url" required></div>
      <div><label>video_alt</label><input name="video_alt"></div>
      <div><label>sort_order</label><input type="number" name="video_sort_order" value="0"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit">Add video</button></div>
  </form>
  <div class="filter-row">
    <label for="presentation-filter"><strong>Filter language_code:</strong></label>
    <input id="presentation-filter" type="text" placeholder="e.g. ru, en">
  </div>
  <div class="table-scroll">
  <table id="presentation-table"><thead><tr><th>language_code</th><th>video_url</th><th>video_alt</th><th>sort_order</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($presentationVideos as $video): ?>
      <tr data-language-code="<?= h(strtolower((string) $video['language_code'])) ?>">
        <td><?= h((string) $video['language_code']) ?></td><td><?= h((string) $video['video_url']) ?></td><td><?= h((string) $video['video_alt']) ?></td><td><?= h((string) $video['sort_order']) ?></td>
        <td class="actions compact-inputs">
          <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="update_presentation_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>">
            <input name="video_language_code" value="<?= h((string) $video['language_code']) ?>" pattern="[A-Za-z]{2,5}" style="max-width:70px">
            <input name="video_url" value="<?= h((string) $video['video_url']) ?>" style="max-width:230px">
            <input name="video_alt" value="<?= h((string) $video['video_alt']) ?>" style="max-width:160px">
            <input name="video_sort_order" type="number" value="<?= h((string) $video['sort_order']) ?>" style="max-width:70px">
            <button type="submit">Edit</button>
          </form>
          <form method="post" onsubmit="return confirm('Delete presentation video?')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_presentation_video"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>"><button type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <p class="muted">Preview tabs: <?php foreach ($presentationVideos as $v): ?><span style="display:inline-block;padding:2px 8px;border:1px solid #ccc;border-radius:14px;margin-right:6px"><?= h(strtoupper((string) $v['language_code'])) ?></span><?php endforeach; ?></p>
  </div>
</details>

<details class="module-section card">
  <summary>Transcripts (for this module)</summary>
  <div class="module-section__body">
  <form method="post" style="margin-bottom:12px" class="compact-inputs">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid"><div><label>file_path</label><input name="file_path" required></div><div><label>sort_order</label><input type="number" name="sort_order" value="0"></div></div>
    <?php foreach ($locales as $locale): ?><div style="margin-top:8px"><label>display_name (<?= h(strtoupper($locale)) ?>)</label><input name="display_name_<?= h($locale) ?>"></div><?php endforeach; ?>
    <div class="actions" style="margin-top:10px"><button type="submit">Add transcript</button></div>
  </form>
  <table><thead><tr><th>file_path</th><th>sort_order</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($transcripts as $t): ?>
    <tr><td><?= h((string) $t['file_path']) ?></td><td><?= h((string) $t['sort_order']) ?></td><td><form method="post" onsubmit="return confirm('Delete transcript?')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="transcript_id" value="<?= h((string) $t['id']) ?>"><button type="submit">Delete</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
</details>

<details class="module-section card">
  <summary>Readings (for this module)</summary>
  <div class="module-section__body">
  <form method="post" style="margin-bottom:12px" class="compact-inputs">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_reading"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div class="grid">
      <div><label>linked_publication_id (optional)</label><select name="linked_publication_id"><option value="">None</option><?php foreach ($publicationOptions as $p): ?><option value="<?= h((string) $p['id']) ?>">#<?= h((string) $p['id']) ?></option><?php endforeach; ?></select></div>
      <div><label>custom_url</label><input name="custom_url"></div>
      <div><label>custom_file_path</label><input name="custom_file_path"></div>
      <div><label>custom_cover_image_path</label><input name="custom_cover_image_path"></div>
      <div><label>sort_order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <?php foreach ($locales as $locale): ?><div style="margin-top:8px"><label>custom_title (<?= h(strtoupper($locale)) ?>)</label><input name="custom_title_<?= h($locale) ?>"></div><?php endforeach; ?>
    <div class="actions" style="margin-top:10px"><button type="submit">Add reading</button></div>
  </form>
  <table><thead><tr><th>linked_pub</th><th>target</th><th>sort_order</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($readings as $r): ?>
    <tr><td><?= h((string) ($r['linked_publication_id'] ?: '-')) ?></td><td><?= h((string) ($r['custom_file_path'] ?: $r['custom_url'])) ?></td><td><?= h((string) $r['sort_order']) ?></td><td><form method="post" onsubmit="return confirm('Delete reading?')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_reading"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="reading_id" value="<?= h((string) $r['id']) ?>"><button type="submit">Delete</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
</details>
<?php endif; ?>

<div class="card">
  <h2>Existing modules</h2>
  <table>
    <thead><tr><th>ID</th><th>Slug</th><th>Number</th><th>Order</th><th>Languages</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= h((string) $row['id']) ?></td>
        <td><?= h($row['slug']) ?></td>
        <td><?= h((string) $row['module_number']) ?></td>
        <td><?= h((string) $row['sort_order']) ?></td>
        <td><?= h($row['languages']) ?></td>
        <td class="actions">
          <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $row['id']) ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete module?')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h((string) $row['id']) ?>"><button type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
if (window.tinymce) {
  tinymce.init({ selector: '.wysiwyg', menubar: false, height: 180, plugins: 'link lists code', toolbar: 'undo redo | bold italic | bullist numlist | link | code' });
}

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
</script>
<?php
admin_footer();
