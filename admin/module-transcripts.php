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
        $pdo->prepare('DELETE FROM module_transcripts WHERE id = :id')->execute(['id' => $id]);
        redirect('/admin/module-transcripts.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $payload = [
        'module_id' => (int) ($_POST['module_id'] ?? 0),
        'file_path' => trim((string) ($_POST['file_path'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($id > 0) {
        $pdo->prepare('UPDATE module_transcripts SET module_id=:module_id, file_path=:file_path, sort_order=:sort_order WHERE id=:id')
            ->execute($payload + ['id' => $id]);
    } else {
        $pdo->prepare('INSERT INTO module_transcripts (module_id, file_path, sort_order) VALUES (:module_id, :file_path, :sort_order)')
            ->execute($payload);
        $id = (int) $pdo->lastInsertId();
    }

    foreach ($locales as $locale) {
        $displayName = trim((string) ($_POST['display_name_' . $locale] ?? ''));
        $pdo->prepare('INSERT INTO module_transcripts_translations (module_transcript_id, locale, display_name)
          VALUES (:id,:locale,:display_name)
          ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)')
            ->execute([
                'id' => $id,
                'locale' => $locale,
                'display_name' => $displayName !== '' ? $displayName : '[empty]',
            ]);
    }
    redirect('/admin/module-transcripts.php?edit=' . $id . '&saved=1');
}

$modules = $pdo->query('SELECT id, slug, module_number FROM modules ORDER BY sort_order ASC, id ASC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM module_transcripts WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, display_name FROM module_transcripts_translations WHERE module_transcript_id = :id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr['display_name'];
    }
}
$rows = $pdo->query('SELECT mt.id, mt.file_path, mt.sort_order, m.slug, m.module_number
  FROM module_transcripts mt
  LEFT JOIN modules m ON m.id = mt.module_id
  ORDER BY mt.sort_order ASC, mt.id ASC')->fetchAll();

admin_header('Module Transcripts');
?>
<div class="card">
  <h1>Module transcripts</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
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
      <div><label>File path (PDF)</label><input name="file_path" required value="<?= h((string) ($edit['file_path'] ?? '')) ?>"></div>
      <div><label>Sort order</label><input type="number" name="sort_order" value="<?= h((string) ($edit['sort_order'] ?? 0)) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:10px">
        <label>Display name (<?= h(strtoupper($locale)) ?>)</label>
        <input name="display_name_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale] ?? '')) ?>">
      </div>
    <?php endforeach; ?>
    <div class="actions">
      <button type="submit"><?= $edit ? 'Update transcript' : 'Create transcript' ?></button>
      <a class="btn btn-secondary" href="/admin/module-transcripts.php">New</a>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>ID</th><th>Module</th><th>File</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h((string) $r['id']) ?></td>
        <td><?= h('Module ' . $r['module_number'] . ' (' . $r['slug'] . ')') ?></td>
        <td><?= h((string) $r['file_path']) ?></td>
        <td><?= h((string) $r['sort_order']) ?></td>
        <td class="actions">
          <a class="btn btn-secondary" href="/admin/module-transcripts.php?edit=<?= h((string) $r['id']) ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete transcript?')">
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
