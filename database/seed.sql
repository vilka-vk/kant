INSERT INTO site_settings (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO site_settings_translations (site_settings_id, locale, footer_copyright)
VALUES
  (1, 'en', 'KANT Project'),
  (1, 'ru', 'Проект KANT')
ON DUPLICATE KEY UPDATE footer_copyright = VALUES(footer_copyright);

INSERT INTO about_project (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO about_project_translations (
  about_project_id, locale, section_title, sticker_text, modal_body
)
VALUES
  (1, 'en', 'About Project', 'Fill this text in admin panel.', 'Fill this modal body in admin panel.'),
  (1, 'ru', 'О проекте', 'Заполните этот текст в админке.', 'Заполните этот текст модального окна в админке.')
ON DUPLICATE KEY UPDATE
  section_title = VALUES(section_title),
  sticker_text = VALUES(sticker_text),
  modal_body = VALUES(modal_body);

INSERT INTO hero_sections (page_key)
VALUES ('modules'), ('module_detail'), ('publications')
ON DUPLICATE KEY UPDATE page_key = VALUES(page_key);
