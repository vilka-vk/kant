<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = (string) ($_POST['install_secret'] ?? '');
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($config['app']['install_secret'], $secret)) {
        $error = tr('Неверный install secret.', 'Invalid install secret.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = tr('Некорректный email администратора.', 'Invalid admin email.');
    } elseif (strlen($password) < 10) {
        $error = tr('Пароль должен быть не короче 10 символов.', 'Password must be at least 10 characters.');
    } else {
        try {
            $pdo = db();
            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            $seed = file_get_contents(__DIR__ . '/../database/seed.sql');
            if ($schema === false || $seed === false) {
                throw new RuntimeException(tr('SQL-файлы не найдены.', 'SQL files are missing.'));
            }
            $pdo->exec($schema);
            $pdo->exec($seed);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (:email, :password_hash)
                                   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
            $stmt->execute(['email' => $email, 'password_hash' => $hash]);

            $ok = tr('Установка завершена. Удалите /admin/install.php или закройте доступ через .htaccess, затем войдите в админку.', 'Install finished. Delete /admin/install.php or protect it via .htaccess, then login.');
        } catch (Throwable $e) {
            $error = tr('Установка не удалась: ', 'Install failed: ') . $e->getMessage();
        }
    }
}

admin_header(tr('Установка админки KANT', 'KANT Admin Installer'));
?>
<div class="card">
  <h1><?= h(tr('Установка админки KANT', 'Install KANT Admin')) ?></h1>
  <p class="muted"><?= h(tr('Установщик создаёт schema + seed и создаёт/обновляет администратора.', 'This installer creates schema + seed and upserts admin user.')) ?></p>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <div class="grid">
      <div>
        <label><?= h(tr('Install secret', 'Install secret')) ?></label>
        <input name="install_secret" type="password" required>
      </div>
      <div>
        <label><?= h(tr('Email администратора', 'Admin email')) ?></label>
        <input name="email" type="email" required>
      </div>
    </div>
    <div style="margin-top:12px">
      <label><?= h(tr('Пароль администратора', 'Admin password')) ?></label>
      <input name="password" type="password" required minlength="10">
    </div>
    <div style="margin-top:16px" class="actions">
      <button type="submit"><?= h(tr('Запустить установку', 'Run install')) ?></button>
      <a class="btn btn-secondary" href="/admin/login.php"><?= h(tr('Перейти ко входу', 'Go to login')) ?></a>
    </div>
  </form>
</div>
<?php
admin_footer();
