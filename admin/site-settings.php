<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/layout.php';
require __DIR__ . '/lib/uploads.php';

require_auth();
$pdo = db();
$locales = $config['app']['supported_locales'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $action = (string) ($_POST['action'] ?? 'save_settings');

    if ($action === 'save_home_page_hero') {
        $heroRow = $pdo->prepare('SELECT id, background_image_path FROM hero_sections WHERE page_key = :page_key LIMIT 1');
        $heroRow->execute(['page_key' => 'home']);
        $hero = $heroRow->fetch() ?: null;
        $previousHeroBg = trim((string) ($hero['background_image_path'] ?? ''));
        $heroBg = $previousHeroBg;
        try {
            $uploadedHero = upload_public_file('hero_home_background_file', 'hero', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
            if ($uploadedHero) {
                $heroBg = $uploadedHero;
            }
        } catch (Throwable $e) {
            redirect('/admin/site-settings.php?error=' . urlencode($e->getMessage()));
        }
        if ($hero && (int) $hero['id'] > 0) {
            $heroId = (int) $hero['id'];
            $pdo->prepare('UPDATE hero_sections SET background_image_path = :background_image_path WHERE id = :id')
                ->execute([
                    'id' => $heroId,
                    'background_image_path' => $heroBg,
                ]);
        } else {
            $pdo->prepare('INSERT INTO hero_sections (page_key, subtitle_enabled, background_image_path) VALUES (:page_key, 1, :background_image_path)')
                ->execute([
                    'page_key' => 'home',
                    'background_image_path' => $heroBg !== '' ? $heroBg : 'assets/images/hero-bg.jpg',
                ]);
            $heroId = (int) $pdo->lastInsertId();
        }
        if ($heroBg !== '' && $previousHeroBg !== '' && $heroBg !== $previousHeroBg) {
            delete_public_file($previousHeroBg);
        }
        foreach ($locales as $locale) {
            $title = $locale === 'ru' ? 'Главная' : 'Home';
            $pdo->prepare('INSERT INTO hero_sections_translations (hero_section_id, locale, title, subtitle)
              VALUES (:hero_section_id, :locale, :title, :subtitle)
              ON DUPLICATE KEY UPDATE title = VALUES(title)')
                ->execute([
                    'hero_section_id' => $heroId,
                    'locale' => $locale,
                    'title' => $title,
                    'subtitle' => '',
                ]);
        }
        redirect('/admin/site-settings.php?saved_hero=1');
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

$heroHomeStmt = $pdo->prepare('SELECT * FROM hero_sections WHERE page_key = :page_key LIMIT 1');
$heroHomeStmt->execute(['page_key' => 'home']);
$heroHome = $heroHomeStmt->fetch() ?: [];
$heroHomePreview = (string) ($heroHome['background_image_path'] ?? '');
if ($heroHomePreview !== '' && !preg_match('#^([a-z]+:)?//#i', $heroHomePreview) && !str_starts_with($heroHomePreview, '/')) {
    $heroHomePreview = '/' . $heroHomePreview;
}

admin_header(tr('Настройки сайта', 'Site Settings'));
?>
<div class="card">
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['saved_hero'])): ?><p class="ok"><?= h(tr('Hero главной сохранен.', 'Home hero saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error'])): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <h2><?= h(tr('Hero главной страницы', 'Home page hero')) ?></h2>
  <p class="muted"><?= h(tr('Фоновое изображение в первом блоке на главной.', 'Background image for the first block on the home page.')) ?></p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_home_page_hero">
    <div class="grid">
      <div><label><?= h(tr('Текущий путь', 'Current path')) ?></label><input value="<?= h((string) ($heroHome['background_image_path'] ?? '')) ?>" disabled></div>
      <div><label><?= h(tr('Загрузить изображение', 'Upload image')) ?></label><input type="file" name="hero_home_background_file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg"></div>
    </div>
    <?php if ($heroHomePreview !== ''): ?>
      <p style="margin-top:12px"><img class="table-preview" src="<?= h($heroHomePreview) ?>" alt="<?= h(tr('Превью hero', 'Hero preview')) ?>" style="max-width:320px;height:auto"></p>
    <?php endif; ?>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить hero', 'Save hero')) ?></button></div>
  </form>
  <hr>
  <h2><?= h(tr('Настройки подвала (footer)', 'Footer settings')) ?></h2>
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
    <div class="actions"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
</div>
<?php
admin_footer();
