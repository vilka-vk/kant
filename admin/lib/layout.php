<?php
declare(strict_types=1);

function admin_header(string $title): void
{
    $user = current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
body{font-family:Arial,sans-serif;margin:0;background:#f7f7f9;color:#222}
.wrap{max-width:1100px;margin:0 auto;padding:20px}
.nav{display:flex;justify-content:space-between;align-items:center;background:#111;color:#fff;padding:14px 20px}
.nav a{color:#fff;text-decoration:none;margin-right:14px}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
input,textarea,select{width:100%;padding:8px;border:1px solid #bbb;border-radius:6px}
label{display:block;font-weight:bold;margin-bottom:6px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.actions{display:flex;gap:8px;align-items:center}
button,.btn{background:#111;color:#fff;border:none;border-radius:6px;padding:10px 14px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-secondary{background:#555}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid #ddd;padding:10px;text-align:left}
.muted{color:#777;font-size:13px}
.ok{color:#0a7d26}
.err{color:#b00020}
</style>';
    echo '</head><body>';
    echo '<div class="nav"><div><a href="/admin/dashboard.php">KANT Admin</a></div><div>';
    if ($user) {
        echo '<span style="margin-right:10px">' . h($user['email']) . '</span><a href="/admin/logout.php">Logout</a>';
    }
    echo '</div></div><div class="wrap">';
}

function admin_footer(): void
{
    echo '</div></body></html>';
}
