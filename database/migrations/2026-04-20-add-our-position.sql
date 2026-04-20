CREATE TABLE IF NOT EXISTS our_position (
  id INT PRIMARY KEY DEFAULT 1,
  image_primary_path VARCHAR(500) DEFAULT '',
  image_secondary_path VARCHAR(500) DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS our_position_translations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  our_position_id INT NOT NULL DEFAULT 1,
  locale VARCHAR(10) NOT NULL,
  section_title VARCHAR(255) NOT NULL,
  concept_title VARCHAR(255) DEFAULT '',
  concept_body MEDIUMTEXT NOT NULL,
  principles_title VARCHAR(255) DEFAULT '',
  principles_body MEDIUMTEXT NOT NULL,
  objectives_title VARCHAR(255) DEFAULT '',
  objective_1 VARCHAR(500) DEFAULT '',
  objective_2 VARCHAR(500) DEFAULT '',
  objective_3 VARCHAR(500) DEFAULT '',
  objective_4 VARCHAR(500) DEFAULT '',
  objective_5 VARCHAR(500) DEFAULT '',
  objective_6 VARCHAR(500) DEFAULT '',
  UNIQUE KEY uniq_our_position_locale (our_position_id, locale),
  CONSTRAINT fk_our_position_tr_our_position
    FOREIGN KEY (our_position_id) REFERENCES our_position(id)
    ON DELETE CASCADE
);

INSERT INTO our_position (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);
