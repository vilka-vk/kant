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
        $heroBg = trim((string) ($_POST['hero_publications_background_image_path'] ?? ($hero['background_image_path'] ?? '')));
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
                    'title' => trim((string) ($_POST['hero_publications_title_' . $locale] ?? '')),
                    'subtitle' => trim((string) ($_POST['hero_publications_subtitle_' . $locale] ?? '')),
                ]);
        }
        redirect('/admin/publications.php?tab=publications&saved_hero=1');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $file = trim((string) ($_POST['file_path'] ?? ''));
    $url = trim((string) ($_POST['external_url'] ?? ''));
    $cover = trim((string) ($_POST['cover_image_path'] ?? ''));
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
        $stmt = $pdo->prepare('INSERT INTO publications_translations (publication_id, locale, title, description)
          VALUES (:id, :locale, :title, :description)
          ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)');
        $stmt->execute([
            'id' => $id,
            'locale' => $locale,
            'title' => trim((string) ($_POST['title_' . $locale] ?? '[empty]')),
            'description' => trim((string) ($_POST['description_' . $locale] ?? '')),
        ]);
    }
    redirect('/admin/publications.php?tab=publications&edit=' . $id . '&saved=1');
}

$types = $pdo->query('SELECT id, slug FROM publication_types ORDER BY sort_order ASC, id ASC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM publications WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, title, description FROM publications_translations WHERE publication_id = :id');
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
$rows = $pdo->query('SELECT p.id, p.display_order, p.published_at, p.file_path, p.external_url, pt.slug as type_slug
  FROM publications p LEFT JOIN publication_types pt ON pt.id = p.publication_type_id
  ORDER BY p.display_order ASC, p.published_at DESC, p.id ASC')->fetchAll();
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
$typeRows = $pdo->query('SELECT * FROM publication_types ORDER BY sort_order ASC, id ASC')->fetchAll();

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
      <div><label><?= h(tr('Фон hero (путь)', 'Hero background (path)')) ?></label><input name="hero_publications_background_image_path" value="<?= h((string) ($heroPublications['background_image_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить фон hero', 'Upload hero background')) ?></label><input type="file" name="hero_publications_background_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Показывать subtitle', 'Show subtitle')) ?></label><select name="hero_publications_subtitle_enabled"><option value="1" <?= ((int) ($heroPublications['subtitle_enabled'] ?? 1) === 1) ? 'selected' : '' ?>><?= h(tr('Да', 'Yes')) ?></option><option value="0" <?= ((int) ($heroPublications['subtitle_enabled'] ?? 1) === 0) ? 'selected' : '' ?>><?= h(tr('Нет', 'No')) ?></option></select></div>
    </div>
    <hr style="margin:12px 0">
    <?php foreach ($locales as $locale): ?>
      <div class="grid" style="margin-bottom:8px">
        <div><label>Hero title (<?= h(strtoupper($locale)) ?>)</label><input name="hero_publications_title_<?= h($locale) ?>" value="<?= h((string) ($heroPublicationsTrRows[$locale]['title'] ?? '')) ?>"></div>
        <div><label>Hero subtitle (<?= h(strtoupper($locale)) ?>)</label><input name="hero_publications_subtitle_<?= h($locale) ?>" value="<?= h((string) ($heroPublicationsTrRows[$locale]['subtitle'] ?? '')) ?>"></div>
      </div>
    <?php endforeach; ?>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить hero для страницы публикаций', 'Save publications hero')) ?></button></div>
  </form>
</div>

<div class="card">
  <h1><?= h(tr('Публикации', 'Publications')) ?></h1>
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
      <div><label><?= h(tr('Порядок отображения', 'Display order')) ?></label><input type="number" name="display_order" value="<?= h((string) ($edit['display_order'] ?? 0)) ?>"></div>
      <div><label><?= h(tr('Путь к обложке', 'Cover image path')) ?></label><input name="cover_image_path" value="<?= h((string) ($edit['cover_image_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить обложку', 'Upload cover image')) ?></label><input type="file" name="cover_upload" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label><?= h(tr('Путь к файлу', 'File path')) ?></label><input name="file_path" value="<?= h((string) ($edit['file_path'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Загрузить файл', 'Upload file')) ?></label><input type="file" name="file_upload" accept=".pdf,.doc,.docx"></div>
      <div><label><?= h(tr('Внешняя ссылка', 'External URL')) ?></label><input name="external_url" value="<?= h((string) ($edit['external_url'] ?? '')) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px"><label>Title (<?= h(strtoupper($locale)) ?>)</label><input name="title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['title'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Description (<?= h(strtoupper($locale)) ?>)</label><textarea rows="3" name="description_<?= h($locale) ?>"><?= h((string) ($trMap[$locale]['description'] ?? '')) ?></textarea></div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? h(tr('Обновить публикацию', 'Update publication')) : h(tr('Создать публикацию', 'Create publication')) ?></button>
      <a class="btn btn-secondary" href="/admin/publications.php?tab=publications"><?= h(tr('Новая', 'New')) ?></a>
    </div>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th><?= h(tr('Тип', 'Type')) ?></th><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Дата', 'Date')) ?></th><th><?= h(tr('Цель', 'Target')) ?></th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h((string) $r['id']) ?></td>
        <td><?= h((string) $r['type_slug']) ?></td>
        <td><?= h((string) $r['display_order']) ?></td>
        <td><?= h((string) $r['published_at']) ?></td>
        <td><?= h($r['file_path'] !== '' ? $r['file_path'] : (string) $r['external_url']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/publications.php?tab=publications&edit=<?= h((string) $r['id']) ?>"><?= h(tr('Редактировать', 'Edit')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="card">
  <h1><?= h(tr('Типы публикаций', 'Publication types')) ?></h1>
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
      <a class="btn btn-secondary" href="/admin/publications.php?tab=types"><?= h(tr('Новый', 'New')) ?></a>
    </div>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Slug</th><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($typeRows as $row): ?>
      <tr>
        <td><?= h((string) $row['id']) ?></td>
        <td><?= h($row['slug']) ?></td>
        <td><?= h((string) $row['sort_order']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/publications.php?tab=types&edit_type=<?= h((string) $row['id']) ?>"><?= h(tr('Редактировать', 'Edit')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php admin_footer();
