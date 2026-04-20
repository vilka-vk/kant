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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $photoPath = trim((string) ($_POST['photo_path'] ?? ''));
    try {
        $uploadedPhoto = upload_public_file('photo_upload', 'authors', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
        if ($uploadedPhoto) {
            $photoPath = $uploadedPhoto;
        }
    } catch (Throwable $e) {
        redirect('/admin/authors.php?error=' . urlencode($e->getMessage()));
    }
    $payload = [
        'photo_path' => $photoPath,
        'display_order' => (int) ($_POST['display_order'] ?? 0),
    ];
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE authors SET photo_path=:photo_path, display_order=:display_order WHERE id=:id');
        $stmt->execute($payload + ['id' => $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO authors (photo_path, display_order) VALUES (:photo_path, :display_order)');
        $stmt->execute($payload);
        $id = (int) $pdo->lastInsertId();
    }
    foreach ($locales as $locale) {
        $stmt = $pdo->prepare('INSERT INTO authors_translations
          (author_id, locale, first_name, last_name, full_name, affiliation)
          VALUES (:id, :locale, :first_name, :last_name, :full_name, :affiliation)
          ON DUPLICATE KEY UPDATE first_name=VALUES(first_name),last_name=VALUES(last_name),full_name=VALUES(full_name),affiliation=VALUES(affiliation)');
        $stmt->execute([
            'id' => $id,
            'locale' => $locale,
            'first_name' => trim((string) ($_POST['first_name_' . $locale] ?? '')),
            'last_name' => trim((string) ($_POST['last_name_' . $locale] ?? '')),
            'full_name' => '',
            'affiliation' => trim((string) ($_POST['affiliation_' . $locale] ?? '')),
        ]);
    }
    redirect('/admin/authors.php?edit=' . $id . '&saved=1');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM authors WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT * FROM authors_translations WHERE author_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr;
    }
}
$rows = $pdo->query('SELECT * FROM authors ORDER BY display_order ASC, id ASC')->fetchAll();

admin_header('Authors');
?>
<div class="card">
  <h1>Authors</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <?php if (!empty($_GET['error'])): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
    <div class="grid">
      <div><label>Photo path</label><input name="photo_path" required value="<?= h((string) ($edit['photo_path'] ?? '')) ?>"></div>
      <div><label>Upload photo</label><input type="file" name="photo_upload" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
      <div><label>Display order</label><input type="number" name="display_order" value="<?= h((string) ($edit['display_order'] ?? 0)) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div class="grid" style="margin-bottom:12px">
        <div><label>First name (<?= h(strtoupper($locale)) ?>)</label><input name="first_name_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['first_name'] ?? '')) ?>"></div>
        <div><label>Last name (<?= h(strtoupper($locale)) ?>)</label><input name="last_name_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['last_name'] ?? '')) ?>"></div>
        <div><label>Affiliation (<?= h(strtoupper($locale)) ?>)</label><input name="affiliation_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['affiliation'] ?? '')) ?>"></div>
      </div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? 'Update author' : 'Create author' ?></button>
      <a class="btn btn-secondary" href="/admin/authors.php">New</a>
    </div>
  </form>
</div>
<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Photo</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h((string) $r['id']) ?></td>
        <td><?= h($r['photo_path']) ?></td>
        <td><?= h((string) $r['display_order']) ?></td>
        <td><a class="btn btn-secondary" href="/admin/authors.php?edit=<?= h((string) $r['id']) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
