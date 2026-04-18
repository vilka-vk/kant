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
    $id = (int) ($_POST['id'] ?? 0);
    $file = trim((string) ($_POST['file_path'] ?? ''));
    $url = trim((string) ($_POST['external_url'] ?? ''));
    if (($file === '' && $url === '') || ($file !== '' && $url !== '')) {
        redirect('/admin/publications.php?error=xor');
    }
    $payload = [
        'publication_type_id' => (int) ($_POST['publication_type_id'] ?? 0),
        'cover_image_path' => trim((string) ($_POST['cover_image_path'] ?? '')),
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
    redirect('/admin/publications.php?edit=' . $id . '&saved=1');
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
$rows = $pdo->query('SELECT p.id, p.display_order, p.published_at, p.file_path, p.external_url, pt.slug as type_slug
  FROM publications p LEFT JOIN publication_types pt ON pt.id = p.publication_type_id
  ORDER BY p.display_order ASC, p.published_at DESC, p.id ASC')->fetchAll();

admin_header('Publications');
?>
<div class="card">
  <h1>Publications</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <?php if (!empty($_GET['error']) && $_GET['error'] === 'xor'): ?><p class="err">Exactly one of file path or external URL is required.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label>Type</label>
        <select name="publication_type_id" required>
          <option value="">Select</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= h((string) $t['id']) ?>" <?= ((int) ($edit['publication_type_id'] ?? 0) === (int) $t['id']) ? 'selected' : '' ?>><?= h($t['slug']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Published at</label><input name="published_at" value="<?= h((string) ($edit['published_at'] ?? date('Y-m-d 00:00:00'))) ?>"></div>
      <div><label>Display order</label><input type="number" name="display_order" value="<?= h((string) ($edit['display_order'] ?? 0)) ?>"></div>
      <div><label>Cover image path</label><input name="cover_image_path" value="<?= h((string) ($edit['cover_image_path'] ?? '')) ?>"></div>
      <div><label>File path</label><input name="file_path" value="<?= h((string) ($edit['file_path'] ?? '')) ?>"></div>
      <div><label>External URL</label><input name="external_url" value="<?= h((string) ($edit['external_url'] ?? '')) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px"><label>Title (<?= h(strtoupper($locale)) ?>)</label><input name="title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['title'] ?? '')) ?>"></div>
      <div style="margin-bottom:12px"><label>Description (<?= h(strtoupper($locale)) ?>)</label><textarea rows="3" name="description_<?= h($locale) ?>"><?= h((string) ($trMap[$locale]['description'] ?? '')) ?></textarea></div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? 'Update publication' : 'Create publication' ?></button>
      <a class="btn btn-secondary" href="/admin/publications.php">New</a>
    </div>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Type</th><th>Order</th><th>Date</th><th>Target</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h((string) $r['id']) ?></td>
        <td><?= h((string) $r['type_slug']) ?></td>
        <td><?= h((string) $r['display_order']) ?></td>
        <td><?= h((string) $r['published_at']) ?></td>
        <td><?= h($r['file_path'] !== '' ? $r['file_path'] : (string) $r['external_url']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/publications.php?edit=<?= h((string) $r['id']) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
