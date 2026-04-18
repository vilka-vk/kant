ALTER TABLE modules
  DROP COLUMN lecture_enabled,
  DROP COLUMN lecture_video_url_primary,
  DROP COLUMN lecture_video_url_secondary,
  DROP COLUMN presentation_enabled,
  DROP COLUMN presentation_video_url_primary,
  DROP COLUMN presentation_video_alt;

ALTER TABLE modules_translations
  DROP COLUMN lecture_video_url;
