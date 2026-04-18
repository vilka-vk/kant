<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
    try {
        $sql = file_get_contents(__DIR__ . '/../database/migrations/2026-04-17-remove-legacy-video-columns.sql');
        if ($sql === false) {
            throw new RuntimeException('Migration file not found.');
        }
        db()->exec($sql);
        $ok = 'Migration applied.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

admin_header('Run Migration');
?>
<div class="card">
  <h1>Legacy Video Cleanup</h1>
  <p class="muted">Drops unused legacy video columns from `modules` and `modules_translations`.</p>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post" onsubmit="return confirm('Apply migration now?')">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <button type="submit">Run migration</button>
  </form>
</div>
<?php admin_footer();
