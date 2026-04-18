<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

if (current_user()) {
    redirect('/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        $error = 'Invalid credentials.';
    } else {
        login_user($user);
        redirect('/admin/dashboard.php');
    }
}

admin_header('KANT Admin Login');
?>
<div class="card" style="max-width:520px">
  <h1>Admin Login</h1>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <form method="post">
    <div>
      <label>Email</label>
      <input type="email" name="email" required>
    </div>
    <div style="margin-top:12px">
      <label>Password</label>
      <input type="password" name="password" required>
    </div>
    <div style="margin-top:16px" class="actions">
      <button type="submit">Login</button>
      <a class="btn btn-secondary" href="/admin/install.php">Install</a>
    </div>
  </form>
</div>
<?php
admin_footer();
