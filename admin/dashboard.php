<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
admin_header('KANT Admin Dashboard');
?>
<div class="card">
  <h1>Dashboard</h1>
  <p class="muted">After install, all core tables already exist. Add and translate content here.</p>
</div>

<div class="card">
  <h2>Content</h2>
  <ul>
    <li><a href="/admin/modules.php">Modules (dynamic lecture tabs by language)</a></li>
    <li><a href="/admin/site-settings.php">Site settings</a></li>
    <li><a href="/admin/about-project.php">About project</a></li>
    <li><a href="/admin/our-position.php">Our position</a></li>
    <li><a href="/admin/publication-types.php">Publication types</a></li>
    <li><a href="/admin/publications.php">Publications</a></li>
    <li><a href="/admin/authors.php">Authors</a></li>
  </ul>
</div>
<div class="card">
  <h2>Maintenance</h2>
  <ul>
    <li><a href="/admin/migrate.php">Run legacy video cleanup migration</a></li>
  </ul>
</div>
<?php
admin_footer();
