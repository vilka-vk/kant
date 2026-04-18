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
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE publication_types SET slug=:slug, sort_order=:sort_order WHERE id=:id');
        $stmt->execute(['id' => $id, 'slug' => $slug, 'sort_order' => $sort]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO publication_types (slug, sort_order) VALUES (:slug, :sort_order)');
        $stmt->execute(['slug' => $slug, 'sort_order' => $sort]);
        $id = (int) $pdo->lastInsertId();
    }
    foreach ($locales as $locale) {
        $name = trim((string) ($_POST['name_' . $locale] ?? ''));
        $stmt = $pdo->prepare('INSERT INTO publication_types_translations (publication_type_id, locale, name)
          VALUES (:id, :locale, :name) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $stmt->execute(['id' => $id, 'locale' => $locale, 'name' => $name !== '' ? $name : '[empty]']);
    }
    redirect('/admin/publication-types.php?edit=' . $id . '&saved=1');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM publication_types WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, name FROM publication_types_translations WHERE publication_type_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $r) {
        $trMap[$r['locale']] = $r['name'];
    }
}

$rows = $pdo->query('SELECT * FROM publication_types ORDER BY sort_order ASC, id ASC')->fetchAll();
admin_header('Publication Types');
?>
<div class="card">
  <h1>Publication types</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label>Slug</label><input name="slug" required value="<?= h((string) ($edit['slug'] ?? '')) ?>"></div>
      <div><label>Sort order</label><input type="number" name="sort_order" value="<?= h((string) ($edit['sort_order'] ?? 0)) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px">
        <label>Name (<?= h(strtoupper($locale)) ?>)</label>
        <input name="name_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale] ?? '')) ?>">
      </div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? 'Update type' : 'Create type' ?></button>
      <a class="btn btn-secondary" href="/admin/publication-types.php">New</a>
    </div>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Slug</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= h((string) $row['id']) ?></td>
        <td><?= h($row['slug']) ?></td>
        <td><?= h((string) $row['sort_order']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/publication-types.php?edit=<?= h((string) $row['id']) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
