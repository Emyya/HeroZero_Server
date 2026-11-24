-- Progresso da season (season pass) por personagem.
-- Ausente das capturas oficiais (payload nao gravado); shape inspirada no objeto
-- `season_progress` que ja vem (estatico) no template de boot autoLoginUser.json:
-- {id, season_id, character_id, status, ts_created, ts_start, ts_end, identifier,
--  season_points, premium_unlocked, restarted}.

CREATE TABLE IF NOT EXISTS `season_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL DEFAULT 1,
  `identifier` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 2,
  `season_points` int(11) NOT NULL DEFAULT 0,
  `premium_unlocked` tinyint(1) NOT NULL DEFAULT 0,
  `restarted` tinyint(1) NOT NULL DEFAULT 0,
  `claimed_rewards` text NOT NULL,
  `unlocked_rewards` text NOT NULL COMMENT 'JSON array de ids pré-desbloqueados (status=1)',
  `ts_created` int(11) NOT NULL DEFAULT 0,
  `ts_start` int(11) NOT NULL DEFAULT 0,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `character_id` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Uma única origem para os ciclos de todas as personagens.
CREATE TABLE IF NOT EXISTS `season_calendar` (
  `id` tinyint unsigned NOT NULL,
  `ts_anchor` bigint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
