SET @has_formats := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules_translations'
    AND COLUMN_NAME = 'formats'
);
SET @sql := IF(
  @has_formats = 0,
  'ALTER TABLE modules_translations ADD COLUMN formats VARCHAR(255) DEFAULT '''' AFTER short_description',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO modules_translations (module_id, locale, title, short_description, formats)
SELECT m.id, 'ru', '[empty]', '', COALESCE(m.formats, '')
FROM modules m
WHERE NOT EXISTS (
  SELECT 1 FROM modules_translations mt WHERE mt.module_id = m.id AND mt.locale = 'ru'
);

INSERT INTO modules_translations (module_id, locale, title, short_description, formats)
SELECT m.id, 'en', '[empty]', '', COALESCE(m.formats, '')
FROM modules m
WHERE NOT EXISTS (
  SELECT 1 FROM modules_translations mt WHERE mt.module_id = m.id AND mt.locale = 'en'
);

UPDATE modules_translations mt
JOIN modules m ON m.id = mt.module_id
SET mt.formats = COALESCE(m.formats, '')
WHERE TRIM(COALESCE(mt.formats, '')) = '';
