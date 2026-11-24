-- Login diário e eventos de bónus. Executar fora de transacções de jogo.
CREATE TABLE IF NOT EXISTS `daily_login_rewards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int NOT NULL,
  `reward_date` date NOT NULL,
  `login_day` int NOT NULL,
  `rewards` text NOT NULL,
  `item_id` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `daily_login_character_date` (`character_id`,`reward_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `bonus_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `bonus_event_days` (
  `event_id` int NOT NULL,
  `bonus_date` date NOT NULL,
  `bonus_type` int NOT NULL,
  `multiplier` int NOT NULL,
  UNIQUE KEY `bonus_date_type` (`bonus_date`,`bonus_type`), KEY `bonus_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `daily_bonus_resources` (
  `character_id` int NOT NULL,
  `quest_date` date DEFAULT NULL,
  `quest_factor` int NOT NULL DEFAULT 1,
  `training_date` date DEFAULT NULL,
  `training_factor` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
