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
    $action = (string) ($_POST['action'] ?? 'save_about');
    if ($action === 'add_about_video') {
        $stmt = $pdo->prepare('INSERT INTO about_project_videos (about_project_id, language_code, video_url, video_alt, sort_order)
          VALUES (1, :language_code, :video_url, :video_alt, :sort_order)');
        $stmt->execute([
            'language_code' => strtolower(trim((string) ($_POST['video_language_code'] ?? 'en'))),
            'video_url' => trim((string) ($_POST['video_url'] ?? '')),
            'video_alt' => trim((string) ($_POST['video_alt'] ?? '')),
            'sort_order' => (int) ($_POST['video_sort_order'] ?? 0),
        ]);
        redirect('/admin/about-project.php');
    }
    if ($action === 'delete_about_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        if ($videoId > 0) {
            $stmt = $pdo->prepare('DELETE FROM about_project_videos WHERE id = :id');
            $stmt->execute(['id' => $videoId]);
        }
        redirect('/admin/about-project.php');
    }
    foreach ($locales as $locale) {
        $stmt = $pdo->prepare('INSERT INTO about_project_translations
          (about_project_id, locale, section_title, sticker_text, video_title_primary, video_title_secondary, modal_body)
          VALUES (1, :locale, :section_title, :sticker_text, :video_title_primary, :video_title_secondary, :modal_body)
          ON DUPLICATE KEY UPDATE section_title=VALUES(section_title), sticker_text=VALUES(sticker_text),
          video_title_primary=VALUES(video_title_primary), video_title_secondary=VALUES(video_title_secondary), modal_body=VALUES(modal_body)');
        $stmt->execute([
            'locale' => $locale,
            'section_title' => trim((string) ($_POST['section_title_' . $locale] ?? '')),
            'sticker_text' => trim((string) ($_POST['sticker_text_' . $locale] ?? '')),
            'video_title_primary' => trim((string) ($_POST['video_title_primary_' . $locale] ?? '')),
            'video_title_secondary' => trim((string) ($_POST['video_title_secondary_' . $locale] ?? '')),
            'modal_body' => trim((string) ($_POST['modal_body_' . $locale] ?? '')),
        ]);
    }
    redirect('/admin/about-project.php?saved=1');
}

$base = $pdo->query('SELECT * FROM about_project WHERE id = 1')->fetch() ?: [];
$aboutVideos = $pdo->query('SELECT * FROM about_project_videos WHERE about_project_id = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
$tr = $pdo->query('SELECT * FROM about_project_translations WHERE about_project_id = 1')->fetchAll();
$map = [];
foreach ($tr as $row) {
    $map[$row['locale']] = $row;
}

admin_header('About Project');
?>
<div class="card">
  <h1>About project</h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok">Saved.</p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_about">
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): $x = $map[$locale] ?? []; ?>
      <h3><?= h(strtoupper($locale)) ?></h3>
      <div style="margin-bottom:10px"><label>Section title</label><input name="section_title_<?= h($locale) ?>" value="<?= h((string) ($x['section_title'] ?? '')) ?>"></div>
      <div style="margin-bottom:10px"><label>Sticker text</label><textarea rows="4" name="sticker_text_<?= h($locale) ?>"><?= h((string) ($x['sticker_text'] ?? '')) ?></textarea></div>
      <div class="grid" style="margin-bottom:10px">
        <div><label>Video title primary</label><input name="video_title_primary_<?= h($locale) ?>" value="<?= h((string) ($x['video_title_primary'] ?? '')) ?>"></div>
        <div><label>Video title secondary</label><input name="video_title_secondary_<?= h($locale) ?>" value="<?= h((string) ($x['video_title_secondary'] ?? '')) ?>"></div>
      </div>
      <div style="margin-bottom:14px"><label>Modal body</label><textarea rows="5" name="modal_body_<?= h($locale) ?>"><?= h((string) ($x['modal_body'] ?? '')) ?></textarea></div>
    <?php endforeach; ?>
    <button type="submit">Save</button>
  </form>
</div>
<div class="card">
  <h2>About project videos by language (dynamic tabs)</h2>
  <form method="post" style="margin-bottom:14px">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_about_video">
    <div class="grid">
      <div><label>Language code</label><input name="video_language_code" placeholder="en / ru / arm / kz / de / ge / az" required></div>
      <div><label>Video URL</label><input name="video_url" required></div>
      <div><label>Video Alt (optional)</label><input name="video_alt"></div>
      <div><label>Sort order</label><input type="number" name="video_sort_order" value="0"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit">Add about video</button></div>
  </form>
  <table>
    <thead><tr><th>Lang</th><th>URL</th><th>Alt</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($aboutVideos as $video): ?>
      <tr>
        <td><?= h((string) $video['language_code']) ?></td>
        <td><?= h((string) $video['video_url']) ?></td>
        <td><?= h((string) $video['video_alt']) ?></td>
        <td><?= h((string) $video['sort_order']) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this video?')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_about_video">
            <input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>">
            <button type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
