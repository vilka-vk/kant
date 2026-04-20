<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
admin_header(t('dashboard.title'));
?>
<div class="card kant-hero-card">
  <h1><?= h(t('dashboard.heading')) ?></h1>
  <p class="muted"><?= h(t('dashboard.description')) ?></p>
</div>

<div class="kant-quick-grid">
  <a class="card kant-quick-card" href="/admin/modules.php">
    <h3><?= h(t('nav.modules')) ?></h3>
    <p class="muted"><?= h(t('dashboard.modules_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/publications.php">
    <h3><?= h(t('nav.publications')) ?></h3>
    <p class="muted"><?= h(t('dashboard.publications_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/authors.php">
    <h3><?= h(t('nav.authors')) ?></h3>
    <p class="muted"><?= h(t('dashboard.authors_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/about-project.php">
    <h3><?= h(t('nav.about_project')) ?></h3>
    <p class="muted"><?= h(t('dashboard.about_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/our-position.php">
    <h3><?= h(t('nav.our_position')) ?></h3>
    <p class="muted"><?= h(t('dashboard.position_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/site-settings.php">
    <h3><?= h(t('nav.site_settings')) ?></h3>
    <p class="muted"><?= h(t('dashboard.settings_desc')) ?></p>
  </a>
  <a class="card kant-quick-card" href="/admin/migrate.php">
    <h3><?= h(t('nav.migrations')) ?></h3>
    <p class="muted"><?= h(t('dashboard.migrations_desc')) ?></p>
  </a>
</div>

<div class="card">
  <h2><?= h(t('dashboard.quick_reminders')) ?></h2>
  <ul class="kant-checklist">
    <li><?= h(t('dashboard.reminder_locales')) ?></li>
    <li><?= h(t('dashboard.reminder_migrations')) ?></li>
    <li><?= h(t('dashboard.reminder_uploads')) ?></li>
  </ul>
</div>
<?php
admin_footer();
