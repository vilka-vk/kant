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
        $migrations = [
            __DIR__ . '/../database/migrations/2026-04-17-remove-legacy-video-columns.sql',
            __DIR__ . '/../database/migrations/2026-04-20-add-our-position.sql',
        ];
        foreach ($migrations as $migrationPath) {
            $sql = file_get_contents($migrationPath);
            if ($sql === false) {
                throw new RuntimeException('Migration file not found: ' . basename($migrationPath));
            }
            db()->exec($sql);
        }
        $ok = 'Migrations applied.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

admin_header('Run Migration');
?>
<div class="card">
  <h1>Database Migrations</h1>
  <p class="muted">Applies idempotent migrations: legacy video cleanup and Our Position tables.</p>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <form method="post" onsubmit="return confirm('Apply migration now?')">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <button type="submit">Run migrations</button>
  </form>
</div>
<?php admin_footer();
