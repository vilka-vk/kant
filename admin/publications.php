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
$tab = (string) ($_GET['tab'] ?? 'publications');
if (!in_array($tab, ['publications', 'types'], true)) {
    $tab = 'publications';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
    $action = (string) ($_POST['action'] ?? 'save_publication');
    if ($action === 'reorder_publications') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            $order = 1;
            $stmt = $pdo->prepare('UPDATE publications SET display_order = :display_order WHERE id = :id');
            foreach ($ids as $id) {
                $stmt->execute(['display_order' => $order++, 'id' => (int) $id]);
            }
        }
        redirect('/admin/publications.php?tab=publications');
    }
    if ($action === 'reorder_publication_types') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            $order = 1;
            $stmt = $pdo->prepare('UPDATE publication_types SET sort_order = :sort_order WHERE id = :id');
            foreach ($ids as $id) {
                $stmt->execute(['sort_order' => $order++, 'id' => (int) $id]);
            }
        }
        redirect('/admin/publications.php?tab=types');
    }
    if ($action === 'save_publication_type') {
        $typeId = (int) ($_POST['type_id'] ?? 0);
        $slug = trim((string) ($_POST['type_slug'] ?? ''));
        $sort = (int) ($_POST['type_sort_order'] ?? 0);
        if ($typeId > 0) {
            $stmt = $pdo->prepare('UPDATE publication_types SET slug=:slug, sort_order=:sort_order WHERE id=:id');
            $stmt->execute(['id' => $typeId, 'slug' => $slug, 'sort_order' => $sort]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO publication_types (slug, sort_order) VALUES (:slug, :sort_order)');
            $stmt->execute(['slug' => $slug, 'sort_order' => $sort]);
            $typeId = (int) $pdo->lastInsertId();
        }
        foreach ($locales as $locale) {
            $name = trim((string) ($_POST['type_name_' . $locale] ?? ''));
            $stmt = $pdo->prepare('INSERT INTO publication_types_translations (publication_type_id, locale, name)
              VALUES (:id, :locale, :name) ON DUPLICATE KEY UPDATE name = VALUES(name)');
            $stmt->execute(['id' => $typeId, 'locale' => $locale, 'name' => $name !== '' ? $name : '[empty]']);
        }
        redirect('/admin/publications.php?tab=types&edit_type=' . $typeId . '&saved_type=1');
    }

    if ($action === 'save_publications_page_hero') {
        $heroRow = $pdo->prepare('SELECT id, background_image_path, subtitle_enabled FROM hero_sections WHERE page_key = :page_key LIMIT 1');
        $heroRow->execute(['page_key' => 'publications']);
        $hero = $heroRow->fetch() ?: null;
        $heroBg = trim((string) ($hero['background_image_path'] ?? ''));
        $heroBg = str_replace('\\', '/', $heroBg);
        $uploadsPos = stripos($heroBg, '/uploads/');
        if ($uploadsPos !== false) {
            $heroBg = substr($heroBg, $uploadsPos);
        } elseif ($heroBg !== '' && !preg_match('#^([a-z]+:)?//#i', $heroBg) && !str_starts_with($heroBg, '/')) {
            $heroBg = '/' . $heroBg;
        }
        try {
            $uploadedHero = upload_public_file('hero_publications_background_file', 'hero', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
            if ($uploadedHero) {
                $heroBg = $uploadedHero;
            }
        } catch (Throwable $e) {
            redirect('/admin/publications.php?error=' . urlencode($e->getMessage()));
        }
        if ($hero && (int) $hero['id'] > 0) {
            $heroId = (int) $hero['id'];
            $pdo->prepare('UPDATE hero_sections SET subtitle_enabled = :subtitle_enabled, background_image_path = :background_image_path WHERE id = :id')
                ->execute([
                    'id' => $heroId,
                    'subtitle_enabled' => (int) ($_POST['hero_publications_subtitle_enabled'] ?? 1) > 0 ? 1 : 0,
                    'background_image_path' => $heroBg,
                ]);
        } else {
            $pdo->prepare('INSERT INTO hero_sections (page_key, subtitle_enabled, background_image_path) VALUES (:page_key, :subtitle_enabled, :background_image_path)')
                ->execute([
                    'page_key' => 'publications',
                    'subtitle_enabled' => (int) ($_POST['hero_publications_subtitle_enabled'] ?? 1) > 0 ? 1 : 0,
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
                    'title' => $locale === 'ru' ? 'Публикации' : 'Publications',
                    'subtitle' => trim((string) ($_POST['hero_publications_subtitle_' . $locale] ?? '')),
                ]);
        }
        redirect('/admin/publications.php?tab=publications&saved_hero=1');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $existingPublication = [];
    if ($id > 0) {
        $existingPublicationStmt = $pdo->prepare('SELECT file_path, external_url, cover_image_path FROM publications WHERE id = :id LIMIT 1');
        $existingPublicationStmt->execute(['id' => $id]);
        $existingPublication = $existingPublicationStmt->fetch() ?: [];
    }
    $file = trim((string) ($existingPublication['file_path'] ?? ''));
    $url = trim((string) ($_POST['external_url'] ?? ($existingPublication['external_url'] ?? '')));
    $cover = trim((string) ($existingPublication['cover_image_path'] ?? ''));
    try {
        $uploadedFile = upload_public_file('file_upload', 'publications/files', ['pdf', 'doc', 'docx']);
        if ($uploadedFile) {
            $file = $uploadedFile;
            $url = '';
        }
        $uploadedCover = upload_public_file('cover_upload', 'publications/covers', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
        if ($uploadedCover) {
            $cover = $uploadedCover;
        }
    } catch (Throwable $e) {
        redirect('/admin/publications.php?tab=publications&error=' . urlencode($e->getMessage()));
    }
    if (($file === '' && $url === '') || ($file !== '' && $url !== '')) {
        redirect('/admin/publications.php?tab=publications&error=xor');
    }
    $payload = [
        'publication_type_id' => (int) ($_POST['publication_type_id'] ?? 0),
        'cover_image_path' => $cover,
        'file_path' => $file,
        'external_url' => $url,
        'published_at' => (string) ($_POST['published_at'] ?? date('Y-m-d H:i:s')),
        'display_order' => (int) ($_POST['display_order'] ?? 0),
    ];
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE publications SET publication_type_id=:publication_type_id, cover_image_path=:cover_image_path,
          file_path=:file_path, external_url=:external_url, published_at=:published_at, display_order=:display_order WHERE id=:id');
        $stmt->execute($payload + ['id' => $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO publications (publication_type_id, cover_image_path, file_path, external_url, published_at, display_order)
          VALUES (:publication_type_id, :cover_image_path, :file_path, :external_url, :published_at, :display_order)');
        $stmt->execute($payload);
        $id = (int) $pdo->lastInsertId();
    }
    foreach ($locales as $locale) {
        $stmt = $pdo->prepare('INSERT INTO publications_translations (publication_id, locale, title)
          VALUES (:id, :locale, :title)
          ON DUPLICATE KEY UPDATE title = VALUES(title)');
        $stmt->execute([
            'id' => $id,
            'locale' => $locale,
            'title' => trim((string) ($_POST['title_' . $locale] ?? '[empty]')),
        ]);
    }
    redirect('/admin/publications.php?tab=publications&edit=' . $id . '&saved=1');
}

$typesStmt = $pdo->prepare('SELECT pt.id, pt.slug, COALESCE(ptt.name, pt.slug) AS localized_name
  FROM publication_types pt
  LEFT JOIN publication_types_translations ptt
    ON ptt.publication_type_id = pt.id AND ptt.locale = :locale
  ORDER BY pt.sort_order ASC, pt.id ASC');
$typesStmt->execute(['locale' => admin_locale()]);
$types = $typesStmt->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM publications WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, title FROM publications_translations WHERE publication_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr;
    }
}
$heroPublicationsStmt = $pdo->prepare('SELECT * FROM hero_sections WHERE page_key = :page_key LIMIT 1');
$heroPublicationsStmt->execute(['page_key' => 'publications']);
$heroPublications = $heroPublicationsStmt->fetch() ?: [];
$heroPublicationsTrRows = [];
if (!empty($heroPublications['id'])) {
    $heroTrStmt = $pdo->prepare('SELECT locale, title, subtitle FROM hero_sections_translations WHERE hero_section_id = :hero_section_id');
    $heroTrStmt->execute(['hero_section_id' => (int) $heroPublications['id']]);
    foreach ($heroTrStmt->fetchAll() as $row) {
        $heroPublicationsTrRows[$row['locale']] = $row;
    }
}
$selectedTypeId = (int) ($_GET['type_id'] ?? 0);
$rowsStmt = $pdo->prepare('SELECT p.id, p.display_order, p.published_at, p.file_path, p.external_url, p.cover_image_path, pt.slug AS type_slug,
    COALESCE(ptt.name, pt.slug) AS type_name,
    COALESCE(
      NULLIF(ptr_locale.title, \'\'),
      (
        SELECT ptr_any.title
        FROM publications_translations ptr_any
        WHERE ptr_any.publication_id = p.id
          AND TRIM(COALESCE(ptr_any.title, \'\')) <> \'\'
        ORDER BY ptr_any.locale ASC
        LIMIT 1
      ),
      \'\'
    ) AS title
  FROM publications p
  LEFT JOIN publication_types pt ON pt.id = p.publication_type_id
  LEFT JOIN publication_types_translations ptt ON ptt.publication_type_id = pt.id AND ptt.locale = :types_locale
  LEFT JOIN publications_translations ptr_locale ON ptr_locale.publication_id = p.id AND ptr_locale.locale = :titles_locale
  WHERE (:type_id_filter = 0 OR p.publication_type_id = :type_id_value)
  ORDER BY p.display_order ASC, p.id ASC');
$rowsStmt->execute([
    'types_locale' => admin_locale(),
    'titles_locale' => admin_locale(),
    'type_id_filter' => $selectedTypeId,
    'type_id_value' => $selectedTypeId,
]);
$rows = $rowsStmt->fetchAll();
$typeEditId = (int) ($_GET['edit_type'] ?? 0);
$typeEdit = null;
$typeTrMap = [];
if ($typeEditId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM publication_types WHERE id = :id');
    $stmt->execute(['id' => $typeEditId]);
    $typeEdit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, name FROM publication_types_translations WHERE publication_type_id = :id');
    $trs->execute(['id' => $typeEditId]);
    foreach ($trs->fetchAll() as $r) {
        $typeTrMap[$r['locale']] = $r['name'];
    }
}
$typeRowsStmt = $pdo->prepare('SELECT pt.*, COALESCE(ptt.name, \'\') AS localized_name
  FROM publication_types pt
  LEFT JOIN publication_types_translations ptt
    ON ptt.publication_type_id = pt.id AND ptt.locale = :locale
  ORDER BY pt.sort_order ASC, pt.id ASC');
$typeRowsStmt->execute(['locale' => admin_locale()]);
$typeRows = $typeRowsStmt->fetchAll();
$isPublicationFormOpen = $tab === 'publications' && ($edit || (string) ($_GET['form'] ?? '') === '1');
$isTypeFormOpen = $tab === 'types' && ($typeEdit || (string) ($_GET['form'] ?? '') === '1');

admin_header(tr('Публикации', 'Publications'));
?>
<div class="card">
  <div class="actions">
    <a class="kant-tab <?= $tab === 'publications' ? 'is-active' : '' ?>" href="/admin/publications.php?tab=publications"><?= h(tr('Публикации', 'Publications')) ?></a>
    <a class="kant-tab <?= $tab === 'types' ? 'is-active' : '' ?>" href="/admin/publications.php?tab=types"><?= h(tr('Типы публикаций', 'Publication types')) ?></a>
  </div>
</div>

<?php if ($tab === 'publications'): ?>
<div class="card">
  <h2><?= h(tr('Hero блока "Публикации"', 'Publications hero block')) ?></h2>
  <?php if (!empty($_GET['saved_hero'])): ?><p class="ok"><?= h(tr('Hero сохранен.', 'Hero saved.')) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_publications_page_hero">
    <div class="grid">
      <div><label><?= h(tr('Фон hero (путь)', 'Hero background (path)')) ?></label><input value="<?= h((string) ($heroPublications['background_image_path'] ?? '')) ?>" disabled></div>
      <div><label><?= h(tr('Загрузить фон hero', 'Upload hero background')) ?></label><input type="file" name="hero_publications_background_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Показывать subtitle', 'Show subtitle')) ?></label><select name="hero_publications_subtitle_enabled"><option value="1" <?= ((int) ($heroPublications['subtitle_enabled'] ?? 1) === 1) ? 'selected' : '' ?>><?= h(tr('Да', 'Yes')) ?></option><option value="0" <?= ((int) ($heroPublications['subtitle_enabled'] ?? 1) === 0) ? 'selected' : '' ?>><?= h(tr('Нет', 'No')) ?></option></select></div>
    </div>
    <hr style="margin:12px 0">
    <?php
      $heroLeftLocale = $locales[0] ?? 'ru';
      $heroRightLocale = $locales[1] ?? ($locales[0] ?? 'en');
    ?>
    <div class="grid" style="margin-bottom:8px">
      <div><label>Hero subtitle (<?= h(strtoupper($heroLeftLocale)) ?>)</label><input name="hero_publications_subtitle_<?= h($heroLeftLocale) ?>" value="<?= h((string) ($heroPublicationsTrRows[$heroLeftLocale]['subtitle'] ?? '')) ?>"></div>
      <div><label>Hero subtitle (<?= h(strtoupper($heroRightLocale)) ?>)</label><input name="hero_publications_subtitle_<?= h($heroRightLocale) ?>" value="<?= h((string) ($heroPublicationsTrRows[$heroRightLocale]['subtitle'] ?? '')) ?>"></div>
    </div>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить hero для страницы публикаций', 'Save publications hero')) ?></button></div>
  </form>
</div>

<div class="card">
  <div class="kant-section-head">
    <h1><?= h(tr('Публикации', 'Publications')) ?></h1>
    <a class="btn" href="/admin/publications.php?tab=publications&form=1"><?= h(tr('Добавить +', 'Add +')) ?></a>
  </div>
  <form method="get" class="grid" style="margin-bottom: 12px;">
    <input type="hidden" name="tab" value="publications">
    <div>
      <label><?= h(tr('Фильтр по типу', 'Filter by type')) ?></label>
      <select name="type_id" onchange="this.form.submit()">
        <option value="0"><?= h(tr('Все типы', 'All types')) ?></option>
        <?php foreach ($types as $t): ?>
          <option value="<?= h((string) $t['id']) ?>" <?= $selectedTypeId === (int) $t['id'] ? 'selected' : '' ?>><?= h((string) $t['localized_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'xor'): ?><p class="err"><?= h(tr('Нужно указать только одно: путь к файлу или внешнюю ссылку.', 'Exactly one of file path or external URL is required.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] !== 'xor'): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <table style="table-layout: fixed; width: 100%;">
    <thead><tr><th class="drag-col"></th><th style="width: 84px;"><?= h(tr('Порядок', 'Order')) ?></th><th style="width: 64px;">ID</th><th style="width: 120px;"><?= h(tr('Обложка', 'Cover')) ?></th><th style="width: 180px;"><?= h(tr('Тип', 'Type')) ?></th><th><?= h(tr('Название', 'Name')) ?></th><th style="width: 180px;"><?= h(tr('Дата', 'Date')) ?></th><th style="width: 90px; text-align: center;"><?= h(tr('Цель', 'Target')) ?></th><th style="width: 150px;"><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody id="publications-sortable">
    <?php foreach ($rows as $r): ?>
      <tr data-id="<?= h((string) $r['id']) ?>">
        <td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td>
        <td><?= h((string) $r['display_order']) ?></td>
        <td><?= h((string) $r['id']) ?></td>
        <td>
          <?php
            $coverPreview = (string) ($r['cover_image_path'] ?? '');
            if ($coverPreview !== '' && !preg_match('#^([a-z]+:)?//#i', $coverPreview) && !str_starts_with($coverPreview, '/')) {
                $coverPreview = '/' . $coverPreview;
            }
          ?>
          <img class="table-preview" src="<?= h($coverPreview !== '' ? $coverPreview : '/assets/images/publication-3.svg') ?>" alt="<?= h(tr('Обложка публикации', 'Publication cover')) ?>">
        </td>
        <td><?= h((string) $r['type_name']) ?></td>
        <td style="width: auto;"><?= h((string) $r['title']) ?></td>
        <td><?= h((string) $r['published_at']) ?></td>
        <td style="text-align: center;">
          <?php
            $targetUrl = (string) ($r['file_path'] !== '' ? $r['file_path'] : (string) $r['external_url']);
            if ($targetUrl !== '' && !preg_match('/^([a-z]+:)?\/\//i', $targetUrl) && strpos($targetUrl, '/') !== 0) {
                $targetUrl = '/' . $targetUrl;
            }
          ?>
          <?php if ($targetUrl !== ''): ?>
            <a class="btn btn-secondary" style="padding: 6px 10px;" href="<?= h($targetUrl) ?>" target="_blank" rel="noopener noreferrer" title="<?= h(tr('Открыть ссылку', 'Open link')) ?>">↗</a>
          <?php endif; ?>
        </td>
        <td><a class="btn btn-secondary" href="/admin/publications.php?tab=publications&form=1&edit=<?= h((string) $r['id']) ?>"><?= h(tr('Редактировать', 'Edit')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" id="publications-reorder-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="reorder_publications">
    <div id="publications-reorder-ids"></div>
  </form>
</div>

<?php if ($isPublicationFormOpen): ?>
<aside class="kant-drawer" aria-label="Publication form drawer">
  <div class="kant-drawer-actions">
    <h2><?= h($edit ? tr('Редактирование публикации', 'Edit publication') : tr('Добавление публикации', 'Add publication')) ?></h2>
    <a class="btn btn-secondary" href="/admin/publications.php?tab=publications" data-close-drawer-publications><?= h(tr('Закрыть', 'Close')) ?></a>
  </div>
<div class="card">
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'xor'): ?><p class="err"><?= h(tr('Нужно указать только одно: путь к файлу или внешнюю ссылку.', 'Exactly one of file path or external URL is required.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] !== 'xor'): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label><?= h(tr('Тип', 'Type')) ?></label>
        <select name="publication_type_id" required>
          <option value=""><?= h(tr('Выберите', 'Select')) ?></option>
          <?php foreach ($types as $t): ?>
            <option value="<?= h((string) $t['id']) ?>" <?= ((int) ($edit['publication_type_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>><?= h($t['slug']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label><?= h(tr('Дата публикации', 'Published at')) ?></label><input name="published_at" value="<?= h((string) ($edit['published_at'] ?? date('Y-m-d 00:00:00'))) ?>"></div>
      <div><label><?= h(tr('Порядок', 'Order')) ?></label><input type="number" name="display_order" min="1" value="<?= h((string) ($edit['display_order'] ?? (count($rows) + 1))) ?>"></div>
      <div><label><?= h(tr('Путь к обложке', 'Cover image path')) ?></label><input value="<?= h((string) ($edit['cover_image_path'] ?? '')) ?>" disabled></div>
      <div><label><?= h(tr('Загрузить обложку', 'Upload cover image')) ?></label><input type="file" name="cover_upload" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Путь к файлу', 'File path')) ?></label><input value="<?= h((string) ($edit['file_path'] ?? '')) ?>" disabled></div>
      <div><label><?= h(tr('Загрузить файл', 'Upload file')) ?></label><input type="file" name="file_upload" accept=".pdf,.doc,.docx"></div>
      <div><label><?= h(tr('Внешняя ссылка', 'External URL')) ?></label><input name="external_url" value="<?= h((string) ($edit['external_url'] ?? '')) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px"><label>Title (<?= h(strtoupper($locale)) ?>)</label><input name="title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['title'] ?? '')) ?>"></div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? h(tr('Обновить публикацию', 'Update publication')) : h(tr('Создать публикацию', 'Create publication')) ?></button>
      <a class="btn btn-secondary" href="/admin/publications.php?tab=publications&form=1"><?= h(tr('Новая', 'New')) ?></a>
    </div>
  </form>
</div>
</aside>
<?php endif; ?>
<?php else: ?>
<div class="card">
  <div class="kant-section-head">
    <h1><?= h(tr('Типы публикаций', 'Publication types')) ?></h1>
    <a class="btn" href="/admin/publications.php?tab=types&form=1"><?= h(tr('Добавить +', 'Add +')) ?></a>
  </div>
  <?php if (!empty($_GET['saved_type'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <table>
    <thead><tr><th class="drag-col"></th><th><?= h(tr('Порядок', 'Order')) ?></th><th>ID</th><th><?= h(tr('Название', 'Name')) ?></th><th>Slug</th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody id="publication-types-sortable">
    <?php foreach ($typeRows as $row): ?>
      <tr data-id="<?= h((string) $row['id']) ?>">
        <td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td>
        <td><?= h((string) $row['sort_order']) ?></td>
        <td><?= h((string) $row['id']) ?></td>
        <td><?= h((string) $row['localized_name']) ?></td>
        <td><?= h($row['slug']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/publications.php?tab=types&form=1&edit_type=<?= h((string) $row['id']) ?>"><?= h(tr('Редактировать', 'Edit')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" id="publication-types-reorder-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="reorder_publication_types">
    <div id="publication-types-reorder-ids"></div>
  </form>
</div>

<?php if ($isTypeFormOpen): ?>
<aside class="kant-drawer" aria-label="Publication type form drawer">
  <div class="kant-drawer-actions">
    <h2><?= h($typeEdit ? tr('Редактирование типа публикации', 'Edit publication type') : tr('Добавление типа публикации', 'Add publication type')) ?></h2>
    <a class="btn btn-secondary" href="/admin/publications.php?tab=types" data-close-drawer-types><?= h(tr('Закрыть', 'Close')) ?></a>
  </div>
<div class="card">
  <?php if (!empty($_GET['saved_type'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_publication_type">
    <input type="hidden" name="type_id" value="<?= h((string) ($typeEdit['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label>Slug</label><input name="type_slug" required value="<?= h((string) ($typeEdit['slug'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Порядок сортировки', 'Sort order')) ?></label><input type="number" name="type_sort_order" value="<?= h((string) ($typeEdit['sort_order'] ?? 0)) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px">
        <label><?= h(tr('Название', 'Name')) ?> (<?= h(strtoupper($locale)) ?>)</label>
        <input name="type_name_<?= h($locale) ?>" value="<?= h((string) ($typeTrMap[$locale] ?? '')) ?>">
      </div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $typeEdit ? h(tr('Обновить тип', 'Update type')) : h(tr('Создать тип', 'Create type')) ?></button>
      <a class="btn btn-secondary" href="/admin/publications.php?tab=types&form=1"><?= h(tr('Новый', 'New')) ?></a>
    </div>
  </form>
</div>
</aside>
<?php endif; ?>
<?php endif; ?>

<div class="kant-confirm-overlay" id="publications-close-confirm">
  <div class="kant-confirm-modal">
    <h3><?= h(tr('Есть несохранённые изменения', 'Unsaved changes')) ?></h3>
    <p class="muted"><?= h(tr('Сохранить изменения перед закрытием формы?', 'Save changes before closing the form?')) ?></p>
    <div class="actions">
      <button type="button" id="publications-confirm-save"><?= h(tr('Сохранить', 'Save')) ?></button>
      <button type="button" class="btn btn-secondary" id="publications-confirm-discard"><?= h(tr('Не сохранять', 'Discard')) ?></button>
      <button type="button" class="btn btn-secondary" id="publications-confirm-cancel"><?= h(tr('Отмена', 'Cancel')) ?></button>
    </div>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
  if (typeof window.initKantDrawerCloseGuard === 'function') {
    window.initKantDrawerCloseGuard({
      formSelector: '.kant-drawer form',
      closeSelector: '[data-close-drawer-publications], [data-close-drawer-types]',
      overlaySelector: '#publications-close-confirm',
      saveSelector: '#publications-confirm-save',
      discardSelector: '#publications-confirm-discard',
      cancelSelector: '#publications-confirm-cancel',
      fallbackHref: '/admin/publications.php'
    });
  }
});

(function () {
  var scrollKey = 'kantPublicationsScrollY';
  var savedY = sessionStorage.getItem(scrollKey);
  if (savedY !== null) {
    window.scrollTo(0, parseInt(savedY, 10) || 0);
    sessionStorage.removeItem(scrollKey);
  }
  function initSortable(tbodyId, formId, idsWrapId) {
    var tbody = document.getElementById(tbodyId);
    var form = document.getElementById(formId);
    var idsWrap = document.getElementById(idsWrapId);
    if (!tbody || !form || !idsWrap) return;
    var dragged = null;
    tbody.querySelectorAll('tr[data-id]').forEach(function (row) {
      var handle = row.querySelector('.drag-handle');
      if (handle) {
        handle.addEventListener('dragstart', function () { dragged = row; });
      }
      row.addEventListener('dragover', function (e) { e.preventDefault(); });
      row.addEventListener('drop', function (e) {
        e.preventDefault();
        if (!dragged || dragged === row) return;
        tbody.insertBefore(dragged, row);
        idsWrap.innerHTML = '';
        tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = tr.getAttribute('data-id') || '';
          idsWrap.appendChild(input);
        });
        sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
        form.submit();
      });
    });
  }
  initSortable('publications-sortable', 'publications-reorder-form', 'publications-reorder-ids');
  initSortable('publication-types-sortable', 'publication-types-reorder-form', 'publication-types-reorder-ids');
})();
</script>
<?php admin_footer();
