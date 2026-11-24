-- Pirâmide da Sorte. Não ativa ocorrências.
CREATE TABLE IF NOT EXISTS event_draw_control (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB;
INSERT IGNORE INTO event_draw_control (id) VALUES (1);
CREATE TABLE IF NOT EXISTS event_draw_occurrences (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identifier VARCHAR(100) NOT NULL,
 ts_start BIGINT NOT NULL,
 ts_end BIGINT NOT NULL,
 enabled TINYINT NOT NULL DEFAULT 1,
 KEY idx_active (enabled,ts_start,ts_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS event_draw_progress (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 character_id INT NOT NULL,
 occurrence_id INT NOT NULL,
 identifier VARCHAR(100) NOT NULL,
 status INT NOT NULL DEFAULT 1,
 payload LONGTEXT NOT NULL,
 closed TINYINT NOT NULL DEFAULT 0,
 UNIQUE KEY uq_character_occurrence (character_id,occurrence_id),
 KEY idx_pending (character_id,closed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
