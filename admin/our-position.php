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
$fixedSectionTitles = [
    'ru' => 'Наша позиция',
    'en' => 'Our position',
];
$defaultImagePrimaryPath = '/assets/images/position-photo-1.jpg';
$defaultImageSecondaryPath = '/assets/images/position-photo-2.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    $current = $pdo->query('SELECT * FROM our_position WHERE id = 1')->fetch() ?: [];
    $imagePrimary = trim((string) ($_POST['image_primary_path'] ?? ($current['image_primary_path'] ?? '')));
    $imageSecondary = trim((string) ($_POST['image_secondary_path'] ?? ($current['image_secondary_path'] ?? '')));

    try {
        $uploadedPrimary = upload_public_file('image_primary_file', 'position-images', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        if ($uploadedPrimary) {
            $imagePrimary = $uploadedPrimary;
        }
        $uploadedSecondary = upload_public_file('image_secondary_file', 'position-images', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        if ($uploadedSecondary) {
            $imageSecondary = $uploadedSecondary;
        }
    } catch (Throwable $e) {
        redirect('/admin/our-position.php?error=' . urlencode($e->getMessage()));
    }

    $pdo->prepare('INSERT INTO our_position (id, image_primary_path, image_secondary_path)
      VALUES (1, :image_primary_path, :image_secondary_path)
      ON DUPLICATE KEY UPDATE image_primary_path = VALUES(image_primary_path), image_secondary_path = VALUES(image_secondary_path)')
        ->execute([
            'image_primary_path' => $imagePrimary,
            'image_secondary_path' => $imageSecondary,
        ]);

    foreach ($locales as $locale) {
        $payload = [
            'locale' => $locale,
            'section_title' => (string) ($fixedSectionTitles[$locale] ?? 'Our position'),
            'concept_title' => trim((string) ($_POST['concept_title_' . $locale] ?? '')),
            'concept_body' => trim((string) ($_POST['concept_body_' . $locale] ?? '')),
            'principles_title' => trim((string) ($_POST['principles_title_' . $locale] ?? '')),
            'principles_body' => trim((string) ($_POST['principles_body_' . $locale] ?? '')),
            'objectives_title' => trim((string) ($_POST['objectives_title_' . $locale] ?? '')),
            'objective_1' => trim((string) ($_POST['objective_1_' . $locale] ?? '')),
            'objective_2' => trim((string) ($_POST['objective_2_' . $locale] ?? '')),
            'objective_3' => trim((string) ($_POST['objective_3_' . $locale] ?? '')),
            'objective_4' => trim((string) ($_POST['objective_4_' . $locale] ?? '')),
            'objective_5' => trim((string) ($_POST['objective_5_' . $locale] ?? '')),
            'objective_6' => trim((string) ($_POST['objective_6_' . $locale] ?? '')),
        ];
        $pdo->prepare('INSERT INTO our_position_translations
          (our_position_id, locale, section_title, concept_title, concept_body, principles_title, principles_body, objectives_title,
           objective_1, objective_2, objective_3, objective_4, objective_5, objective_6)
          VALUES (1, :locale, :section_title, :concept_title, :concept_body, :principles_title, :principles_body, :objectives_title,
                  :objective_1, :objective_2, :objective_3, :objective_4, :objective_5, :objective_6)
          ON DUPLICATE KEY UPDATE
            section_title = VALUES(section_title),
            concept_title = VALUES(concept_title),
            concept_body = VALUES(concept_body),
            principles_title = VALUES(principles_title),
            principles_body = VALUES(principles_body),
            objectives_title = VALUES(objectives_title),
            objective_1 = VALUES(objective_1),
            objective_2 = VALUES(objective_2),
            objective_3 = VALUES(objective_3),
            objective_4 = VALUES(objective_4),
            objective_5 = VALUES(objective_5),
            objective_6 = VALUES(objective_6)')
            ->execute($payload);
    }

    redirect('/admin/our-position.php?saved=1');
}

$base = $pdo->query('SELECT * FROM our_position WHERE id = 1')->fetch() ?: [];
$needsDefaultImagePaths = trim((string) ($base['image_primary_path'] ?? '')) === ''
    || trim((string) ($base['image_secondary_path'] ?? '')) === '';
if ($needsDefaultImagePaths) {
    $base['image_primary_path'] = trim((string) ($base['image_primary_path'] ?? '')) !== ''
        ? (string) $base['image_primary_path']
        : $defaultImagePrimaryPath;
    $base['image_secondary_path'] = trim((string) ($base['image_secondary_path'] ?? '')) !== ''
        ? (string) $base['image_secondary_path']
        : $defaultImageSecondaryPath;
    $pdo->prepare('INSERT INTO our_position (id, image_primary_path, image_secondary_path)
      VALUES (1, :image_primary_path, :image_secondary_path)
      ON DUPLICATE KEY UPDATE image_primary_path = VALUES(image_primary_path), image_secondary_path = VALUES(image_secondary_path)')
        ->execute([
            'image_primary_path' => (string) $base['image_primary_path'],
            'image_secondary_path' => (string) $base['image_secondary_path'],
        ]);
}
$trs = $pdo->query('SELECT * FROM our_position_translations WHERE our_position_id = 1')->fetchAll();
$map = [];
foreach ($trs as $row) {
    $map[$row['locale']] = $row;
}

$leftLocale = $locales[0] ?? 'ru';
$rightLocale = $locales[1] ?? ($locales[0] ?? 'en');

admin_header(tr('Наша позиция', 'Our position'));
?>
<div class="card">
  <h1><?= h(tr('Наша позиция', 'Our position')) ?></h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error'])): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <p class="muted"><?= h(tr('Таблица локализации: слева', 'Localization table: left column is')) ?> <?= h(strtoupper($leftLocale)) ?>, <?= h(tr('справа', 'right column is')) ?> <?= h(strtoupper($rightLocale)) ?>.</p>
    <table>
      <thead>
        <tr><th><?= h(tr('Поле', 'Field')) ?></th><th><?= h(strtoupper($leftLocale)) ?></th><th><?= h(strtoupper($rightLocale)) ?></th></tr>
      </thead>
      <tbody>
        <?php
        $fields = [
            'concept_title' => 'Concept title',
            'concept_body' => 'Concept body',
            'principles_title' => 'Principles title',
            'principles_body' => 'Principles body',
            'objectives_title' => 'Objectives title',
            'objective_1' => 'Objective 1',
            'objective_2' => 'Objective 2',
            'objective_3' => 'Objective 3',
            'objective_4' => 'Objective 4',
            'objective_5' => 'Objective 5',
            'objective_6' => 'Objective 6',
        ];
        foreach ($fields as $key => $label):
            $leftValue = (string) ($map[$leftLocale][$key] ?? '');
            $rightValue = (string) ($map[$rightLocale][$key] ?? '');
            $isLong = str_ends_with($key, '_body');
        ?>
        <tr>
          <td><strong><?= h($label) ?></strong></td>
          <td>
            <?php if ($isLong): ?>
              <textarea rows="4" name="<?= h($key . '_' . $leftLocale) ?>"><?= h($leftValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($key . '_' . $leftLocale) ?>" value="<?= h($leftValue) ?>">
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isLong): ?>
              <textarea rows="4" name="<?= h($key . '_' . $rightLocale) ?>"><?= h($rightValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($key . '_' . $rightLocale) ?>" value="<?= h($rightValue) ?>">
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <hr style="margin:16px 0">
    <div class="card" style="margin-bottom:0">
      <h3 style="margin-top:0"><?= h(tr('Изображения блока', 'Block images')) ?></h3>
      <p class="muted" style="margin-top:0"><?= h(tr(
          'Рекомендуем менять эти изображения только при необходимости: текущие файлы соответствуют утвержденному дизайну страницы.',
          'We recommend updating these images only when necessary: the current files match the approved page design.'
      )) ?></p>
      <div class="grid">
        <div>
          <label><?= h(tr('Путь к изображению 1', 'Image 1 path')) ?></label>
          <input name="image_primary_path" value="<?= h((string) ($base['image_primary_path'] ?? '')) ?>">
          <label style="margin-top:8px"><?= h(tr('Загрузить изображение 1', 'Upload image 1')) ?></label>
          <input type="file" name="image_primary_file" accept=".jpg,.jpeg,.png,.webp,.gif">
        </div>
        <div>
          <label><?= h(tr('Путь к изображению 2', 'Image 2 path')) ?></label>
          <input name="image_secondary_path" value="<?= h((string) ($base['image_secondary_path'] ?? '')) ?>">
          <label style="margin-top:8px"><?= h(tr('Загрузить изображение 2', 'Upload image 2')) ?></label>
          <input type="file" name="image_secondary_file" accept=".jpg,.jpeg,.png,.webp,.gif">
        </div>
      </div>
    </div>
    <div class="actions" style="margin-top:12px">
      <button type="submit"><?= h(tr('Сохранить блок "Наша позиция"', 'Save our position')) ?></button>
    </div>
  </form>
</div>
<?php admin_footer();
