CREATE TABLE IF NOT EXISTS herocon_control (
 id TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT IGNORE INTO herocon_control (id) VALUES (1);
CREATE TABLE IF NOT EXISTS herocon_cycles (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 identifier VARCHAR(100) NOT NULL,
 ts_start INT NOT NULL,
 ts_end INT NOT NULL,
 finished TINYINT NOT NULL DEFAULT 0,
 UNIQUE KEY period (ts_start)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS herocon_contributions (
 cycle_id INT NOT NULL,
 character_id INT NOT NULL,
 guild_id INT NOT NULL,
 fractions BIGINT NOT NULL DEFAULT 0,
 ts_score INT NOT NULL,
 PRIMARY KEY (cycle_id, character_id, guild_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS herocon_views (
 character_id INT NOT NULL PRIMARY KEY,
 cycle_id INT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS herocon_rewards (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 cycle_id INT NOT NULL,
 character_id INT NOT NULL,
 kind TINYINT NOT NULL,
 rank_position INT NOT NULL,
 score BIGINT NOT NULL,
 stat_points INT NOT NULL DEFAULT 0,
 box_identifier VARCHAR(100) NOT NULL DEFAULT '',
 item_id INT NOT NULL DEFAULT 0,
 season_identifier VARCHAR(100) NOT NULL DEFAULT '',
 season_points INT NOT NULL DEFAULT 0,
 claimed TINYINT NOT NULL DEFAULT 0,
 UNIQUE KEY entitlement (cycle_id, character_id, kind),
 KEY pending (character_id, kind, claimed)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS herocon_rank_times (
 cycle_id INT NOT NULL,
 kind TINYINT NOT NULL,
 participant_id INT NOT NULL,
 score BIGINT NOT NULL,
 ts_score INT NOT NULL,
 PRIMARY KEY (cycle_id,kind,participant_id)
) ENGINE=InnoDB;
