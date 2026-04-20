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

INSERT INTO our_position (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO our_position_translations (
  our_position_id, locale, section_title, concept_title, concept_body, principles_title, principles_body, objectives_title,
  objective_1, objective_2, objective_3, objective_4
)
VALUES
  (
    1, 'en', 'Our position', 'Concept',
    'For hundreds of years, institutions of mass education have been used to inculcate the interrelated ideologies of militarism, imperialism, and nationalism. But we believe that the power of knowledge can still pave the way to peace. Therefore, we are creating an alternative educational program designed to develop skills for a critical understanding of militaristic ideologies and practices.',
    'Principles',
    'We aim to expose the underlying causes of war, and the direct connections between capitalism, imperialism, nationalism, and militarism. We believe that a clear understanding of the causes and methods of war is the foundation for resistance and the struggle for peace. We aim to participate in anti-war and anti-militarist resistance. This educational resource has been created with this goal in mind.',
    'Objectives',
    'A critical review and analysis of militaristic institutions, ideologies and practices.',
    'The creation of an alternative to the dominant historical narrative legitimizing war.',
    'The promotion of anti-war anti-militarist and peace.',
    'Research on the political economy of militarism; contribution to the development of critical militarism studies.'
  ),
  (
    1, 'ru', 'Наша позиция', 'Концепция',
    'На протяжении сотен лет институты массового образования использовались для внедрения взаимосвязанных идеологий милитаризма, империализма и национализма. Но мы убеждены, что сила знания все еще может прокладывать путь к миру. Поэтому мы создаем альтернативную образовательную программу для развития навыков критического понимания милитаристских идеологий и практик.',
    'Принципы',
    'Мы стремимся раскрыть глубинные причины войны и прямые связи между капитализмом, империализмом, национализмом и милитаризмом. Мы считаем, что ясное понимание причин и методов войны является основой сопротивления и борьбы за мир. Мы стремимся участвовать в антивоенном и антимилитаристском сопротивлении. Этот образовательный ресурс создан с этой целью.',
    'Задачи',
    'Критический обзор и анализ милитаристских институтов, идеологий и практик.',
    'Создание альтернативы доминирующему историческому нарративу, легитимирующему войну.',
    'Продвижение антивоенной, антимилитаристской и миротворческой повестки.',
    'Исследование политической экономии милитаризма; вклад в развитие критических исследований милитаризма.'
  )
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
  objective_4 = VALUES(objective_4);

INSERT INTO hero_sections (page_key)
VALUES ('modules'), ('module_detail'), ('publications')
ON DUPLICATE KEY UPDATE page_key = VALUES(page_key);
