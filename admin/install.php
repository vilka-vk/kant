<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/layout.php';

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = (string) ($_POST['install_secret'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($config['app']['install_secret'], $secret)) {
        $error = 'Invalid install secret.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid admin email.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } else {
        try {
            $pdo = db();
            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            $seed = file_get_contents(__DIR__ . '/../database/seed.sql');
            if ($schema === false || $seed === false) {
                throw new RuntimeException('SQL files are missing.');
            }
            $pdo->exec($schema);
            $pdo->exec($seed);
            $pdo->exec("ALTER TABLE modules_translations ADD COLUMN IF NOT EXISTS literature_html MEDIUMTEXT");
            $pdo->exec("CREATE TABLE IF NOT EXISTS about_project_videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                about_project_id INT NOT NULL DEFAULT 1,
                language_code VARCHAR(20) NOT NULL,
                video_url VARCHAR(500) NOT NULL,
                video_alt VARCHAR(500) DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS module_lecture_videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module_id INT NOT NULL,
                language_code VARCHAR(20) NOT NULL,
                video_url VARCHAR(500) NOT NULL,
                video_alt VARCHAR(500) DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS module_presentation_videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module_id INT NOT NULL,
                language_code VARCHAR(20) NOT NULL,
                video_url VARCHAR(500) NOT NULL,
                video_alt VARCHAR(500) DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0
            )");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (:email, :password_hash)
                                   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
            $stmt->execute(['email' => $email, 'password_hash' => $hash]);

            $ok = 'Install finished. Delete /admin/install.php or protect it via .htaccess, then login.';
        } catch (Throwable $e) {
            $error = 'Install failed: ' . $e->getMessage();
        }
    }
}

admin_header('KANT Admin Installer');
?>
<div class="card">
  <h1>Install KANT Admin</h1>
  <p class="muted">This installer creates schema + seed and upserts admin user.</p>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <div class="grid">
      <div>
        <label>Install secret</label>
        <input name="install_secret" type="password" required>
      </div>
      <div>
        <label>Admin email</label>
        <input name="email" type="email" required>
      </div>
    </div>
    <div style="margin-top:12px">
      <label>Admin password</label>
      <input name="password" type="password" required minlength="10">
    </div>
    <div style="margin-top:16px" class="actions">
      <button type="submit">Run install</button>
      <a class="btn btn-secondary" href="/admin/login.php">Go to login</a>
    </div>
  </form>
</div>
<?php
admin_footer();
