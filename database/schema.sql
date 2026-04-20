CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings (
  id INT PRIMARY KEY DEFAULT 1,
  social_youtube_url VARCHAR(500) DEFAULT '',
  social_twitter_url VARCHAR(500) DEFAULT '',
  social_instagram_url VARCHAR(500) DEFAULT '',
  social_facebook_url VARCHAR(500) DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_settings_id INT NOT NULL DEFAULT 1,
  locale VARCHAR(10) NOT NULL,
  footer_copyright TEXT NOT NULL,
  UNIQUE KEY uniq_site_settings_locale (site_settings_id, locale),
  CONSTRAINT fk_site_settings_tr_site_settings
    FOREIGN KEY (site_settings_id) REFERENCES site_settings(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS about_project (
  id INT PRIMARY KEY DEFAULT 1,
  video_url_primary VARCHAR(500) DEFAULT '',
  video_url_secondary VARCHAR(500) DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS about_project_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  about_project_id INT NOT NULL DEFAULT 1,
  language_code VARCHAR(20) NOT NULL,
  video_url VARCHAR(500) NOT NULL,
  video_alt VARCHAR(500) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_about_project_videos_about_project
    FOREIGN KEY (about_project_id) REFERENCES about_project(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS about_project_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  about_project_id INT NOT NULL DEFAULT 1,
  locale VARCHAR(10) NOT NULL,
  section_title VARCHAR(255) NOT NULL,
  sticker_text TEXT NOT NULL,
  video_title_primary VARCHAR(255) DEFAULT '',
  video_title_secondary VARCHAR(255) DEFAULT '',
  modal_body MEDIUMTEXT NOT NULL,
  UNIQUE KEY uniq_about_project_locale (about_project_id, locale),
  CONSTRAINT fk_about_project_tr_about_project
    FOREIGN KEY (about_project_id) REFERENCES about_project(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hero_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page_key VARCHAR(50) NOT NULL UNIQUE,
  subtitle_enabled TINYINT(1) NOT NULL DEFAULT 1,
  background_image_path VARCHAR(500) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS hero_sections_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hero_section_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(500) DEFAULT '',
  UNIQUE KEY uniq_hero_section_locale (hero_section_id, locale),
  CONSTRAINT fk_hero_sections_tr_hero_sections
    FOREIGN KEY (hero_section_id) REFERENCES hero_sections(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  module_number INT NOT NULL,
  sort_order INT NOT NULL,
  languages VARCHAR(255) NOT NULL,
  formats VARCHAR(255) DEFAULT '',
  list_duration_display VARCHAR(50) DEFAULT '',
  hero_background_image_path VARCHAR(500) DEFAULT '',
  presentation_file_path VARCHAR(500) DEFAULT '',
  prev_module_id INT NULL,
  next_module_id INT NULL,
  CONSTRAINT fk_modules_prev FOREIGN KEY (prev_module_id) REFERENCES modules(id) ON DELETE SET NULL,
  CONSTRAINT fk_modules_next FOREIGN KEY (next_module_id) REFERENCES modules(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS module_lecture_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  language_code VARCHAR(20) NOT NULL,
  video_url VARCHAR(500) NOT NULL,
  video_alt VARCHAR(500) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_module_lecture_videos_module
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_presentation_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  language_code VARCHAR(20) NOT NULL,
  video_url VARCHAR(500) NOT NULL,
  video_alt VARCHAR(500) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_module_presentation_videos_module
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS modules_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  title VARCHAR(255) NOT NULL,
  short_description MEDIUMTEXT NOT NULL,
  hero_kicker VARCHAR(255) DEFAULT '',
  hero_subtitle VARCHAR(500) DEFAULT '',
  lecture_label VARCHAR(255) DEFAULT '',
  lecture_title VARCHAR(255) DEFAULT '',
  lecture_video_title_primary VARCHAR(255) DEFAULT '',
  lecture_video_title_secondary VARCHAR(255) DEFAULT '',
  presentation_label VARCHAR(255) DEFAULT '',
  presentation_title VARCHAR(255) DEFAULT '',
  presentation_video_title_primary VARCHAR(255) DEFAULT '',
  literature_html MEDIUMTEXT NULL,
  UNIQUE KEY uniq_modules_locale (module_id, locale),
  CONSTRAINT fk_modules_tr_modules
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_transcripts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_module_transcripts_module
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_transcripts_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_transcript_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  UNIQUE KEY uniq_module_transcript_locale (module_transcript_id, locale),
  CONSTRAINT fk_module_transcripts_tr_module_transcripts
    FOREIGN KEY (module_transcript_id) REFERENCES module_transcripts(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  linked_publication_id INT NULL,
  custom_url VARCHAR(500) DEFAULT '',
  custom_file_path VARCHAR(500) DEFAULT '',
  custom_cover_image_path VARCHAR(500) DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_module_readings_module
    FOREIGN KEY (module_id) REFERENCES modules(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS module_readings_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_reading_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  custom_title VARCHAR(255) DEFAULT '',
  UNIQUE KEY uniq_module_reading_locale (module_reading_id, locale),
  CONSTRAINT fk_module_readings_tr_module_readings
    FOREIGN KEY (module_reading_id) REFERENCES module_readings(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS publication_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS publication_types_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  publication_type_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  name VARCHAR(255) NOT NULL,
  UNIQUE KEY uniq_publication_type_locale (publication_type_id, locale),
  CONSTRAINT fk_publication_types_tr_publication_types
    FOREIGN KEY (publication_type_id) REFERENCES publication_types(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS publications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  publication_type_id INT NOT NULL,
  cover_image_path VARCHAR(500) DEFAULT '',
  file_path VARCHAR(500) DEFAULT '',
  external_url VARCHAR(500) DEFAULT '',
  published_at DATETIME NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_publications_publication_type
    FOREIGN KEY (publication_type_id) REFERENCES publication_types(id)
    ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS publications_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  publication_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description MEDIUMTEXT NULL,
  UNIQUE KEY uniq_publication_locale (publication_id, locale),
  CONSTRAINT fk_publications_tr_publications
    FOREIGN KEY (publication_id) REFERENCES publications(id)
    ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  photo_path VARCHAR(500) NOT NULL,
  display_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS authors_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  locale VARCHAR(10) NOT NULL,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  full_name VARCHAR(255) DEFAULT '',
  affiliation VARCHAR(255) NOT NULL,
  UNIQUE KEY uniq_author_locale (author_id, locale),
  CONSTRAINT fk_authors_tr_authors
    FOREIGN KEY (author_id) REFERENCES authors(id)
    ON DELETE CASCADE
);
