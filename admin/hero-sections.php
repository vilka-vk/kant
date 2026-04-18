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
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE hero_sections SET subtitle_enabled=:subtitle_enabled, background_image_path=:bg WHERE id=:id');
        $stmt->execute([
            'id' => $id,
            'subtitle_enabled' => isset($_POST['subtitle_enabled']) ? 1 : 0,
            'bg' => trim((string) ($_POST['background_image_path'] ?? '')),
        ]);
        foreach ($locales as $locale) {
            $stmt = $pdo->prepare('INSERT INTO hero_sections_translations (hero_section_id, locale, title, subtitle)
              VALUES (:id, :locale, :title, :subtitle)
              ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle)');
            $stmt->execute([
                'id' => $id,
                'locale' => $locale,
                'title' => trim((string) ($_POST['title_' . $locale] ?? '')),
                'subtitle' => trim((string) ($_POST['subtitle_' . $locale] ?? '')),
            ]);
        }
        redirect('/admin/hero-sections.php?edit=' . $id . '&saved=1');
    }
}

$rows = $pdo->query('SELECT * FROM hero_sections ORDER BY id ASC')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
$trMap = [];
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM hero_sections WHERE id=:id');
    $stmt->execute(['id' => $editId]);
    $edit = $stmt->fetch();
    $trs = $pdo->prepare('SELECT locale, title, subtitle FROM hero_sections_translations WHERE hero_section_id=:id');
    $trs->execute(['id' => $editId]);
    foreach ($trs->fetchAll() as $tr) {
        $trMap[$tr['locale']] = $tr;
    }
}

admin_header('Hero Sections');
?>
<div class="card">
  <h1>Hero sections</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <table>
    <thead><tr><th>ID</th><th>Page key</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h((string) $r['id']) ?></td>
          <td><?= h($r['page_key']) ?></td>
          <td><a class="btn btn-secondary" href="/admin/hero-sections.php?edit=<?= h((string) $r['id']) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($edit): ?>
<div class="card">
  <h2>Edit: <?= h($edit['page_key']) ?></h2>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string) $edit['id']) ?>">
    <div class="grid">
      <div><label>Background image path</label><input name="background_image_path" value="<?= h((string) $edit['background_image_path']) ?>"></div>
      <div><label><input type="checkbox" name="subtitle_enabled" <?= ((int) $edit['subtitle_enabled'] === 1) ? 'checked' : '' ?>> subtitle enabled</label></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:10px"><label>Title (<?= h(strtoupper($locale)) ?>)</label><input name="title_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['title'] ?? '')) ?>"></div>
      <div style="margin-bottom:10px"><label>Subtitle (<?= h(strtoupper($locale)) ?>)</label><input name="subtitle_<?= h($locale) ?>" value="<?= h((string) ($trMap[$locale]['subtitle'] ?? '')) ?>"></div>
    <?php endforeach; ?>
    <button type="submit">Save</button>
  </form>
</div>
<?php endif; ?>
<?php admin_footer();
