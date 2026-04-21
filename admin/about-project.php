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
$leftLocale = $locales[0] ?? 'ru';
$rightLocale = $locales[1] ?? ($locales[0] ?? 'en');

$defaultTranslations = [
    'en' => [
        'section_title' => 'About Project',
        'sticker_text' => "People have always dreamed of peace but constantly waged war. Will it ever be possible to achieve a lasting peace? The German philosopher Immanuel Kant proposed an answer to this question in his treatise Perpetual Peace. The 300th anniversary of Kant's birth is an opportunity to consider how relevant the ideas of this thinker from Konigsberg still are today.",
        'modal_body' => 'People have always dreamed of peace but constantly waged war. Will it ever be possible to achieve a lasting peace? The German philosopher Immanuel Kant proposed an answer to this question in his treatise Perpetual Peace.',
    ],
    'ru' => [
        'section_title' => 'О проекте',
        'sticker_text' => 'Люди всегда мечтали о мире, но постоянно вели войны. Возможно ли достичь устойчивого мира? Немецкий философ Иммануил Кант предложил ответ в трактате «К вечному миру». 300-летие со дня рождения Канта — повод заново посмотреть на актуальность его идей сегодня.',
        'modal_body' => 'Люди всегда мечтали о мире, но постоянно вели войны. Возможно ли достичь устойчивого мира? Немецкий философ Иммануил Кант предложил ответ в трактате «К вечному миру».',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
    $action = (string) ($_POST['action'] ?? 'save_about');
    if ($action === 'add_about_video') {
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        try {
            $uploadedVideo = upload_public_file('video_file', 'about-videos', ['mp4', 'webm', 'ogg']);
            if ($uploadedVideo) {
                $videoUrl = $uploadedVideo;
            }
        } catch (Throwable $e) {
            redirect('/admin/about-project.php?error=' . urlencode($e->getMessage()));
        }
        if ($videoUrl === '') {
            redirect('/admin/about-project.php?error=' . urlencode('Provide video URL or upload a file.'));
        }
        $stmt = $pdo->prepare('INSERT INTO about_project_videos (about_project_id, language_code, video_url, video_alt, sort_order)
          VALUES (1, :language_code, :video_url, :video_alt, :sort_order)');
        $stmt->execute([
            'language_code' => strtolower(trim((string) ($_POST['video_language_code'] ?? 'en'))),
            'video_url' => $videoUrl,
            'video_alt' => trim((string) ($_POST['video_alt'] ?? '')),
            'sort_order' => (int) ($_POST['video_sort_order'] ?? 0),
        ]);
        redirect('/admin/about-project.php');
    }
    if ($action === 'delete_about_video') {
        $videoId = (int) ($_POST['video_id'] ?? 0);
        if ($videoId > 0) {
            $rowStmt = $pdo->prepare('SELECT video_url FROM about_project_videos WHERE id = :id LIMIT 1');
            $rowStmt->execute(['id' => $videoId]);
            $row = $rowStmt->fetch() ?: [];
            $stmt = $pdo->prepare('DELETE FROM about_project_videos WHERE id = :id');
            $stmt->execute(['id' => $videoId]);
            delete_public_file((string) ($row['video_url'] ?? ''));
        }
        redirect('/admin/about-project.php');
    }
    if ($action === 'reorder_about_videos') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            $order = 1;
            $stmt = $pdo->prepare('UPDATE about_project_videos SET sort_order = :sort_order WHERE id = :id');
            foreach ($ids as $id) {
                $stmt->execute(['sort_order' => $order++, 'id' => (int) $id]);
            }
        }
        redirect('/admin/about-project.php');
    }
    foreach ($locales as $locale) {
        $fixedSectionTitle = (string) ($defaultTranslations[$locale]['section_title'] ?? 'About Project');
        $stmt = $pdo->prepare('INSERT INTO about_project_translations
          (about_project_id, locale, section_title, sticker_text, video_title_primary, video_title_secondary, modal_body)
          VALUES (1, :locale, :section_title, :sticker_text, :video_title_primary, :video_title_secondary, :modal_body)
          ON DUPLICATE KEY UPDATE section_title=VALUES(section_title), sticker_text=VALUES(sticker_text),
          video_title_primary=VALUES(video_title_primary), video_title_secondary=VALUES(video_title_secondary), modal_body=VALUES(modal_body)');
        $stmt->execute([
            'locale' => $locale,
            'section_title' => $fixedSectionTitle,
            'sticker_text' => trim((string) ($_POST['sticker_text_' . $locale] ?? '')),
            'video_title_primary' => '',
            'video_title_secondary' => '',
            'modal_body' => trim((string) ($_POST['modal_body_' . $locale] ?? '')),
        ]);
    }
    redirect('/admin/about-project.php?saved=1');
}

$base = $pdo->query('SELECT * FROM about_project WHERE id = 1')->fetch() ?: [];
$aboutVideos = $pdo->query('SELECT * FROM about_project_videos WHERE about_project_id = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
$tr = $pdo->query('SELECT * FROM about_project_translations WHERE about_project_id = 1')->fetchAll();
$map = [];
foreach ($tr as $row) {
    $map[$row['locale']] = $row;
}
foreach ($defaultTranslations as $locale => $defaults) {
    if (!isset($map[$locale])) {
        $map[$locale] = $defaults;
    } else {
        foreach ($defaults as $key => $value) {
            if (!isset($map[$locale][$key]) || (string) $map[$locale][$key] === '') {
                $map[$locale][$key] = $value;
            }
        }
    }
}

admin_header(tr('О проекте', 'About Project'));
?>
<div class="card">
  <h1><?= h(tr('О проекте', 'About project')) ?></h1>
  <?php if (!empty($_GET['saved'])): ?><p class="ok"><?= h(tr('Сохранено.', 'Saved.')) ?></p><?php endif; ?>
  <?php if (!empty($_GET['error'])): ?><p class="err"><?= h((string) $_GET['error']) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_about">
    <hr style="margin:16px 0">
    <p class="muted"><?= h(tr('Таблица локализации: слева', 'Localization table: left column is')) ?> <?= h(strtoupper($leftLocale)) ?>, <?= h(tr('справа', 'right column is')) ?> <?= h(strtoupper($rightLocale)) ?>.</p>
    <table>
      <thead><tr><th><?= h(tr('Поле', 'Field')) ?></th><th><?= h(strtoupper($leftLocale)) ?></th><th><?= h(strtoupper($rightLocale)) ?></th></tr></thead>
      <tbody>
        <?php
          $aboutFields = [
            'sticker_text' => 'Sticker text / Текст стикера',
            'modal_body' => 'Modal body / Текст модального окна',
          ];
          foreach ($aboutFields as $key => $label):
            $leftValue = (string) ($map[$leftLocale][$key] ?? '');
            $rightValue = (string) ($map[$rightLocale][$key] ?? '');
            $isLong = in_array($key, ['sticker_text', 'modal_body'], true);
        ?>
        <tr>
          <td><strong><?= h($label) ?></strong></td>
          <td>
            <?php if ($isLong): ?>
              <textarea class="wysiwyg" rows="4" name="<?= h($key . '_' . $leftLocale) ?>"><?= h($leftValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($key . '_' . $leftLocale) ?>" value="<?= h($leftValue) ?>">
            <?php endif; ?>
          </td>
          <td>
            <?php if ($isLong): ?>
              <textarea class="wysiwyg" rows="4" name="<?= h($key . '_' . $rightLocale) ?>"><?= h($rightValue) ?></textarea>
            <?php else: ?>
              <input name="<?= h($key . '_' . $rightLocale) ?>" value="<?= h($rightValue) ?>">
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button>
  </form>
</div>
<div class="card">
  <h2><?= h(tr('Видео блока "О проекте" по языкам (динамические вкладки)', 'About project videos by language (dynamic tabs)')) ?></h2>
  <div class="kant-section-head">
    <h3><?= h(tr('Список видео', 'Videos list')) ?></h3>
    <button type="button" class="btn" data-toggle-form="about-video-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <table>
    <thead><tr><th class="drag-col"></th><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Lang')) ?></th><th>URL</th><th>Alt</th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody id="about-videos-sortable">
      <?php foreach ($aboutVideos as $video): ?>
      <tr data-id="<?= h((string) $video['id']) ?>">
        <td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td>
        <td><?= h((string) $video['sort_order']) ?></td>
        <td><?= h((string) $video['language_code']) ?></td>
        <td><?= h((string) $video['video_url']) ?></td>
        <td><?= h((string) $video['video_alt']) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('<?= h(tr('Удалить это видео?', 'Delete this video?')) ?>')">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_about_video">
            <input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>">
            <button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" id="about-videos-reorder-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="reorder_about_videos">
    <div id="about-videos-reorder-ids"></div>
  </form>
  <form method="post" class="compact-inputs" id="about-video-add-form" enctype="multipart/form-data" hidden style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="add_about_video">
    <div class="grid">
      <div><label><?= h(tr('Код языка', 'Language code')) ?></label><input name="video_language_code" placeholder="en / ru / arm / kz / de / ge / az" required></div>
      <div><label><?= h(tr('Video URL (embed или путь к файлу)', 'Video URL (embed or file path)')) ?></label><input name="video_url"></div>
      <div><label><?= h(tr('Загрузить видеофайл', 'Upload video file')) ?></label><input type="file" name="video_file" accept=".mp4,.webm,.ogg"></div>
      <div><label><?= h(tr('Alt видео (необязательно)', 'Video Alt (optional)')) ?></label><input name="video_alt"></div>
      <div><label><?= h(tr('Порядок', 'Order')) ?></label><input type="number" name="video_sort_order" min="1" value="<?= h((string) (count($aboutVideos) + 1)) ?>"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>
</div>
<script>
(function () {
  document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var formId = btn.getAttribute('data-toggle-form');
      var form = formId ? document.getElementById(formId) : null;
      if (!form) return;
      var isOpen = !form.hasAttribute('hidden');
      if (isOpen) {
        form.setAttribute('hidden', 'hidden');
      } else {
        form.removeAttribute('hidden');
      }
      btn.textContent = isOpen ? <?= json_encode(h(tr('Добавить +', 'Add +'))) ?> : <?= json_encode(h(tr('Скрыть форму', 'Hide form'))) ?>;
    });
  });
})();

(function () {
  var tbody = document.getElementById('about-videos-sortable');
  var form = document.getElementById('about-videos-reorder-form');
  var idsWrap = document.getElementById('about-videos-reorder-ids');
  var scrollKey = 'kantAboutVideosScrollY';
  var savedY = sessionStorage.getItem(scrollKey);
  if (savedY !== null) {
    window.scrollTo(0, parseInt(savedY, 10) || 0);
    sessionStorage.removeItem(scrollKey);
  }
  if (!tbody || !form || !idsWrap) return;
  var dragged = null;
  tbody.querySelectorAll('tr[data-id]').forEach(function (row) {
    var handle = row.querySelector('.drag-handle');
    if (handle) {
      handle.addEventListener('dragstart', function () { dragged = row; });
    }
    row.addEventListener('dragover', function (e) { e.preventDefault(); });
    row.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragged || dragged === row) return;
      tbody.insertBefore(dragged, row);
      idsWrap.innerHTML = '';
      tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = tr.getAttribute('data-id') || '';
        idsWrap.appendChild(input);
      });
      sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
      form.submit();
    });
  });
})();
</script>
<?php admin_footer();
