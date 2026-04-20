SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'lecture_enabled'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN lecture_enabled', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'lecture_video_url_primary'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN lecture_video_url_primary', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'lecture_video_url_secondary'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN lecture_video_url_secondary', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'presentation_enabled'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN presentation_enabled', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'presentation_video_url_primary'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN presentation_video_url_primary', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules'
    AND COLUMN_NAME = 'presentation_video_alt'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules DROP COLUMN presentation_video_alt', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'modules_translations'
    AND COLUMN_NAME = 'lecture_video_url'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE modules_translations DROP COLUMN lecture_video_url', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
