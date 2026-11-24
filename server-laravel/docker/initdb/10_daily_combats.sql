-- Patamares de duelos e liga: contadores independentes, sem retroativos.
CREATE TABLE IF NOT EXISTS daily_duel_progress (
  character_id int NOT NULL,
  progress_date date NOT NULL,
  fight_count int NOT NULL DEFAULT 0,
  PRIMARY KEY (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS daily_duel_rewards (
  id int NOT NULL AUTO_INCREMENT,
  character_id int NOT NULL,
  reward_date date NOT NULL,
  threshold_value int NOT NULL,
  reward_type tinyint NOT NULL,
  reward_value int NOT NULL,
  reward_identifier varchar(80) NOT NULL DEFAULT '',
  rewards text NOT NULL,
  item_id int NOT NULL DEFAULT 0,
  status tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY daily_duel_character_date_threshold (character_id, reward_date, threshold_value)
) ENGINE=InnoDB AUTO_INCREMENT=1600000000 DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS daily_league_progress (
  character_id int NOT NULL,
  progress_date date NOT NULL,
  fight_count int NOT NULL DEFAULT 0,
  PRIMARY KEY (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS daily_league_rewards (
  id int NOT NULL AUTO_INCREMENT,
  character_id int NOT NULL,
  reward_date date NOT NULL,
  threshold_value int NOT NULL,
  reward_type tinyint NOT NULL,
  reward_value int NOT NULL,
  reward_identifier varchar(80) NOT NULL DEFAULT '',
  rewards text NOT NULL,
  item_id int NOT NULL DEFAULT 0,
  status tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY daily_league_character_date_threshold (character_id, reward_date, threshold_value)
) ENGINE=InnoDB AUTO_INCREMENT=1700000000 DEFAULT CHARSET=utf8mb4;

