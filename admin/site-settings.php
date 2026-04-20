<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';

require_auth();
$pdo = db();
$locales = $config['app']['supported_locales'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $pdo->prepare('UPDATE site_settings SET
      social_youtube_url = :youtube,
      social_twitter_url = :twitter,
      social_instagram_url = :instagram,
      social_facebook_url = :facebook
      WHERE id = 1')->execute([
        'youtube' => (string) ($_POST['social_youtube_url'] ?? ''),
        'twitter' => (string) ($_POST['social_twitter_url'] ?? ''),
        'instagram' => (string) ($_POST['social_instagram_url'] ?? ''),
        'facebook' => (string) ($_POST['social_facebook_url'] ?? ''),
    ]);

    foreach ($locales as $locale) {
        $value = (string) ($_POST['footer_copyright_' . $locale] ?? '');
        $stmt = $pdo->prepare('INSERT INTO site_settings_translations (site_settings_id, locale, footer_copyright)
          VALUES (1, :locale, :value)
          ON DUPLICATE KEY UPDATE footer_copyright = VALUES(footer_copyright)');
        $stmt->execute(['locale' => $locale, 'value' => $value]);
    }
    redirect('/admin/site-settings.php?saved=1');
}

$settings = $pdo->query('SELECT * FROM site_settings WHERE id = 1')->fetch() ?: [];
$tr = $pdo->query('SELECT locale, footer_copyright FROM site_settings_translations WHERE site_settings_id = 1')->fetchAll();
$trMap = [];
foreach ($tr as $row) {
    $trMap[$row['locale']] = $row['footer_copyright'];
}

admin_header(tr('Настройки сайта', 'Site Settings'));
?>
<div class="card">
  <h1><?= h(tr('Настройки сайта', 'Site settings')) ?></h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div class="grid">
      <div><label>YouTube URL</label><input name="social_youtube_url" value="<?= h((string) ($settings['social_youtube_url'] ?? '')) ?>"></div>
      <div><label>Twitter URL</label><input name="social_twitter_url" value="<?= h((string) ($settings['social_twitter_url'] ?? '')) ?>"></div>
      <div><label>Instagram URL</label><input name="social_instagram_url" value="<?= h((string) ($settings['social_instagram_url'] ?? '')) ?>"></div>
      <div><label>Facebook URL</label><input name="social_facebook_url" value="<?= h((string) ($settings['social_facebook_url'] ?? '')) ?>"></div>
    </div>
    <hr style="margin:16px 0">
    <?php foreach ($locales as $locale): ?>
      <div style="margin-bottom:12px">
        <label><?= h(tr('Текст футера', 'Footer text')) ?> (<?= h(strtoupper($locale)) ?>)</label>
        <textarea rows="2" name="footer_copyright_<?= h($locale) ?>"><?= h((string) ($trMap[$locale] ?? '')) ?></textarea>
      </div>
    <?php endforeach; ?>
    <button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button>
  </form>
</div>
<?php
admin_footer();
