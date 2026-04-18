<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
$pdo = db();
$locales = $config['app']['supported_locales'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM module_readings WHERE id = :id')->execute(['id' => $id]);
        redirect('/admin/module-readings.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $linkedPublicationId = (int) ($_POST['linked_publication_id'] ?? 0);
    $customUrl = trim((string) ($_POST['custom_url'] ?? ''));
    $customFile = trim((string) ($_POST['custom_file_path'] ?? ''));
    if ($linkedPublicationId === 0 && (($customUrl === '' && $customFile === '') || ($customUrl !== '' && $customFile !== ''))) {
        redirect('/admin/module-readings.php?error=xor');
    }

    $payload = [
        'module_id' => (int) ($_POST['module_id'] ?? 0),
        'linked_publication_id' => $linkedPublicationId > 0 ? $linkedPublicationId : null,
        'custom_url' => $customUrl,
        'custom_file_path' => $customFile,
        'custom_cover_image_path' => trim((string) ($_POST['custom_cover_image_path'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($id > 0) {
        $pdo->prepare('UPDATE module_readings SET module_id=:module_id, linked_publication_id=:linked_publication_id, custom_url=:custom_url,
          custom_file_path=:custom_file_path, custom_cover_image_path=:custom_cover_image_path, sort_order=:sort_order WHERE id=:id')
            ->execute($payload + ['id' => $id]);
    } else {
        $pdo->prepare('INSERT INTO module_readings (module_id, linked_publication_id, custom_url, custom_file_path, custom_cover_image_path, sort_order)
          VALUES (:module_id, :linked_publication_id, :custom_url, :custom_file_path, :custom_cover_image_path, :sort_order)')
            ->execute($payload);
        $id = (int) $pdo->lastInsertId();
    }

    foreach ($locales as $locale) {
        $customTitle = trim((string) ($_POST['custom_title_' . $locale] ?? ''));
        $pdo->prepare('INSERT INTO module_readings_translations (module_reading_id, locale, custom_title)
          VALUES (:id,:locale,:custom_title)
          ON DUPLICATE KEY UPDATE custom_title = VALUES(custom_title)')
            ->execute([
                'id' => $id,
                'locale' => $locale,
                'custom_title' => $customTitle,
            ]);
    }
    redirect('/admin/module-readings.php?edit=' . $id . '&saved=1');
}

$modules = $pdo->query('SELECT id, slug, module_number FROM modules ORDER BY sort_order ASC, id ASC')->fetchAll();
$publications = $pdo->query('SELECT id, display_order FROM publications ORDER BY display_order ASC, id ASC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM module_readings WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, custom_title FROM module_readings_translations WHERE module_reading_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr['custom_title'];
    }
}
$rows = $pdo->query('SELECT mr.id, mr.sort_order, mr.custom_url, mr.custom_file_path, mr.linked_publication_id, m.slug, m.module_number
  FROM module_readings mr
  LEFT JOIN modules m ON m.id = mr.module_id
  ORDER BY mr.sort_order ASC, mr.id ASC')->fetchAll();

admin_header('Module Readings');
?>
<div class="card">
  <h1>Module readings</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'xor'): ?><p class="err">Without linked publication you must set exactly one field: custom URL or custom file path.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
    <div class="grid">
      <div>
        <label>Module</label>
        <select name="module_id" required>
          <option value="">Select module</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= h((string) $m['id']) ?>" <?= ((int) ($edit['module_id'] ?? 0) === (int) $m['id']) ? 'selected' : '' ?>>
              <?= h('Module ' . $m['module_number'] . ' (' . $m['slug'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Linked publication (optional)</label>
        <select name="linked_publication_id">
          <option value="">None</option>
          <?php foreach ($publications as $p): ?>
            <option value="<?= h((string) $p['id']) ?>" <?= ((int) ($edit['linked_publication_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
              <?= h('Publication #' . $p['id'] . ' (order ' . $p['display_order'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Custom URL</label><input name="custom_url" value="<?= h((string) ($edit['custom_url'] ?? '')) ?>"></div>
      <div><label>Custom file path</label><input name="custom_file_path" value="<?= h((string) ($edit['custom_file_path'] ?? '')) ?>"></div>
      <div><label>Custom cover image path</label><input name="custom_cover_image_path" value="<?= h((string) ($edit['custom_cover_image_path'] ?? '')) ?>"></div>
      <div><label>Sort order</label><input type="number" name="sort_order" value="<?= h((string) ($edit['sort_order'] ?? 0)) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:10px">
        <label>Custom title (<?= h(strtoupper($locale)) ?>)</label>
        <input name="custom_title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale] ?? '')) ?>">
      </div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? 'Update reading' : 'Create reading' ?></button>
      <a class="btn btn-secondary" href="/admin/module-readings.php">New</a>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Module</th><th>Linked pub</th><th>Custom target</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h((string) $r['id']) ?></td>
        <td><?= h('Module ' . $r['module_number'] . ' (' . $r['slug'] . ')') ?></td>
        <td><?= h((string) ($r['linked_publication_id'] ?: '-')) ?></td>
        <td><?= h($r['custom_file_path'] !== '' ? (string) $r['custom_file_path'] : (string) $r['custom_url']) ?></td>
        <td><?= h((string) $r['sort_order']) ?></td>
        <td class="actions">
          <a class="btn btn-secondary" href="/admin/module-readings.php?edit=<?= h((string) $r['id']) ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete reading?')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= h((string) $r['id']) ?>">
            <button type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
