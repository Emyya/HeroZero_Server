-- Hideout (esconderijo) — build 257.
-- Schema proprio: as tabelas oficiais nunca aparecem nas capturas HAR (o servidor
-- oficial nao as expoe), mas o payload DOHideout e conhecido ao detalhe
-- (docs/hideout/extract/modelo_dados.md) e a implementacao flash 129 usa exatamente
-- esta forma relacional (dbschema/Hideout.php, HideoutBattle.php, HideoutRoom.php).
--
-- ⚠️ Base de dados JA existente: correr este ficheiro a mao apaga os dados antigos
-- do hideout (a tabela `hideout_rooms` anterior era do stub quebrado). Em volume
-- novo o docker-entrypoint executa-o sozinho.
--
-- Codificacao da grelha `room_slot_<nivel>_<slot>` (12 pisos x 5 slots):
--   ausente/-1 = bloqueado | 0 = vazio desbloqueado | >0 = id da sala colocada
--   <=-2 = desbloqueio em curso, valor = -(ts_activity_end do unlock)
-- Piso 0 todo desbloqueado; pisos 1..11 bloqueados.

DROP TABLE IF EXISTS `hideout_rooms`;
DROP TABLE IF EXISTS `hideout_battles`;
DROP TABLE IF EXISTS `hideout`;

CREATE TABLE IF NOT EXISTS `hideout` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `hideout_points` int(11) NOT NULL DEFAULT 0,
  `current_level` int(11) NOT NULL DEFAULT 0,
  `idle_worker_count` int(11) NOT NULL DEFAULT 1,
  `max_worker_count` int(11) NOT NULL DEFAULT 1,
  `ts_time_worker_expires` int(11) NOT NULL DEFAULT 0,
  `current_resource_glue` int(11) NOT NULL DEFAULT 10,
  `max_resource_glue` int(11) NOT NULL DEFAULT 0,
  `current_resource_stone` int(11) NOT NULL DEFAULT 10,
  `max_resource_stone` int(11) NOT NULL DEFAULT 0,
  `current_attacker_units` int(11) NOT NULL DEFAULT 0,
  `max_attacker_units` int(11) NOT NULL DEFAULT 0,
  `current_defender_units` int(11) NOT NULL DEFAULT 0,
  `max_defender_units` int(11) NOT NULL DEFAULT 0,
  `current_opponent_id` int(11) NOT NULL DEFAULT 0,
  `current_opponent_chest_reward` tinyint(1) NOT NULL DEFAULT 0,
  `ts_last_opponent_refresh` int(11) NOT NULL DEFAULT 0,
  `active_battle_id` int(11) NOT NULL DEFAULT 0,
  `ts_last_lost_attack` int(11) NOT NULL DEFAULT 0,
  `current_worker_level` int(11) NOT NULL DEFAULT 0,
  `current_wall_level` int(11) NOT NULL DEFAULT 0,
  `current_barracks_level` int(11) NOT NULL DEFAULT 0,
  `max_barracks_level` int(11) NOT NULL DEFAULT 0,
  `current_gem_production_level` int(11) NOT NULL DEFAULT 0,
  `current_broker_level` int(11) NOT NULL DEFAULT 0,
  `current_robot_storage_level` int(11) NOT NULL DEFAULT 0,
  `missed_attacks` int(11) NOT NULL DEFAULT 0,
  `room_slot_0_0` int(11) NOT NULL DEFAULT 0,
  `room_slot_0_1` int(11) NOT NULL DEFAULT 0,
  `room_slot_0_2` int(11) NOT NULL DEFAULT 0,
  `room_slot_0_3` int(11) NOT NULL DEFAULT 0,
  `room_slot_0_4` int(11) NOT NULL DEFAULT 0,
  `room_slot_1_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_1_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_1_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_1_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_1_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_2_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_2_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_2_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_2_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_2_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_3_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_3_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_3_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_3_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_3_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_4_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_4_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_4_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_4_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_4_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_5_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_5_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_5_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_5_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_5_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_6_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_6_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_6_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_6_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_6_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_7_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_7_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_7_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_7_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_7_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_8_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_8_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_8_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_8_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_8_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_9_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_9_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_9_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_9_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_9_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_10_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_10_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_10_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_10_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_10_4` int(11) NOT NULL DEFAULT -1,
  `room_slot_11_0` int(11) NOT NULL DEFAULT -1,
  `room_slot_11_1` int(11) NOT NULL DEFAULT -1,
  `room_slot_11_2` int(11) NOT NULL DEFAULT -1,
  `room_slot_11_3` int(11) NOT NULL DEFAULT -1,
  `room_slot_11_4` int(11) NOT NULL DEFAULT -1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `character_id` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Batalhas hideout vs hideout. Colunas = payload real capturado do oficial
-- (docs/hideout/extract/respostas_capturadas.md); profiles/rounds/rewards ficam
-- como STRINGS JSON tal como o cliente espera no payload.
CREATE TABLE IF NOT EXISTS `hideout_battles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attacker_hideout_id` int(11) NOT NULL,
  `defender_hideout_id` int(11) NOT NULL,
  `defender_server_id` varchar(16) NOT NULL DEFAULT '',
  `attacker_status` tinyint(4) NOT NULL DEFAULT 0,
  `defender_status` tinyint(4) NOT NULL DEFAULT 0,
  `attacker_count` int(11) NOT NULL DEFAULT 0,
  `defender_count` int(11) NOT NULL DEFAULT 0,
  `attacker_profiles` mediumtext NOT NULL,
  `defender_profiles` mediumtext NOT NULL,
  `rounds` mediumtext NOT NULL,
  `attacker_rewards` text NOT NULL,
  `attacker_bonus_rewards` text NOT NULL,
  `defender_rewards` text NOT NULL,
  `destroyed_attacker` int(11) NOT NULL DEFAULT 0,
  `destroyed_defender` int(11) NOT NULL DEFAULT 0,
  `hideout_winner_id` int(11) NOT NULL DEFAULT 0,
  `server_winner_id` varchar(16) NOT NULL DEFAULT '',
  `attacker_character_name` varchar(64) NOT NULL DEFAULT '',
  `defender_character_name` varchar(64) NOT NULL DEFAULT '',
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `attacker_hideout_id` (`attacker_hideout_id`),
  KEY `defender_hideout_id` (`defender_hideout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Salas do hideout. Posicao vive na grelha `hideout.room_slot_*`; aqui ficam so
-- estado/atividade/valores adicionais das salas especiais.
CREATE TABLE IF NOT EXISTS `hideout_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `hideout_id` int(11) NOT NULL DEFAULT 0,
  `identifier` varchar(64) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `level` int(11) NOT NULL DEFAULT 1,
  `current_resource_amount` int(11) NOT NULL DEFAULT 0,
  `max_resource_amount` int(11) NOT NULL DEFAULT 100,
  `ts_last_resource_change` int(11) NOT NULL DEFAULT 0,
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  `ts_activity_end` int(11) NOT NULL DEFAULT 0,
  `current_generator_level` int(11) NOT NULL DEFAULT 0,
  `additional_value_1` int(11) NOT NULL DEFAULT 0,
  `additional_value_2` text NOT NULL,
  `guild_synergy_additional_value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`),
  KEY `hideout_id` (`hideout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
