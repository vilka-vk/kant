<?php
declare(strict_types=1);

function admin_nav_items(): array
{
    return [
        ['href' => '/admin/dashboard.php', 'label' => t('nav.dashboard')],
        ['href' => '/admin/modules.php', 'label' => t('nav.modules')],
        ['href' => '/admin/publications.php', 'label' => t('nav.publications')],
        ['href' => '/admin/authors.php', 'label' => t('nav.authors')],
        ['href' => '/admin/about-project.php', 'label' => t('nav.about_project')],
        ['href' => '/admin/our-position.php', 'label' => t('nav.our_position')],
        ['href' => '/admin/site-settings.php', 'label' => t('nav.site_settings')],
    ];
}

function admin_lang_url(string $lang): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
    $parts = parse_url($uri);
    $path = (string) ($parts['path'] ?? '/admin/dashboard.php');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    $query['lang'] = $lang;
    return $path . '?' . http_build_query($query);
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
        $migrationsActive = ($path === '/admin/migrate.php') ? ' is-active' : '';
        echo '<div class="kant-sidebar-bottom">';
        echo '<a class="nav-link' . $migrationsActive . '" href="/admin/migrate.php"><span class="title">' . h(t('nav.migrations')) . '</span></a>';
        echo '</div>';
        echo '</aside>';
        echo '<main class="content kant-content">';
        echo '<header class="kant-topbar">';
        echo '<div><h1 class="kant-page-title">' . h($title) . '</h1></div>';
        echo '<div class="kant-topbar-actions">';
        echo '<a class="btn btn-secondary' . (admin_locale() === 'ru' ? ' is-active-lang' : '') . '" href="' . h(admin_lang_url('ru')) . '">RU</a>';
        echo '<a class="btn btn-secondary' . (admin_locale() === 'en' ? ' is-active-lang' : '') . '" href="' . h(admin_lang_url('en')) . '">EN</a>';
        echo '<span class="kant-user">' . h($user['email']) . '</span>';
        echo '<a class="btn btn-secondary" href="/admin/logout.php">' . h(t('ui.logout')) . '</a>';
        echo '</div></header>';
        echo '<div class="wrap">';
        return;
    }

    echo '<main class="content kant-content kant-content--single">';
    echo '<header class="kant-topbar">';
    echo '<div><h1 class="kant-page-title">' . h($title) . '</h1></div>';
    echo '<div class="kant-topbar-actions">';
    echo '<a class="btn btn-secondary' . (admin_locale() === 'ru' ? ' is-active-lang' : '') . '" href="' . h(admin_lang_url('ru')) . '">RU</a>';
    echo '<a class="btn btn-secondary' . (admin_locale() === 'en' ? ' is-active-lang' : '') . '" href="' . h(admin_lang_url('en')) . '">EN</a>';
    echo '<a class="btn btn-secondary" href="/admin/login.php">' . h(t('ui.login')) . '</a>';
    echo '</div>';
    echo '</header><div class="wrap">';
}

function admin_footer(): void
{
    if (current_user()) {
        echo '</div></main></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js" referrerpolicy="origin"></script>';
        echo '<script>if(window.tinymce){tinymce.init({selector:"textarea.wysiwyg",menubar:false,height:220,plugins:"link lists code",toolbar:"undo redo | bold italic underline | bullist numlist | link | code"});}else{console.warn("TinyMCE is not loaded");}</script>';
        echo '<script>
window.initKantDrawerCloseGuard = function (opts) {
  if (!opts) return;
  var form = document.querySelector(opts.formSelector || "");
  var closeBtn = document.querySelector(opts.closeSelector || "");
  var overlay = document.querySelector(opts.overlaySelector || "");
  if (!form || !closeBtn || !overlay) return;
  var saveBtn = document.querySelector(opts.saveSelector || "");
  var discardBtn = document.querySelector(opts.discardSelector || "");
  var cancelBtn = document.querySelector(opts.cancelSelector || "");
  if (!saveBtn || !discardBtn || !cancelBtn) return;
  var dirty = false;
  var pendingCloseHref = "";
  form.addEventListener("input", function () { dirty = true; });
  form.addEventListener("change", function () { dirty = true; });
  form.addEventListener("submit", function () { dirty = false; });
  closeBtn.addEventListener("click", function (e) {
    if (!dirty) return;
    e.preventDefault();
    pendingCloseHref = closeBtn.getAttribute("href") || opts.fallbackHref || "/";
    overlay.classList.add("is-open");
  });
  saveBtn.addEventListener("click", function () { form.requestSubmit(); });
  discardBtn.addEventListener("click", function () { window.location.href = pendingCloseHref || opts.fallbackHref || "/"; });
  cancelBtn.addEventListener("click", function () {
    overlay.classList.remove("is-open");
    pendingCloseHref = "";
  });
};
</script>';
        echo '<script>
(function () {
  var KEY = "kantAdminScrollRestore";
  try {
    var raw = sessionStorage.getItem(KEY);
    if (raw) {
      var data = JSON.parse(raw);
      if (data && data.path === window.location.pathname) {
        window.scrollTo(0, Number(data.y) || 0);
      }
      sessionStorage.removeItem(KEY);
    }
  } catch (e) {}

  document.querySelectorAll("form[method=\'post\']").forEach(function (form) {
    form.addEventListener("submit", function () {
      try {
        sessionStorage.setItem(KEY, JSON.stringify({
          path: window.location.pathname,
          y: window.scrollY || 0
        }));
      } catch (e) {}
    });
  });
})();
</script>';
        echo '</body></html>';
        return;
    }
    echo '</div></main></body></html>';
}
