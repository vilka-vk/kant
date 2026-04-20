<?php
declare(strict_types=1);

function admin_nav_items(): array
{
    return [
        ['href' => '/admin/dashboard.php', 'label' => 'Dashboard'],
        ['href' => '/admin/modules.php', 'label' => 'Modules'],
        ['href' => '/admin/site-settings.php', 'label' => 'Site settings'],
        ['href' => '/admin/about-project.php', 'label' => 'About project'],
        ['href' => '/admin/our-position.php', 'label' => 'Our position'],
        ['href' => '/admin/publication-types.php', 'label' => 'Publication types'],
        ['href' => '/admin/publications.php', 'label' => 'Publications'],
        ['href' => '/admin/authors.php', 'label' => 'Authors'],
        ['href' => '/admin/migrate.php', 'label' => 'Migrations'],
    ];
}

function admin_header(string $title): void
{
    $user = current_user();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="/admin/assets/vvveb-admin.css">';
    echo '<link rel="stylesheet" href="/admin/assets/admin-theme.css">';
    echo '</head><body>';
    if ($user) {
        echo '<div id="container">';
        echo '<aside class="sidebar kant-sidebar">';
        echo '<div class="logo">';
        echo '<a href="/admin/dashboard.php" class="img"><span class="kant-logo">KANT Admin</span></a>';
        echo '</div>';
        echo '<nav class="navbar navbar-expand-md"><div class="collapse navbar-collapse show"><ul class="nav navbar-nav flex-column">';
        foreach (admin_nav_items() as $item) {
            $active = ($path === $item['href']) ? ' is-active' : '';
            echo '<li class="nav-item">';
            echo '<a class="nav-link' . $active . '" href="' . h($item['href']) . '"><span class="title">' . h($item['label']) . '</span></a>';
            echo '</li>';
        }
        echo '</ul></div></nav>';
        echo '</aside>';
        echo '<main class="content kant-content">';
        echo '<header class="kant-topbar">';
        echo '<div><h1 class="kant-page-title">' . h($title) . '</h1></div>';
        echo '<div class="kant-topbar-actions">';
        echo '<span class="kant-user">' . h($user['email']) . '</span>';
        echo '<a class="btn btn-secondary" href="/admin/logout.php">Logout</a>';
        echo '</div></header>';
        echo '<div class="wrap">';
        return;
    }

    echo '<main class="content kant-content kant-content--single">';
    echo '<header class="kant-topbar">';
    echo '<div><h1 class="kant-page-title">' . h($title) . '</h1></div>';
    echo '<div class="kant-topbar-actions"><a class="btn btn-secondary" href="/admin/login.php">Login</a></div>';
    echo '</header><div class="wrap">';
}

function admin_footer(): void
{
    if (current_user()) {
        echo '</div></main></div>';
        echo '<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>';
        echo '<script>if(window.tinymce){tinymce.init({selector:"textarea.wysiwyg",menubar:false,height:220,plugins:"link lists code",toolbar:"undo redo | bold italic underline | bullist numlist | link | code"});}</script>';
        echo '</body></html>';
        return;
    }
    echo '</div></main></body></html>';
}
