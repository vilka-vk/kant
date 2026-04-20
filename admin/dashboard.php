<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
admin_header('KANT Admin Dashboard');
?>
<div class="card kant-hero-card">
  <h1>Content dashboard</h1>
  <p class="muted">Use the sections below to update content, translations, media, and maintenance tasks.</p>
</div>

<div class="kant-quick-grid">
  <a class="card kant-quick-card" href="/admin/modules.php">
    <h3>Modules</h3>
    <p class="muted">Edit module pages, videos, transcripts, readings, and hero settings.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/publications.php">
    <h3>Publications</h3>
    <p class="muted">Manage publication cards, files, and publication page hero content.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/authors.php">
    <h3>Authors</h3>
    <p class="muted">Maintain author profiles and uploaded author photos.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/about-project.php">
    <h3>About project</h3>
    <p class="muted">Update intro block text, videos, and section translations.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/our-position.php">
    <h3>Our position</h3>
    <p class="muted">Edit concept, principles, objectives, and media for the homepage block.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/publication-types.php">
    <h3>Publication types</h3>
    <p class="muted">Configure category filters for publication tabs.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/site-settings.php">
    <h3>Site settings</h3>
    <p class="muted">Change global settings used across pages.</p>
  </a>
  <a class="card kant-quick-card" href="/admin/migrate.php">
    <h3>Migrations</h3>
    <p class="muted">Apply database migration scripts when pulling backend changes.</p>
  </a>
</div>

<div class="card">
  <h2>Quick reminders</h2>
  <ul class="kant-checklist">
    <li>Use RU and EN fields together so the language switcher stays complete.</li>
    <li>After pulling backend updates, run migrations once in the admin panel.</li>
    <li>Use uploaded files whenever possible to avoid dead external links.</li>
  </ul>
</div>
<?php
admin_footer();
