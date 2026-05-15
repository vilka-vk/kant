INSERT INTO hero_sections (page_key, subtitle_enabled, background_image_path)
VALUES ('home', 1, 'assets/images/hero-bg.jpg')
ON DUPLICATE KEY UPDATE page_key = page_key;

INSERT INTO hero_sections_translations (hero_section_id, locale, title, subtitle)
SELECT hs.id, 'ru', 'Главная', ''
FROM hero_sections hs
WHERE hs.page_key = 'home'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO hero_sections_translations (hero_section_id, locale, title, subtitle)
SELECT hs.id, 'en', 'Home', ''
FROM hero_sections hs
WHERE hs.page_key = 'home'
ON DUPLICATE KEY UPDATE title = VALUES(title);
