<?php
declare(strict_types=1);
/** @var bool $moduleComponentsEnabled */
/** @var array $moduleComponents */
/** @var array|null $editComponent */
/** @var array $editComponentTrMap */
/** @var array $componentVideos */
/** @var array $componentTranscripts */
/** @var array|null $editComponentVideo */
/** @var bool $isComponentFormOpen */
/** @var bool $isStandaloneComponentPage */
/** @var array $editRow */
/** @var array $locales */
/** @var string $leftLocale */
/** @var string $rightLocale */
?>
<?php if ($moduleComponentsEnabled): ?>
<?php if (!$isStandaloneComponentPage): ?>
<div class="card module-section">
  <div class="kant-section-head">
    <h4><?= h(tr('Список компонентов', 'Components list')) ?></h4>
    <a class="btn" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>&component=new&component_page=1"><?= h(tr('Добавить компонент', 'Add component')) ?></a>
  </div>
  <table>
    <thead>
      <tr>
        <th class="drag-col"></th>
        <th><?= h(tr('Порядок', 'Order')) ?></th>
        <th><?= h(tr('Заголовок', 'Title')) ?></th>
        <th><?= h(tr('Название', 'Name')) ?></th>
        <th><?= h(tr('Языки', 'Languages')) ?></th>
        <th><?= h(tr('Транскрипции', 'Transcripts')) ?></th>
        <th><?= h(tr('Список литературы', 'Literature list')) ?></th>
        <th><?= h(tr('Действия', 'Actions')) ?></th>
      </tr>
    </thead>
    <tbody id="components-sortable">
      <?php foreach ($moduleComponents as $component): ?>
        <tr data-id="<?= h((string) $component['id']) ?>">
          <td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td>
          <td><?= h((string) $component['sort_order']) ?></td>
          <td><?= h((string) ($component['block_title'] ?? '')) ?></td>
          <td><?= h((string) ($component['name'] ?? '')) ?></td>
          <td><?= h((string) ($component['video_languages'] ?? '')) ?></td>
          <td><?= h(((int) ($component['has_transcripts'] ?? 0) > 0) ? tr('Да', 'Yes') : tr('Нет', 'No')) ?></td>
          <td><?= h(((int) ($component['has_literature'] ?? 0) > 0) ? tr('Да', 'Yes') : tr('Нет', 'No')) ?></td>
          <td class="actions compact-inputs">
            <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>&component=<?= h((string) $component['id']) ?>&component_page=1"><?= h(tr('Редактировать', 'Edit')) ?></a>
            <form method="post" onsubmit="return confirm('<?= h(tr('Удалить компонент?', 'Delete component?')) ?>')">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_component">
              <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
              <input type="hidden" name="component_id" value="<?= h((string) $component['id']) ?>">
              <button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" id="components-reorder-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="reorder_components">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <div id="components-reorder-ids"></div>
  </form>
</div>
<?php endif; ?>

