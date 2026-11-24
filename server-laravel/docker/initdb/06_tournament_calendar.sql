CREATE TABLE IF NOT EXISTS tournament_calendar_lock (
  id TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT IGNORE INTO tournament_calendar_lock (id) VALUES (1);
