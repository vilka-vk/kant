CREATE TABLE IF NOT EXISTS module_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  presentation_file_path VARCHAR(500) DEFAULT '',
  CONSTRAINT fk_module_components_module
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_components_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_component_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  block_title VARCHAR(255) DEFAULT '',
  name VARCHAR(500) DEFAULT '',
  literature_html MEDIUMTEXT NULL,
  UNIQUE KEY uniq_module_component_locale (module_component_id, locale),
  CONSTRAINT fk_module_components_tr_component
    FOREIGN KEY (module_component_id) REFERENCES module_components(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_component_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_component_id INT NOT NULL,
  language_code VARCHAR(20) NOT NULL,
  video_url VARCHAR(500) NOT NULL,
  video_alt VARCHAR(500) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_module_component_videos_component
    FOREIGN KEY (module_component_id) REFERENCES module_components(id)
    ON DELETE CASCADE
);

ALTER TABLE module_transcripts
  ADD COLUMN module_component_id INT NULL AFTER module_id;

INSERT INTO module_components (module_id, sort_order, presentation_file_path)
SELECT m.id, 1, ''
FROM modules m
WHERE EXISTS (SELECT 1 FROM module_lecture_videos lv WHERE lv.module_id = m.id)
   OR EXISTS (
     SELECT 1 FROM modules_translations mt
     WHERE mt.module_id = m.id
       AND (
         TRIM(COALESCE(mt.lecture_title, '')) <> ''
         OR TRIM(COALESCE(mt.literature_html, '')) <> ''
       )
   )
   OR EXISTS (SELECT 1 FROM module_transcripts t WHERE t.module_id = m.id);

INSERT INTO module_components (module_id, sort_order, presentation_file_path)
SELECT m.id, 2, COALESCE(m.presentation_file_path, '')
FROM modules m
WHERE EXISTS (SELECT 1 FROM module_presentation_videos pv WHERE pv.module_id = m.id)
   OR TRIM(COALESCE(m.presentation_file_path, '')) <> ''
   OR EXISTS (
     SELECT 1 FROM modules_translations mt
     WHERE mt.module_id = m.id AND TRIM(COALESCE(mt.presentation_title, '')) <> ''
   );

INSERT INTO module_components_translations (module_component_id, locale, block_title, name, literature_html)
SELECT lc.id, mt.locale, COALESCE(mt.lecture_title, ''), COALESCE(mt.lecture_video_title_primary, ''), mt.literature_html
FROM module_components lc
JOIN modules_translations mt ON mt.module_id = lc.module_id
WHERE lc.sort_order = 1;

INSERT INTO module_components_translations (module_component_id, locale, block_title, name, literature_html)
SELECT pc.id, mt.locale, COALESCE(mt.presentation_title, ''), COALESCE(mt.presentation_video_title_primary, ''), NULL
FROM module_components pc
JOIN modules_translations mt ON mt.module_id = pc.module_id
WHERE pc.sort_order = 2;

INSERT INTO module_component_videos (module_component_id, language_code, video_url, video_alt, sort_order)
SELECT lc.id, lv.language_code, lv.video_url, lv.video_alt, lv.sort_order
FROM module_components lc
JOIN module_lecture_videos lv ON lv.module_id = lc.module_id
WHERE lc.sort_order = 1;

INSERT INTO module_component_videos (module_component_id, language_code, video_url, video_alt, sort_order)
SELECT pc.id, pv.language_code, pv.video_url, pv.video_alt, pv.sort_order
FROM module_components pc
JOIN module_presentation_videos pv ON pv.module_id = pc.module_id
WHERE pc.sort_order = 2;

UPDATE module_transcripts t
JOIN module_components lc ON lc.module_id = t.module_id AND lc.sort_order = 1
SET t.module_component_id = lc.id
WHERE t.module_component_id IS NULL;

UPDATE module_transcripts t
JOIN module_components fc ON fc.module_id = t.module_id
SET t.module_component_id = fc.id
WHERE t.module_component_id IS NULL
  AND fc.id = (SELECT MIN(c2.id) FROM module_components c2 WHERE c2.module_id = t.module_id);

ALTER TABLE module_transcripts
  ADD CONSTRAINT fk_module_transcripts_component
    FOREIGN KEY (module_component_id) REFERENCES module_components(id)
    ON DELETE CASCADE;