<?php if ($isComponentFormOpen): ?>
<div class="card component-editor-card" style="margin-top:14px">
  <div class="kant-section-head">
    <h3><?= $editComponent ? h(tr('Редактирование компонента', 'Edit component')) : h(tr('Добавление компонента', 'Add component')) ?></h3>
    <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>"><?= h($isStandaloneComponentPage ? tr('Назад к модулю', 'Back to module') : tr('Назад к компонентам', 'Back to components')) ?></a>
  </div>

  <form method="post" style="margin-bottom:16px">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_component">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
    <?php if ($editComponent): ?>
      <input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>">
      <input type="hidden" name="sort_order" value="<?= h((string) ($editComponent['sort_order'] ?? 1)) ?>">
    <?php endif; ?>
    <table style="margin-bottom:12px">
      <thead><tr><th><?= h(tr('Поле', 'Field')) ?></th><th><?= h(strtoupper($leftLocale)) ?></th><th><?= h(strtoupper($rightLocale)) ?></th></tr></thead>
      <tbody>
        <tr>
          <td><strong><?= h(tr('Заголовок', 'Title')) ?></strong></td>
          <td><input name="block_title_<?= h($leftLocale) ?>" value="<?= h((string) (($editComponentTrMap[$leftLocale] ?? [])['block_title'] ?? '')) ?>"></td>
          <td><input name="block_title_<?= h($rightLocale) ?>" value="<?= h((string) (($editComponentTrMap[$rightLocale] ?? [])['block_title'] ?? '')) ?>"></td>
        </tr>
        <tr>
          <td><strong><?= h(tr('Название', 'Name')) ?></strong></td>
          <td><input name="name_<?= h($leftLocale) ?>" value="<?= h((string) (($editComponentTrMap[$leftLocale] ?? [])['name'] ?? '')) ?>"></td>
          <td><input name="name_<?= h($rightLocale) ?>" value="<?= h((string) (($editComponentTrMap[$rightLocale] ?? [])['name'] ?? '')) ?>"></td>
        </tr>
      </tbody>
    </table>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить заголовок и название', 'Save title and name')) ?></button></div>
  </form>

  <?php if ($editComponent): ?>
  <hr class="component-editor-divider">
  <div class="kant-section-head">
    <h4><?= h(tr('Список видео', 'Videos list')) ?></h4>
    <button type="button" class="btn" data-toggle-form="component-video-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <div class="table-scroll">
    <table><thead><tr><th class="drag-col"></th><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Language')) ?></th><th><?= h(tr('Видео (ссылка/файл)', 'Video (link/file)')) ?></th><th><?= h(tr('Подпись к видео', 'Video caption')) ?></th><th><?= h(tr('Действия', 'Actions')) ?></th></tr></thead>
      <tbody id="component-videos-sortable">
        <?php foreach ($componentVideos as $video): ?>
          <tr data-id="<?= h((string) $video['id']) ?>">
            <td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td>
            <td><?= h((string) $video['sort_order']) ?></td>
            <td><?= h((string) $video['language_code']) ?></td>
            <td><a href="<?= h((string) $video['video_url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) $video['video_url']) ?></a></td>
            <td><?= h((string) $video['video_alt']) ?></td>
            <td class="actions compact-inputs">
              <a class="btn btn-secondary" href="/admin/modules.php?edit=<?= h((string) $editRow['id']) ?>&component=<?= h((string) $editComponent['id']) ?>&component_video=<?= h((string) $video['id']) ?>&component_page=1"><?= h(tr('Изменить', 'Edit')) ?></a>
              <form method="post" onsubmit="return confirm('<?= h(tr('Удалить видео?', 'Delete video?')) ?>')">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_component_video">
                <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
                <input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>">
                <input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
                <input type="hidden" name="video_id" value="<?= h((string) $video['id']) ?>">
                <button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form method="post" id="component-videos-reorder-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="reorder_component_videos">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>">
    <input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
    <div id="component-videos-reorder-ids"></div>
  </form>
  <p class="inline-help"><?= h(tr('Подсказка: используйте embed URL, например https://www.youtube.com/embed/... или Vimeo player URL.', 'URL hint: use embed URL, e.g. https://www.youtube.com/embed/... or Vimeo player URL.')) ?></p>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="component-video-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= h($editComponentVideo ? 'update_component_video' : 'add_component_video') ?>">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>">
    <input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
    <?php if ($editComponentVideo): ?><input type="hidden" name="video_id" value="<?= h((string) $editComponentVideo['id']) ?>"><?php endif; ?>
    <div class="grid">
      <div><label><?= h(tr('Код языка', 'Language code')) ?></label><input name="video_language_code" placeholder="en / ru / arm" required pattern="[A-Za-z]{2,5}" value="<?= h((string) ($editComponentVideo['language_code'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Ссылка на видео (embed)', 'Video URL (embed)')) ?></label><input name="video_url" required value="<?= h((string) ($editComponentVideo['video_url'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Подпись к видео', 'Video caption')) ?></label><input name="video_alt" value="<?= h((string) ($editComponentVideo['video_alt'] ?? '')) ?>"></div>
      <div><label><?= h(tr('Порядок', 'Order')) ?></label><input type="number" name="video_sort_order" min="1" value="<?= h((string) ($editComponentVideo['sort_order'] ?? (count($componentVideos) + 1))) ?>"></div>
    </div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>

  <hr class="component-editor-divider">
  <div class="kant-section-head">
    <h4><?= h(tr('Список транскрипций', 'Transcripts list')) ?></h4>
    <button type="button" class="btn" data-toggle-form="component-transcript-add-form"><?= h(tr('Добавить +', 'Add +')) ?></button>
  </div>
  <table><thead><tr><th class="drag-col"></th><th><?= h(tr('Порядок', 'Order')) ?></th><th><?= h(tr('Язык', 'Language')) ?></th><th><?= h(tr('Файл', 'File')) ?></th><th><?= h(tr('Действие', 'Action')) ?></th></tr></thead>
    <tbody id="component-transcripts-sortable">
      <?php foreach ($componentTranscripts as $t): ?>
        <tr data-id="<?= h((string) $t['id']) ?>"><td class="drag-col"><span class="drag-handle" draggable="true" title="<?= h(tr('Перетащить', 'Drag')) ?>">☰</span></td><td><?= h((string) $t['sort_order']) ?></td><td><?= h(strtoupper((string) ($t['display_name'] ?? ''))) ?></td><td><?= h((string) $t['file_path']) ?></td><td><form method="post" onsubmit="return confirm('<?= h(tr('Удалить транскрипцию?', 'Delete transcript?')) ?>')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>"><input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>"><input type="hidden" name="transcript_id" value="<?= h((string) $t['id']) ?>"><button type="submit"><?= h(tr('Удалить', 'Delete')) ?></button></form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" style="margin-bottom:12px" class="compact-inputs" id="component-transcript-add-form" enctype="multipart/form-data" hidden>
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_transcript"><input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>"><input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>"><input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
    <div class="grid"><div><label><?= h(tr('Загрузить файл транскрипции', 'Upload transcript file')) ?></label><input type="file" name="transcript_file" accept=".pdf,.doc,.docx,.txt" required></div><div><label><?= h(tr('Язык', 'Language')) ?></label><input name="language_code" placeholder="ru / en" required pattern="[A-Za-z]{2,5}"></div></div>
    <div class="actions" style="margin-top:10px"><button type="submit"><?= h(tr('Сохранить', 'Save')) ?></button></div>
  </form>

  <hr class="component-editor-divider">
  <h4><?= h(tr('Список литературы', 'Literature list')) ?></h4>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_component">
    <input type="hidden" name="id" value="<?= h((string) $editRow['id']) ?>">
    <input type="hidden" name="component_id" value="<?= h((string) $editComponent['id']) ?>">
    <input type="hidden" name="component_page" value="<?= $isStandaloneComponentPage ? '1' : '0' ?>">
    <input type="hidden" name="sort_order" value="<?= h((string) ($editComponent['sort_order'] ?? 1)) ?>">
    <input type="hidden" name="block_title_<?= h($leftLocale) ?>" value="<?= h((string) (($editComponentTrMap[$leftLocale] ?? [])['block_title'] ?? '')) ?>">
    <input type="hidden" name="block_title_<?= h($rightLocale) ?>" value="<?= h((string) (($editComponentTrMap[$rightLocale] ?? [])['block_title'] ?? '')) ?>">
    <input type="hidden" name="name_<?= h($leftLocale) ?>" value="<?= h((string) (($editComponentTrMap[$leftLocale] ?? [])['name'] ?? '')) ?>">
    <input type="hidden" name="name_<?= h($rightLocale) ?>" value="<?= h((string) (($editComponentTrMap[$rightLocale] ?? [])['name'] ?? '')) ?>">
    <table style="margin-bottom:12px">
      <thead><tr><th><?= h(tr('Поле', 'Field')) ?></th><th><?= h(strtoupper($leftLocale)) ?></th><th><?= h(strtoupper($rightLocale)) ?></th></tr></thead>
      <tbody>
        <tr>
          <td><strong><?= h(tr('Список литературы', 'Literature list')) ?></strong></td>
          <td><textarea class="wysiwyg" rows="4" name="literature_html_<?= h($leftLocale) ?>"><?= h((string) (($editComponentTrMap[$leftLocale] ?? [])['literature_html'] ?? '')) ?></textarea></td>
          <td><textarea class="wysiwyg" rows="4" name="literature_html_<?= h($rightLocale) ?>"><?= h((string) (($editComponentTrMap[$rightLocale] ?? [])['literature_html'] ?? '')) ?></textarea></td>
        </tr>
      </tbody>
    </table>
    <div class="actions"><button type="submit"><?= h(tr('Сохранить список литературы', 'Save literature list')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
