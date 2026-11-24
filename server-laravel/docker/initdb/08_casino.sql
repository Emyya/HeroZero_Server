-- Casino desativado por defeito. DDL apenas na instalação/migração.
CREATE TABLE IF NOT EXISTS casino_control (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO casino_control (id) VALUES (1);
CREATE TABLE IF NOT EXISTS casino_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  variant VARCHAR(80) NOT NULL,
  machine_type TINYINT UNSIGNED NOT NULL,
  ts_start BIGINT NOT NULL,
  ts_end BIGINT NOT NULL,
  enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
  KEY casino_window (enabled, ts_start, ts_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS casino_wins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  character_id INT NOT NULL,
  event_id INT UNSIGNED NOT NULL DEFAULT 0,
  ts_created BIGINT NOT NULL,
  message TEXT NOT NULL,
  KEY casino_feed (event_id, ts_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS casino_collections (
  character_id INT NOT NULL,
  reward_type TINYINT UNSIGNED NOT NULL,
  identifier VARCHAR(128) NOT NULL,
  ts_collected BIGINT NOT NULL,
  PRIMARY KEY (character_id, reward_type, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS casino_rooms (
  character_id INT NOT NULL PRIMARY KEY,
  ts_seen BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
