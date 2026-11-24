<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Nucleo do hideout (esconderijo) — build 257.
 *
 * Porte do HideoutUtils.php do servidor flash 129 (referencia completa, ver
 * docs/hideout/implementar_hideout.md) com as adaptacoes obrigatorias do 257:
 *   - grelha 12 pisos x 5 slots (flash: 4x5);
 *   - `unlock_with_mainbuilding` VETOR indexado pela copia da sala;
 *   - curva de instant finish {short,medium,long,very_long} + teto
 *     hideout_skip_max_premium_amount=499;
 *   - guild_competition_point_production e sala de AUTO-producao;
 *   - master data lida do constants_json.data do CDN (mesma fonte do cliente),
 *     nao das constants do boot — as capturas oficiais trazem essas chaves a null.
 *
 * Contratos de payload: docs/hideout/extract/ (modelo_dados.md, acoes_protocolo.md).
 */
final class Hideout
{
    public const MAX_LEVELS = 12; // pisos da grelha (257)
    public const MAX_SLOTS  = 5;

    public const STATUS_IDLE      = 1;
    public const STATUS_BUILDING  = 2;
    public const STATUS_UPGRADING = 3;
    public const STATUS_PLACING   = 4;
    public const STATUS_STORING   = 5;
    public const STATUS_PRODUCING = 6;

    // Categorias do 257 (cliente: get_isAutoProductionRoom / get_isManuallyProductionRoom).
    // NOTA: guild_competition_point_production e AUTO no 257 (no flash nem existia).
    public const AUTO_PRODUCTION_ROOMS = [
        'main_building', 'glue_production', 'stone_production', 'xp_production',
        'guild_competition_point_production',
    ];
    public const MANUAL_PRODUCTION_ROOMS = [
        'attacker_production', 'defender_production', 'gem_production',
        'exchange_room', 'gym', 'blacksmith',
        'guild_auto_join_production', 'guild_missile_production',
        'guild_medikit_production', 'guild_weapon_oil_production',
    ];

    public const BATTLE_STATUS_FOUGHT            = 1;
    public const BATTLE_STATUS_FINISHED          = 2;
    public const BATTLE_STATUS_REWARDS_PROCESSED = 3;

    public const ROUND_DODGED   = 1;
    public const ROUND_NORMAL   = 2;
    public const ROUND_CRITICAL = 3;

    /** Colunas mutaveis da tabela `hideout` (sem id/character_id), para o save. */
    private const HIDEOUT_COLS = [
        'hideout_points', 'current_level', 'idle_worker_count', 'max_worker_count',
        'ts_time_worker_expires',
        'current_resource_glue', 'max_resource_glue', 'current_resource_stone', 'max_resource_stone',
        'current_attacker_units', 'max_attacker_units', 'current_defender_units', 'max_defender_units',
        'current_opponent_id', 'current_opponent_chest_reward', 'ts_last_opponent_refresh',
        'active_battle_id', 'ts_last_lost_attack',
        'current_worker_level', 'current_wall_level', 'current_barracks_level', 'max_barracks_level',
        'current_gem_production_level', 'current_broker_level', 'current_robot_storage_level',
        'missed_attacks',
        'room_slot_0_0', 'room_slot_0_1', 'room_slot_0_2', 'room_slot_0_3', 'room_slot_0_4',
        'room_slot_1_0', 'room_slot_1_1', 'room_slot_1_2', 'room_slot_1_3', 'room_slot_1_4',
        'room_slot_2_0', 'room_slot_2_1', 'room_slot_2_2', 'room_slot_2_3', 'room_slot_2_4',
        'room_slot_3_0', 'room_slot_3_1', 'room_slot_3_2', 'room_slot_3_3', 'room_slot_3_4',
        'room_slot_4_0', 'room_slot_4_1', 'room_slot_4_2', 'room_slot_4_3', 'room_slot_4_4',
        'room_slot_5_0', 'room_slot_5_1', 'room_slot_5_2', 'room_slot_5_3', 'room_slot_5_4',
        'room_slot_6_0', 'room_slot_6_1', 'room_slot_6_2', 'room_slot_6_3', 'room_slot_6_4',
        'room_slot_7_0', 'room_slot_7_1', 'room_slot_7_2', 'room_slot_7_3', 'room_slot_7_4',
        'room_slot_8_0', 'room_slot_8_1', 'room_slot_8_2', 'room_slot_8_3', 'room_slot_8_4',
        'room_slot_9_0', 'room_slot_9_1', 'room_slot_9_2', 'room_slot_9_3', 'room_slot_9_4',
        'room_slot_10_0', 'room_slot_10_1', 'room_slot_10_2', 'room_slot_10_3', 'room_slot_10_4',
        'room_slot_11_0', 'room_slot_11_1', 'room_slot_11_2', 'room_slot_11_3', 'room_slot_11_4',
    ];

    private const ROOM_COLS = [
        'identifier', 'status', 'level', 'current_resource_amount', 'max_resource_amount',
        'ts_last_resource_change', 'ts_creation', 'ts_activity_end', 'current_generator_level',
        'additional_value_1', 'additional_value_2', 'guild_synergy_additional_value',
    ];

    // ------------------------------------------------------------------
    // Master data (constants_json.data do CDN — mesma fonte que o cliente)
    // ------------------------------------------------------------------

    private static ?array $mdCache = null;

    /** Fatia de constants relevante ao hideout, cacheada por mtime:size do asset. */
    public static function md(): array
    {
        if (self::$mdCache !== null) {
            return self::$mdCache;
        }
        $src = __DIR__ . '/../../public/assets/data/constants_json.data';
        if (!is_file($src)) {
            $src = __DIR__ . '/../../../assets/data/constants_json.data';
        }
        return self::$mdCache = AssetCache::slice(
            'hideout_constants',
            is_file($src) ? $src : '',
            fn(): array => self::buildMd()
        );
    }

    private static function buildMd(): array
    {
        $keepExact = [
            'hideout_rooms', 'hideout_units', 'hideout_expansion', 'hideout_room_upgrade',
            'hideout_generator_upgrades', 'hideout_room_blacksmith_items',
            'hideout_room_blacksmith_product', 'boosters',
        ];
        $out = [];
        foreach ([
            __DIR__ . '/../../public/assets/data/constants_json.data',
            __DIR__ . '/../../../assets/data/constants_json.data',
        ] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                break;
            }
            $json = @gzuncompress($raw);
            if ($json === false) {
                break;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                break;
            }
            foreach ($data as $k => $v) {
                if (in_array($k, $keepExact, true)
                    || str_starts_with($k, 'hideout_')
                    || str_starts_with($k, 'item_improvement_')
                    || str_starts_with($k, 'battle_')) {
                    $out[$k] = $v;
                }
            }
            break;
        }
        return $out;
    }

    /** Constante escalar da master data com fallback. */
    public static function c(string $key, float|int|bool|string|null $default = 0): mixed
    {
        $v = self::md()[$key] ?? null;
        return $v === null ? $default : $v;
    }

    public static function roomConfig(string $identifier): ?array
    {
        $cfg = self::md()['hideout_rooms'][$identifier] ?? null;
        return is_array($cfg) ? $cfg : null;
    }

    public static function roomLevelConfig(string $identifier, int $level): ?array
    {
        $cfg = self::md()['hideout_rooms'][$identifier]['levels'][$level] ?? null;
        return is_array($cfg) ? $cfg : null;
    }

    public static function unitConfig(string $unitType, int $level): ?array
    {
        $levels = self::md()['hideout_units'][$unitType]['levels'] ?? [];
        $cfg = $levels[$level] ?? $levels[1] ?? null;
        return is_array($cfg) ? $cfg : null;
    }

    public static function getRoomSize(string $identifier): int
    {
        return max(1, (int)(self::roomConfig($identifier)['size'] ?? 1));
    }

    public static function getRoomLimit(string $identifier): int
    {
        return (int)(self::roomConfig($identifier)['limit'] ?? 0);
    }

    public static function isAutoProductionRoom(string $identifier): bool
    {
        return in_array($identifier, self::AUTO_PRODUCTION_ROOMS, true);
    }

    public static function isManualProductionRoom(string $identifier): bool
    {
        return in_array($identifier, self::MANUAL_PRODUCTION_ROOMS, true);
    }

    public static function isProductionRoom(string $identifier): bool
    {
        return self::isAutoProductionRoom($identifier) || self::isManualProductionRoom($identifier);
    }

    /** Nivel minimo do personagem para subir uma sala AO nivel $targetLevel. */
    public static function getUpgradeCharacterMinLevel(int $targetLevel): int
    {
        $cfg = self::md()['hideout_room_upgrade'][$targetLevel] ?? null;
        return (int)(is_array($cfg) ? ($cfg['character_min_level'] ?? 0) : 0);
    }

    /**
     * Nivel total do hideout = soma (limit x niveis) de todas as salas
     * (mesma conta de getMaxHideoutLevel do flash/cliente).
     */
    public static function getMaxHideoutLevel(): int
    {
        static $maxLevel = null;
        if ($maxLevel === null) {
            $maxLevel = 0;
            foreach (self::md()['hideout_rooms'] ?? [] as $cfg) {
                if (!is_array($cfg)) continue;
                $maxLevel += (int)($cfg['limit'] ?? 0) * (is_array($cfg['levels'] ?? null) ? count($cfg['levels']) : 0);
            }
        }
        return $maxLevel;
    }

    // ------------------------------------------------------------------
    // Linhas de BD
    // ------------------------------------------------------------------

    public static function rowFor(int $characterId): ?array
    {
        return Db::row('SELECT * FROM `hideout` WHERE character_id = ?', [$characterId]);
    }

    public static function roomsFor(int $characterId): array
    {
        return array_map([self::class, 'repairManualRoomState'],
            Db::rows('SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id', [$characterId]));
    }

    public static function roomById(int $characterId, int $roomId): ?array
    {
        $row = Db::row('SELECT * FROM `hideout_rooms` WHERE character_id = ? AND id = ?', [$characterId, $roomId]);
        return $row === null ? null : self::repairManualRoomState($row);
    }

    /** Manual production always has a positive deadline; zero is an old construction bug. */
    public static function normalizeManualRoomState(array $room): array
    {
        if (self::isManualProductionRoom((string)$room['identifier'])
            && (int)$room['status'] === self::STATUS_PRODUCING && (int)$room['ts_activity_end'] === 0) {
            $room['status'] = self::STATUS_IDLE;
            $room['current_resource_amount'] = 0;
            $room['max_resource_amount'] = 0;
        }
        return $room;
    }

    private static function repairManualRoomState(array $room): array
    {
        $fixed = self::normalizeManualRoomState($room);
        if ($fixed !== $room) {
            Db::exec('UPDATE hideout_rooms SET status = ?, current_resource_amount = 0, max_resource_amount = 0
                WHERE id = ? AND character_id = ? AND status = ? AND ts_activity_end = 0',
                [self::STATUS_IDLE, (int)$room['id'], (int)$room['character_id'], self::STATUS_PRODUCING]);
        }
        return $fixed;
    }

    /**
     * Bónus passivos do clube de luta privado (personal_arena).
     * Bonus 1 = +N flat ao max_duel_stamina (Coragem);
     * Bonus 2 = +N flat ao max_league_stamina (Adrenalina).
     * Devolve zeros se a sala não estiver colocada na grelha.
     */
    public static function personalArenaBonuses(int $characterId): array
    {
        $zero = ['duel_stamina' => 0, 'league_stamina' => 0];
        $row = Db::row(
            'SELECT hr.id, hr.level FROM `hideout_rooms` hr
             WHERE hr.character_id = ? AND hr.identifier = ? LIMIT 1',
            [$characterId, 'personal_arena']
        );
        if ($row === null) return $zero;
        $h = Db::row('SELECT * FROM `hideout` WHERE character_id = ?', [$characterId]);
        if ($h === null || !self::isRoomPlaced($h, (int)$row['id'])) return $zero;
        $lvl = max(1, (int)$row['level']);
        $cfg = self::roomLevelConfig('personal_arena', $lvl);
        return [
            'duel_stamina'   => (int)($cfg['passiv_bonus_amount_1'] ?? 0),
            'league_stamina' => (int)($cfg['passiv_bonus_amount_2'] ?? 0),
        ];
    }

    /**
     * Bónus passivos do laboratório cibernético (kybernetic_improvement).
     * Bonus 1 = % de aumento das stats base; Bonus 2 = pontos flat adicionados
     * ao budget dos itens gerados na loja. Devolve zeros se a sala não existir.
     */
    public static function kyberneticBonuses(int $characterId): array
    {
        $zero = ['stats_pct' => 0, 'item_flat' => 0];
        $row = Db::row(
            'SELECT hr.id, hr.level FROM `hideout_rooms` hr
              JOIN `hideout` h ON h.character_id = hr.character_id
             WHERE hr.character_id = ? AND hr.identifier = ? LIMIT 1',
            [$characterId, 'kybernetic_improvement']
        );
        if ($row === null) return $zero;
        // Só dá bónus se a sala estiver efectivamente colocada na grelha
        $h = Db::row('SELECT * FROM `hideout` WHERE character_id = ?', [$characterId]);
        if ($h === null || !self::isRoomPlaced($h, (int)$row['id'])) return $zero;
        $lvl = max(1, (int)$row['level']);
        $cfg = self::roomLevelConfig('kybernetic_improvement', $lvl);
        return [
            'stats_pct' => (int)($cfg['passiv_bonus_amount_1'] ?? 0),
            'item_flat'  => (int)($cfg['passiv_bonus_amount_2'] ?? 0),
        ];
    }

    /** Cria a row do hideout com os bases oficiais (worker=1, glue/stone=10). */
    public static function createRow(int $characterId): array
    {
        Db::exec('INSERT INTO `hideout` (character_id) VALUES (?)', [$characterId]);
        return self::rowFor($characterId);
    }

    /** Grava a row do hideout (todas as colunas mutaveis). Devolve rows afetadas. */
    public static function saveHideout(array $row): int
    {
        $sets = [];
        $args = [];
        foreach (self::HIDEOUT_COLS as $col) {
            $sets[] = "`$col` = ?";
            $args[] = $row[$col] ?? 0;
        }
        $args[] = (int)$row['id'];
        return Db::exec('UPDATE `hideout` SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    }

    /** Grava uma sala. */
    public static function saveRoom(array $room): int
    {
        $sets = [];
        $args = [];
        foreach (self::ROOM_COLS as $col) {
            $sets[] = "`$col` = ?";
            $args[] = $room[$col] ?? '';
        }
        $args[] = (int)$room['id'];
        return Db::exec('UPDATE `hideout_rooms` SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    }

    public static function saveRooms(array $rooms): void
    {
        foreach ($rooms as $room) {
            self::saveRoom($room);
        }
    }

    /** Cria uma sala NOVA (build) e devolve a row completa (id incluido). */
    public static function createRoom(int $characterId, int $hideoutId, string $identifier, int $maxResourceAmount, int $tsActivityEnd): array
    {
        $now = GameTime::now();
        Db::exec(
            'INSERT INTO `hideout_rooms`
                (character_id, hideout_id, identifier, status, level, current_resource_amount,
                 max_resource_amount, ts_last_resource_change, ts_creation, ts_activity_end, current_generator_level)
             VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?, ?, 0)',
            [$characterId, $hideoutId, $identifier, self::STATUS_BUILDING, $maxResourceAmount, $now, $now, $tsActivityEnd]
        );
        return Db::row(
            'SELECT * FROM `hideout_rooms` WHERE character_id = ? ORDER BY id DESC LIMIT 1',
            [$characterId]
        );
    }

    // ------------------------------------------------------------------
    // Grelha de slots
    // ------------------------------------------------------------------

    public static function slotKey(int $level, int $slot): string
    {
        return "room_slot_{$level}_{$slot}";
    }

    public static function isSlotAreaFree(array $h, int $level, int $startSlot, int $size): bool
    {
        if ($level < 0 || $level >= self::MAX_LEVELS) {
            return false;
        }
        if ($startSlot < 0 || ($startSlot + $size) > self::MAX_SLOTS) {
            return false;
        }
        for ($i = 0; $i < $size; $i++) {
            if ((int)($h[self::slotKey($level, $startSlot + $i)] ?? -1) !== 0) {
                return false;
            }
        }
        return true;
    }

    public static function assignRoomToSlots(array &$h, int $level, int $startSlot, int $size, int $roomId): void
    {
        for ($i = 0; $i < $size; $i++) {
            $h[self::slotKey($level, $startSlot + $i)] = $roomId;
        }
    }

    public static function clearRoomFromSlots(array &$h, int $roomId): void
    {
        for ($l = 0; $l < self::MAX_LEVELS; $l++) {
            for ($s = 0; $s < self::MAX_SLOTS; $s++) {
                $key = self::slotKey($l, $s);
                if ((int)($h[$key] ?? 0) === $roomId) {
                    $h[$key] = 0;
                }
            }
        }
    }

    public static function isRoomPlaced(array $h, int $roomId): bool
    {
        for ($l = 0; $l < self::MAX_LEVELS; $l++) {
            for ($s = 0; $s < self::MAX_SLOTS; $s++) {
                if ((int)($h[self::slotKey($l, $s)] ?? 0) === $roomId) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Slot [level, slot] onde a sala esta colocada, ou null. */
    public static function roomSlotAndLevel(array $h, int $roomId): ?array
    {
        for ($l = 0; $l < self::MAX_LEVELS; $l++) {
            for ($s = 0; $s < self::MAX_SLOTS; $s++) {
                if ((int)($h[self::slotKey($l, $s)] ?? 0) === $roomId) {
                    return ['level' => $l, 'slot' => $s];
                }
            }
        }
        return null;
    }

    /** Desbloqueios de slot expirados viram 0 e devolvem o worker (sweep lazy). */
    public static function sweepExpiredSlotUnlocks(array &$h): bool
    {
        $now = GameTime::now();
        $changed = false;
        for ($l = 0; $l < self::MAX_LEVELS; $l++) {
            for ($s = 0; $s < self::MAX_SLOTS; $s++) {
                $key = self::slotKey($l, $s);
                $val = (int)($h[$key] ?? -1);
                if ($val < -1 && abs($val) <= $now) {
                    $h[$key] = 0;
                    $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
                    $changed = true;
                }
            }
        }
        return $changed;
    }

    // ------------------------------------------------------------------
    // Factores / duracoes / custos
    // ------------------------------------------------------------------

    /** Reducao de duracao do worker_home (1.0 = sem reducao). */
    public static function workerSpeedFactor(array $h): float
    {
        $lvl = (int)$h['current_worker_level'];
        if ($lvl > 0) {
            $bonus = (float)(self::roomLevelConfig('worker_home', $lvl)['passiv_bonus_amount_1'] ?? 0);
            if ($bonus > 0) {
                return max(0.0, 1.0 - $bonus / 100.0);
            }
        }
        return 1.0;
    }

    public static function generatorCapacityFactor(array $room): float
    {
        return self::generatorFactor($room);
    }

    public static function generatorDurationFactor(array $room): float
    {
        return max(0.1, self::generatorFactor($room));
    }

    private static function generatorFactor(array $room): float
    {
        $lvl = (int)($room['current_generator_level'] ?? 0);
        if ($lvl > 0) {
            $bonus = (float)(self::roomLevelConfig('generator', $lvl)['passiv_bonus_amount_1'] ?? 0);
            return 1.0 + $bonus / 100.0;
        }
        return 1.0;
    }

    /**
     * Custo premium de instant finish — formula EXATA do cliente 257
     * (tb.getInstantFinishPremiumAmount): interpolacao linear em 4 segmentos,
     * teto hideout_skip_max_premium_amount.
     */
    public static function instantFinishCost(int $remainingSeconds): int
    {
        $b = 0.0;
        if ($remainingSeconds > 0) {
            $st = (float)self::c('hideout_skip_short_time', 60);
            $sp = (float)self::c('hideout_skip_short_premium_amount', 0.2);
            $mt = (float)self::c('hideout_skip_medium_time', 3600);
            $mp = (float)self::c('hideout_skip_medium_premium_amount', 4);
            $lt = (float)self::c('hideout_skip_long_time', 86400);
            $lp = (float)self::c('hideout_skip_long_premium_amount', 52);
            $vt = (float)self::c('hideout_skip_very_long_time', 604800);
            $vp = (float)self::c('hideout_skip_very_long_premium_amount', 200);
            $t  = (float)$remainingSeconds;
            $b  = $t <= $st ? $sp
                : ($t <= $mt ? (($mp - $sp) / ($mt - $st)) * ($t - $st) + $sp
                : ($t <= $lt ? (($lp - $mp) / ($lt - $mt)) * ($t - $mt) + $mp
                : (($vp - $lp) / ($vt - $lt)) * ($t - $lt) + $lp));
        }
        return min((int)ceil($b), (int)self::c('hideout_skip_max_premium_amount', 499));
    }

    public static function buildDuration(string $identifier, array $h): int
    {
        $minutes = (float)(self::roomLevelConfig($identifier, 1)['build_time'] ?? 0);
        $scaling = (float)self::c('hideout_build_duration_scaling', 1);
        return max(1, (int)ceil($minutes * 60 * self::workerSpeedFactor($h) * $scaling));
    }

    public static function upgradeDuration(string $identifier, int $currentLevel, array $h): int
    {
        $minutes = (float)(self::roomLevelConfig($identifier, $currentLevel + 1)['build_time'] ?? 0);
        $scaling = (float)self::c('hideout_upgrade_duration_scaling', 1);
        return max(1, (int)ceil($minutes * 60 * self::workerSpeedFactor($h) * $scaling));
    }

    public static function placeDuration(array $room, array $h): int
    {
        $perLevel = (float)self::c('hideout_place_duration_per_level', 10);
        return max(1, (int)ceil((int)$room['level'] * $perLevel * 60 * self::workerSpeedFactor($h)));
    }

    public static function storeDuration(array $room, array $h): int
    {
        $perLevel = (float)self::c('hideout_store_duration_per_level', 10);
        return max(1, (int)ceil((int)$room['level'] * $perLevel * 60 * self::workerSpeedFactor($h)));
    }

    /** Duracao do unlock de um slot novo (master hideout_expansion[n].duration em minutos). */
    public static function slotUnlockDuration(int $unlockedCount): int
    {
        $cfg = self::md()['hideout_expansion'][$unlockedCount] ?? null;
        $minutes = (float)(is_array($cfg) ? ($cfg['duration'] ?? 0) : 0);
        $scaling = (float)self::c('hideout_room_unlock_duration_scaling', 1);
        $seconds = (int)round($minutes * 60 * $scaling);
        return $seconds > 0 ? max(1, $seconds) : 0;
    }

    /** Custos do PROXIMO slot (index = desbloqueados + 1). */
    public static function slotExpansionCost(int $unlockedCount): array
    {
        $cfg = self::md()['hideout_expansion'][$unlockedCount + 1] ?? null;
        return [
            'gold'  => (float)(is_array($cfg) ? ($cfg['price_gold'] ?? 0) : 0),
            'glue'  => (float)(is_array($cfg) ? ($cfg['price_glue'] ?? 0) : 0),
            'stone' => (float)(is_array($cfg) ? ($cfg['price_stone'] ?? 0) : 0),
        ];
    }

    // ------------------------------------------------------------------
    // Recursos / caps / passivas
    // ------------------------------------------------------------------

    /**
     * Fator brain para xp_production: cada sala `brain` COLOCADA soma
     * passiv_bonus_amount_1/100 (cliente: get_currentBrainFactor).
     */
    public static function brainFactor(array $h, array $allRooms): float
    {
        $factor = 1.0;
        foreach ($allRooms as $room) {
            if ($room['identifier'] === 'brain' && self::isRoomPlaced($h, (int)$room['id'])) {
                $cfg = self::roomLevelConfig('brain', (int)$room['level']);
                if ($cfg) {
                    $factor += (float)($cfg['passiv_bonus_amount_1'] ?? 0) / 100.0;
                }
            }
        }
        return $factor;
    }

    /**
     * Maximo EFETIVO da sala — SO PARA LOGICA SERVER-SIDE (accrual/colecta).
     * O payload envia sempre a BASE: o cliente recalcula localmente
     * max × generator × brain × sinergia (Vf.get_maxResourceAmount), e
     * multiplicar fatores no servidor daria valores divergentes.
     */
    /**
     * Capacidade máxima de moedas da central (main_building).
     * Fórmula: 14.3 × nível^1.39 × rpm × bónus_guilda × bónus_energético_trabalho
     * rpm = resource_production_max do nível da sala (fator de escala da master data).
     * Energético de trabalho: booster_work1=10%, booster_work2=25%, booster_work3=50%.
     */
    public static function mainBuildingGoldMax(int $characterId, float $rpm): int
    {
        $char = Db::row(
            'SELECT level, guild_id, active_work_booster_id, ts_active_work_boost_expires FROM `character` WHERE id = ?',
            [$characterId]
        );
        if ($char === null) {
            return 0;
        }
        $playerLevel = max(1, (int)$char['level']);

        $guildBoostPct = 0.0;
        $guildId = (int)($char['guild_id'] ?? 0);
        if ($guildId > 0) {
            $guild = Db::row('SELECT stat_quest_game_currency_reward_boost FROM guild WHERE id = ?', [$guildId]);
            if ($guild !== null) {
                $guildBoostPct = (float)($guild['stat_quest_game_currency_reward_boost'] ?? 0) * 2.0;
            }
        }

        $workBoostPct = 0.0;
        $boosterId = (string)($char['active_work_booster_id'] ?? '');
        $boosterExpires = (int)($char['ts_active_work_boost_expires'] ?? 0);
        if ($boosterId !== '' && $boosterExpires > GameTime::now()) {
            $boosterCfg = self::md()['boosters'][$boosterId] ?? null;
            if (is_array($boosterCfg)) {
                $workBoostPct = (float)($boosterCfg['amount'] ?? 0);
            }
        }

        $base = 14.3 * pow($playerLevel, 1.39);
        $guildMultiplier = 1.0 + ($guildBoostPct / 100.0);
        $workMultiplier  = 1.0 + ($workBoostPct / 100.0);
        return (int)round($base * $rpm * $guildMultiplier * $workMultiplier);
    }

public static function effectiveMaxResourceAmount(array $room, array $h, array $allRooms): int
    {
        if ($room['identifier'] === 'main_building') {
            $cfg = self::roomLevelConfig('main_building', (int)$room['level']);
            $rpm = (float)($cfg['resource_production_max'] ?? 1.0);
            $characterId = (int)($h['character_id'] ?? 0);
            $goldMax = $characterId > 0 ? self::mainBuildingGoldMax($characterId, $rpm) : 0;
            $factor = self::generatorCapacityFactor($room);
            return (int)floor($goldMax * $factor);
        }
        $base = max(0, (int)$room['max_resource_amount']);
        if (!self::isAutoProductionRoom((string)$room['identifier'])) {
            return $base;
        }
        $factor = self::generatorCapacityFactor($room);
        if ($room['identifier'] === 'xp_production') {
            $factor *= self::brainFactor($h, $allRooms);
        }
return (int)floor($base * $factor);
    }

    /** Janela real do bónus; também permite contabilizar produção após a expiração. */
    public static function productionBoosterWindow(int $cid): array
    {
        $char=Db::row('SELECT active_hideout_booster_id,ts_active_hideout_boost_expires,ts_active_hideout_boost_started FROM `character` WHERE id=?',[$cid]);
        $id=(string)($char['active_hideout_booster_id'] ?? '');
        $amount=(int)(Live::BOOSTER_CATALOG[$id][1] ?? 0);
        return ['amount'=>$amount,'start'=>(int)($char['ts_active_hideout_boost_started'] ?? 0),'end'=>(int)($char['ts_active_hideout_boost_expires'] ?? 0)];
    }

    public static function activateProductionBooster(int $cid, string $id, int $expires): void
    {
        Missiles::transaction([$cid],static function()use($cid,$id,$expires):void {
            $h=self::rowFor($cid);
            if ($h) {
                $rooms=self::roomsFor($cid);
                foreach($rooms as $room) if (in_array($room['identifier'],['glue_production','stone_production'],true) && (int)$room['status']===self::STATUS_PRODUCING) {
                    $room['current_resource_amount']=self::autoRoomProducedAmount($room,GameTime::now(),$h,$rooms);
                    $room['ts_last_resource_change']=GameTime::now();
                    self::saveRoom($room);
                }
            }
            Db::exec('UPDATE `character` SET active_hideout_booster_id=?,ts_active_hideout_boost_expires=?,ts_active_hideout_boost_started=? WHERE id=?',[$id,$expires,GameTime::now(),$cid]);
        });
    }

    /** Accrual de auto-producao por minutos decorridos (igual ao cliente). */
    public static function autoRoomProducedAmount(array $room, int $now, array $h, array $allRooms): int
    {
        $cfg = self::roomLevelConfig($room['identifier'], (int)$room['level']);
        if (!$cfg) {
            return (int)$room['current_resource_amount'];
        }
        $minTillMax = (float)($cfg['min_till_max_resource'] ?? 1)
            * (float)self::c('hideout_min_till_max_resource_duration_scaling', 1);
        $max = max(1, self::effectiveMaxResourceAmount($room, $h, $allRooms));
        $perMinute = $minTillMax > 0 ? $max / $minTillMax : 0.0;
        $last = (int)$room['ts_last_resource_change'] > 0 ? (int)$room['ts_last_resource_change'] : $now;
        $minutes = floor(max(0, $now - $last) / 60);
        if ($minutes <= 0 && (int)$room['current_resource_amount'] <= 0) {
            return (int)$room['current_resource_amount'];
        }
        $weightedMinutes=$minutes;
        if (in_array($room['identifier'],['glue_production','stone_production'],true)) {
            $window=self::productionBoosterWindow((int)$h['character_id']);
            $end=$window['end']===-1 ? $now : $window['end'];
            $overlap=max(0,min($last+$minutes*60,$end)-max($last,$window['start']));
            if ($window['start']>0) $weightedMinutes+=($overlap/60)*($window['amount']/100);
        }
        return (int)min($max, (int)$room['current_resource_amount'] + (int)floor($perMinute * $weightedMinutes));
    }

    /** Passivas de nivel no hideout ao concluir atividade (barracks/wall/worker_home). */
    public static function applyRoomPassives(array &$h, array $room): void
    {
        switch ($room['identifier']) {
            case 'barracks':
                $h['current_barracks_level'] = (int)$room['level'];
                $h['max_barracks_level'] = max((int)$h['max_barracks_level'], (int)$room['level']);
                break;
            case 'wall':
                $h['current_wall_level'] = (int)$room['level'];
                break;
            case 'worker_home':
                $h['current_worker_level'] = (int)$room['level'];
                break;
            case 'broker':
                $h['current_broker_level'] = (int)$room['level'];
                break;
            case 'gem_production':
                $h['current_gem_production_level'] = (int)$room['level'];
                break;
            case 'robot_storage':
                $h['current_robot_storage_level'] = (int)$room['level'];
                break;
        }
    }

    public static function removeRoomPassives(array &$h, array $room): void
    {
        switch ($room['identifier']) {
            case 'barracks':
                $h['current_barracks_level'] = 0;
                break;
            case 'wall':
                $h['current_wall_level'] = 0;
                break;
            case 'worker_home':
                $h['current_worker_level'] = 0;
                break;
            case 'broker':
                $h['current_broker_level'] = 0;
                break;
            case 'gem_production':
                $h['current_gem_production_level'] = 0;
                break;
            case 'robot_storage':
                $h['current_robot_storage_level'] = 0;
                break;
        }
    }

    /**
     * Caps globais: main_building define max glue/stone (passiv_bonus_amount_1/2);
     * salas de unidades somam resource_production_max aos max de unidades.
     * So contam salas COLOCADAS fora da store. Clamp dos current aos caps.
     */
    public static function applyCaps(array &$h, array $rooms): void
    {
        $maxGlue = 0;
        $maxStone = 0;
        $maxAtk = 0;
        $maxDef = 0;
        foreach ($rooms as $room) {
            if (!self::isRoomPlaced($h, (int)$room['id'])) {
                continue;
            }
            $cfg = self::roomLevelConfig($room['identifier'], (int)$room['level']);
            if (!$cfg) {
                continue;
            }
            switch ($room['identifier']) {
                case 'main_building':
                    $maxGlue = (int)($cfg['passiv_bonus_amount_1'] ?? 0);
                    $maxStone = (int)($cfg['passiv_bonus_amount_2'] ?? 0);
                    break;
                case 'attacker_production':
                    $maxAtk += (int)($cfg['resource_production_max'] ?? 0);
                    break;
                case 'defender_production':
                    $maxDef += (int)($cfg['resource_production_max'] ?? 0);
                    break;
            }
        }
        $h['max_resource_glue'] = $maxGlue;
        $h['max_resource_stone'] = $maxStone;
        $h['max_attacker_units'] = $maxAtk;
        $h['max_defender_units'] = $maxDef;
        // Só aplica o cap quando a main_building está colocada (cap > 0).
        // Sem main_building o jogador ainda não pode construir nada — os
        // recursos iniciais do tutorial devem ser preservados.
        if ($maxGlue > 0 && (int)$h['current_resource_glue'] > $maxGlue) {
            $h['current_resource_glue'] = $maxGlue;
        }
        if ($maxStone > 0 && (int)$h['current_resource_stone'] > $maxStone) {
            $h['current_resource_stone'] = $maxStone;
        }
        if ((int)$h['current_attacker_units'] > $maxAtk) {
            $h['current_attacker_units'] = $maxAtk;
        }
        if ((int)$h['current_defender_units'] > $maxDef) {
            $h['current_defender_units'] = $maxDef;
        }
    }

    /** O maior gerador colocado replica o nivel para as salas de producao. */
    private const GENERATOR_BOOSTABLE_ROOMS = [
        'main_building', 'xp_production', 'gem_production',
        'glue_production', 'stone_production',
    ];

    public static function refreshGeneratorLevels(array $h, array &$rooms): void
    {
        // Mapa (row, col) => nível do gerador colocado nesse slot.
        $genMap = [];
        foreach ($rooms as $room) {
            if ($room['identifier'] !== 'generator') {
                continue;
            }
            $pos = self::roomSlotAndLevel($h, (int)$room['id']);
            if ($pos === null) {
                continue;
            }
            $genMap[$pos['level']][$pos['slot']] = (int)$room['level'];
        }

        foreach ($rooms as &$room) {
            if (!in_array($room['identifier'], self::GENERATOR_BOOSTABLE_ROOMS, true)) {
                $room['current_generator_level'] = 0;
                continue;
            }
            $pos = self::roomSlotAndLevel($h, (int)$room['id']);
            if ($pos === null) {
                $room['current_generator_level'] = 0;
                continue;
            }
            $row  = $pos['level'];
            $size = self::getRoomSize($room['identifier']);
            // Slots adjacentes: coluna imediatamente à esquerda e à direita da sala.
            $leftCol  = $pos['slot'] - 1;
            $rightCol = $pos['slot'] + $size;
            $best = 0;
            if ($leftCol >= 0) {
                $best = max($best, (int)($genMap[$row][$leftCol] ?? 0));
            }
            if ($rightCol < self::MAX_SLOTS) {
                $best = max($best, (int)($genMap[$row][$rightCol] ?? 0));
            }
            $room['current_generator_level'] = $best;
        }
        unset($room);
    }

    /**
     * Capacidade de unidades com o bonus do robot_storage — o cliente aplica-o
     * POR CIMA dos valores crus em ATAQUE e DEFESA (tb.getMaxAttackerUnits /
     * getMaxDefenderUnits); o payload continua CRU, so a logica interna de
     * producao/colecta respeita a capacidade real.
     */
    public static function robotBoostedUnits(array $h, int $base): int
    {
        $level = (int)$h['current_robot_storage_level'];
        if ($base <= 0 || $level <= 0) {
            return $base;
        }
        $cfg = self::roomLevelConfig('robot_storage', $level);
        if (!$cfg) {
            return $base;
        }
        $pct = (float)($cfg['passiv_bonus_amount_1'] ?? 0);
        return (int)floor($base + $base * $pct / 100.0);
    }

    /**
     * Conclui a atividade pendente de uma sala (chamar quando ts_activity_end<=now).
     * Porte direto do completeRoomActivity flash, incl. flags de tutorial/goals.
     */
    public static function completeRoomActivity(Character $char, array &$h, array &$room, array &$allRooms): void
    {
        $now = GameTime::now();
        $identifier = $room['identifier'];

        switch ((int)$room['status']) {
            case self::STATUS_BUILDING:
                $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
                $h['current_level'] = (int)$h['current_level'] + 1;
                $room['level'] = 1;
                $room['status'] = self::isAutoProductionRoom($identifier) ? self::STATUS_PRODUCING : self::STATUS_IDLE;
                $room['ts_activity_end'] = 0;
                $room['ts_last_resource_change'] = $now;
                // BASE do nivel 1 (o cliente aplica fatores localmente); round() porque resource_production_max é float.
                $level1Cfg = self::roomLevelConfig((string)$identifier, 1);
                $room['max_resource_amount'] = (int)round((float)($level1Cfg['resource_production_max'] ?? 0));
                $effectiveMax = self::effectiveMaxResourceAmount($room, $h, $allRooms);
                if (in_array($identifier, ['main_building', 'glue_production', 'stone_production'], true)) {
                    // Recursos iniciais: main/glue/stone nascem cheios.
                    $room['current_resource_amount'] = $effectiveMax;
                }
                $flags = [
                    'main_building'       => 'hideout_first_building',
                    'stone_production'    => 'hideout_build_stone',
                    'glue_production'     => 'hideout_build_glue',
                    'attacker_production' => 'hideout_build_attacker',
                ];
                if (isset($flags[$identifier])) {
                    $char->setTutorialFlag($flags[$identifier], true);
                }
                if ($identifier === 'generator') {
                    Character::bumpGoalStatFor($char->id(), 'hideout_build_generator', 1);
                }
                self::applyRoomPassives($h, $room);
                self::applyCaps($h, $allRooms);
                self::refreshGeneratorLevels($h, $allRooms);
                break;

            case self::STATUS_UPGRADING:
                $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
                $h['current_level'] = (int)$h['current_level'] + 1;
                $room['level'] = (int)$room['level'] + 1;
                $room['status'] = self::isAutoProductionRoom($identifier) ? self::STATUS_PRODUCING : self::STATUS_IDLE;
                $room['ts_activity_end'] = 0;
                $room['ts_last_resource_change'] = $now;
                // BASE do novo nivel (cliente aplica fatores dele); round() porque resource_production_max é float.
                $newCfg = self::roomLevelConfig((string)$identifier, (int)$room['level']);
                $room['max_resource_amount'] = (int)round((float)($newCfg['resource_production_max'] ?? 0));
                Character::bumpGoalStatFor($char->id(), 'hideout_room_upgraded', 1);
                self::applyRoomPassives($h, $room);
                self::applyCaps($h, $allRooms);
                self::refreshGeneratorLevels($h, $allRooms);
                break;

            case self::STATUS_PLACING:
                $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
                $h['current_level'] = (int)$h['current_level'] + (int)$room['level'];
                $room['status'] = self::isAutoProductionRoom($identifier) ? self::STATUS_PRODUCING : self::STATUS_IDLE;
                $room['ts_activity_end'] = 0;
                $room['ts_last_resource_change'] = $now;
                self::applyRoomPassives($h, $room);
                self::applyCaps($h, $allRooms);
                self::refreshGeneratorLevels($h, $allRooms);
                break;

            case self::STATUS_STORING:
                $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
                $h['current_level'] = max(0, (int)$h['current_level'] - (int)$room['level']);
                $room['status'] = self::STATUS_IDLE;
                $room['ts_activity_end'] = 0;
                $room['current_resource_amount'] = 0;
                self::clearRoomFromSlots($h, (int)$room['id']);
                self::removeRoomPassives($h, $room);
                self::applyCaps($h, $allRooms);
                self::refreshGeneratorLevels($h, $allRooms);
                break;

            case self::STATUS_PRODUCING:
                // Manual: producao pronta (collect processa); auto: nada a fazer aqui.
                $room['ts_activity_end'] = $now;
                $room['ts_last_resource_change'] = $now;
                break;
        }
        if (self::isManualProductionRoom((string)$identifier) && (int)$room['status'] === self::STATUS_IDLE) {
            $room['current_resource_amount'] = 0;
            $room['max_resource_amount'] = 0;
        }
    }

    // ------------------------------------------------------------------
    // Unidades
    // ------------------------------------------------------------------

    /** Nivel das unidades produzidas = passiv_bonus_amount_1 da barracks maxima. */
    public static function producedUnitLevel(array $h): int
    {
        $lvl = (int)$h['max_barracks_level'];
        if ($lvl > 0) {
            $unitLevel = (int)(self::roomLevelConfig('barracks', $lvl)['passiv_bonus_amount_1'] ?? 1);
            if ($unitLevel > 0) {
                return $unitLevel;
            }
        }
        return 1;
    }

    public static function unitStats(string $unitType, int $unitLevel): array
    {
        $cfg = self::unitConfig($unitType, $unitLevel);
        return [
            'stamina'        => (float)($cfg['stat_stamina'] ?? 100),
            'strength'       => (float)($cfg['stat_strength'] ?? 100),
            'criticalrating' => (float)($cfg['stat_critical_rating'] ?? 10),
            'dodgerating'    => (float)($cfg['stat_dodge_rating'] ?? 10),
            'weapondamage'   => 0,
            'level'          => $unitLevel,
            'damage_bonus'   => 100,
        ];
    }

    /** Nivel de unidade da parede (passiv_bonus_amount_1 da wall; 0 = sem parede). */
    public static function wallUnitLevel(array $h): int
    {
        $lvl = (int)$h['current_wall_level'];
        if ($lvl <= 0) {
            return 0;
        }
        return max(0, (int)(self::roomLevelConfig('wall', $lvl)['passiv_bonus_amount_1'] ?? 0));
    }

    public static function unitCost(string $unitType, int $unitLevel, int $count): array
    {
        $cfg = self::unitConfig($unitType, $unitLevel);
        return [
            'gold'  => (float)($cfg['price_gold'] ?? 0) * $count,
            'glue'  => (float)($cfg['price_glue'] ?? 0) * $count,
            'stone' => (float)($cfg['price_stone'] ?? 0) * $count,
        ];
    }

    // ------------------------------------------------------------------
    // Combate
    // ------------------------------------------------------------------

    /**
     * Filas de combate: atacantes a_N; defensores w_0 (parede ou dummy stamina 50)
     * + d_i. Porte do buildBattleQueues flash.
     */
    public static function buildBattleQueues(array $attackerHideout, array $defenderHideout, int $attackerCount, bool $tutorial = false): array
    {
        $attackers = [];
        $stats = self::unitStats('attacker', self::producedUnitLevel($attackerHideout));
        for ($i = 0; $i < $attackerCount; $i++) {
            $attackers[] = ['id' => "a_{$i}"] + $stats;
        }

        $defenders = [];
        if ($tutorial) {
            // Tutorial: o professor entra como 'parede' (perfil w), config
            // hideout_units.professor (stamina 100/strength 10 — captura real bate).
            $professor = self::unitStats('professor', 1);
            $defenders[] = ['id' => 'w_0', 'is_wall' => true] + $professor;
            return [$attackers, $defenders];
        }

        $wallLevel = self::wallUnitLevel($defenderHideout);
        $defenderCount = (int)$defenderHideout['current_defender_units'];
        if ($wallLevel > 0) {
            $defenders[] = ['id' => 'w_0', 'is_wall' => true] + self::unitStats('wall', $wallLevel);
        } elseif ($defenderCount <= 0) {
            // Parede dummy: stamina 50 (GameSettings flash; captura tutorial bate).
            $dummy = self::unitStats('wall', 1);
            $dummy['stamina'] = 50;
            $defenders[] = ['id' => 'w_0', 'is_wall' => true] + $dummy;
        }
        $defStats = self::unitStats('defender', self::producedUnitLevel($defenderHideout));
        for ($i = 0; $i < $defenderCount; $i++) {
            $defenders[] = ['id' => "d_{$i}", 'is_wall' => false] + $defStats;
        }
        return [$attackers, $defenders];
    }

    /** p = f(ratio) com as curvas do flash (server-side, invisiveis no cliente). */
    public static function probability(float $mine, float $theirs, float $min, float $base, float $max, float $expLow, float $expHigh): float
    {
        $ratio = $theirs > 0 ? $mine / $theirs : 10.0;
        if ($ratio <= 1.0) {
            return pow($ratio, $expLow) * ($base - $min) + $min;
        }
        return (1.0 - pow(1.0 / $ratio, $expHigh)) * ($max - $base) + $base;
    }

    public static function criticalChance(float $mineCrit, float $theirCrit): float
    {
        return self::probability(
            $mineCrit, $theirCrit,
            0.01, 0.02, 1.0,
            (float)self::c('battle_critical_probability_exp_low', 20),
            (float)self::c('battle_critical_probability_exp_high', 1.225)
        );
    }

    public static function dodgeChance(float $mineDodge, float $theirDodge): float
    {
        return self::probability(
            $mineDodge, $theirDodge,
            0.01, 0.10, 0.60,
            (float)self::c('battle_dodge_probability_exp_low', 5),
            (float)self::c('battle_dodge_probability_exp_high', 1.65)
        );
    }

    private static function rand(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    /**
     * Simulacao 1v1 sequencial (porte do simulateBattle flash):
     * HP = round(stamina * battle_hp_scale); dano = rand(0.8..1.05)*strength,
     * critico x2.2; turnos alternados; max battle_max_rounds por duelo.
     */
    public static function simulateBattle(array $attackers, array $defenders): array
    {
        $hpScale = (float)self::c('battle_hp_scale', 10);
        $critFactor = (float)self::c('battle_critical_factor', 2.2);
        $maxRounds = (int)self::c('battle_max_rounds', 80);

        $attackerProfiles = [];
        $defenderProfiles = [];
        foreach ($attackers as $u) {
            $attackerProfiles[self::profileKey($u['id'])] = self::profileFromUnit($u);
        }
        foreach ($defenders as $u) {
            $defenderProfiles[self::profileKey($u['id'])] = self::profileFromUnit($u);
        }
        $defenderProfiles += ['d' => self::profileFromUnit(self::unitStats('defender', 1)), 'oc' => []];

        $rounds = [];
        $aIdx = 0;
        $dIdx = 0;
        $destroyedAttacker = 0;
        $destroyedDefender = 0;
        $aHp = count($attackers) ? round($attackers[0]['stamina'] * $hpScale) : 0;
        $dHp = count($defenders) ? round($defenders[0]['stamina'] * $hpScale) : 0;

        while ($aIdx < count($attackers) && $dIdx < count($defenders)) {
            $att = $attackers[$aIdx];
            $def = $defenders[$dIdx];
            $attCrit = self::criticalChance($att['criticalrating'], $def['criticalrating']);
            $defCrit = self::criticalChance($def['criticalrating'], $att['criticalrating']);
            $attDodgeOfDef = self::dodgeChance($def['dodgerating'], $att['dodgerating']); // def esquiva ataques do att
            $defDodgeOfAtt = self::dodgeChance($att['dodgerating'], $def['dodgerating']);

            $attackerTurn = self::rand() < 0.5;
            $guard = 0;
            while ($aHp > 0 && $dHp > 0 && $guard++ < $maxRounds * 2) {
                if ($attackerTurn) {
                    if (self::rand() < $defDodgeOfAtt) {
                        $rounds[] = ['a' => $att['id'], 'd' => $def['id'], 'r' => self::ROUND_DODGED];
                    } else {
                        $damage = (0.8 + self::rand() * 0.25) * $att['strength']; // rand(0.8..1.05)
                        $result = self::ROUND_NORMAL;
                        if (self::rand() < $attCrit) {
                            $damage = round($damage * $critFactor);
                            $result = self::ROUND_CRITICAL;
                        }
                        $damage = max(1, (int)round($damage));
                        $dHp -= $damage;
                        $rounds[] = ['a' => $att['id'], 'd' => $def['id'], 'r' => $result, 'v' => $damage];
                    }
                } else {
                    if (self::rand() < $attDodgeOfDef) {
                        $rounds[] = ['a' => $def['id'], 'd' => $att['id'], 'r' => self::ROUND_DODGED];
                    } else {
                        $damage = (0.8 + self::rand() * 0.25) * $def['strength'];
                        $result = self::ROUND_NORMAL;
                        if (self::rand() < $defCrit) {
                            $damage = round($damage * $critFactor);
                            $result = self::ROUND_CRITICAL;
                        }
                        $damage = max(1, (int)round($damage));
                        $aHp -= $damage;
                        $rounds[] = ['a' => $def['id'], 'd' => $att['id'], 'r' => $result, 'v' => $damage];
                    }
                }
                $attackerTurn = !$attackerTurn;
            }

            if ($dHp <= 0) {
                if (empty($defenders[$dIdx]['is_wall'])) {
                    $destroyedDefender++;
                }
                $dIdx++;
                $dHp = $dIdx < count($defenders) ? round($defenders[$dIdx]['stamina'] * $hpScale) : 0;
            }
            if ($aHp <= 0) {
                $destroyedAttacker++;
                $aIdx++;
                $aHp = $aIdx < count($attackers) ? round($attackers[$aIdx]['stamina'] * $hpScale) : 0;
            }
        }

        return [
            'rounds'              => $rounds,
            'attacker_profiles'   => $attackerProfiles,
            'defender_profiles'   => $defenderProfiles,
            'destroyed_attacker'  => $destroyedAttacker,
            'destroyed_defender'  => $destroyedDefender,
            // Atacante ganha se varreu todos os defensores (ou nao ha defensores).
            'attacker_won'        => $aIdx < count($attackers) && $dIdx >= count($defenders),
        ];
    }

    private static function profileFromUnit(array $unit): array
    {
        return [
            'stamina'        => $unit['stamina'],
            'strength'       => $unit['strength'],
            'criticalrating' => $unit['criticalrating'],
            'dodgerating'    => $unit['dodgerating'],
            'weapondamage'   => $unit['weapondamage'] ?? 0,
            'level'          => $unit['level'],
            'damage_bonus'   => $unit['damage_bonus'] ?? 100,
        ];
    }

    /** a_12 -> 'a': perfis agrupados por prefixo (contrato do cliente). */
    private static function profileKey(string $unitId): string
    {
        return explode('_', $unitId)[0];
    }

    /** Salvage do defensor pelas unidades destruidas (strings JSON Reward). */
    public static function defenderSalvage(array $attackerHideout, array $defenderHideout, int $destroyedAttackers, int $destroyedDefenders): string
    {
        $glue = 0;
        $stone = 0;
        if ($destroyedAttackers > 0) {
            $cfg = self::unitConfig('attacker', self::producedUnitLevel($attackerHideout));
            $glue += (int)($cfg['destroyed_glue_for_defender'] ?? 0) * $destroyedAttackers;
            $stone += (int)($cfg['destroyed_stone_for_defender'] ?? 0) * $destroyedAttackers;
        }
        if ($destroyedDefenders > 0) {
            $cfg = self::unitConfig('defender', self::producedUnitLevel($defenderHideout));
            $glue += (int)($cfg['destroyed_glue_for_defender'] ?? 0) * $destroyedDefenders;
            $stone += (int)($cfg['destroyed_stone_for_defender'] ?? 0) * $destroyedDefenders;
        }
        $reward = [];
        if ($glue > 0) $reward['hideout_glue'] = $glue;
        if ($stone > 0) $reward['hideout_stone'] = $stone;
        return $reward === [] ? '' : json_encode($reward);
    }

    public static function countWinsAgainst(int $attackerHideoutId, int $defenderHideoutId): int
    {
        return (int)Db::value(
            'SELECT COUNT(*) FROM `hideout_battles`
              WHERE attacker_hideout_id = ? AND defender_hideout_id = ? AND hideout_winner_id = ?',
            [$attackerHideoutId, $defenderHideoutId, $attackerHideoutId]
        );
    }

    /** Elo k=21 clampado a +-[1..k]; defensor leva sempre o simetrico. */
    public static function pointsDelta(int $attackerPoints, int $defenderPoints, bool $attackerWon): array
    {
        $k = (int)(self::c('league_elo_calculation_k', 21) ?: 21);
        $expected = 1.0 / (1.0 + pow(10, ($defenderPoints - $attackerPoints) / 400.0));
        $delta = (int)round($k * (($attackerWon ? 1.0 : 0.0) - $expected));
        $atkDelta = $attackerWon ? max(1, $delta) : min(-1, $delta);
        return ['attacker_delta' => $atkDelta, 'defender_delta' => -$atkDelta];
    }

    // ------------------------------------------------------------------
    // Oponentes
    // ------------------------------------------------------------------

    /**
     * Escolhe oponente: 70% ordenar por pontos / 30% por current_level; vence o
     * valor MAIS PROXIMO. Protecao inicial medida sobre ts_creation da 1a sala.
     */
    public static function selectOpponent(int $selfCharacterId, int $selfPoints, int $selfLevel, int $excludeCharacterId = 0): int
    {
        $usePoints = (mt_rand(1, 10000) / 10000.0) <= (float)self::c('hideout_battle_use_points_leaderboard_chance', 0.7);
        $selfValue = $usePoints ? $selfPoints : $selfLevel;
        $protection = (int)self::c('hideout_initial_protection_time', 24) * 3600;
        $now = GameTime::now();

        $rows = Db::rows(
            'SELECT h.character_id, h.hideout_points, h.current_level,
                    (SELECT MIN(r.ts_creation) FROM `hideout_rooms` r WHERE r.hideout_id = h.id AND r.ts_creation > 0) AS first_room_ts
               FROM `hideout` h
              WHERE h.character_id != ?',
            [$selfCharacterId]
        );
        $bestId = 0;
        $bestDelta = PHP_INT_MAX;
        $fallbackId = 0;
        $fallbackDelta = PHP_INT_MAX;
        // Candidatos sem excluir o atual (usado se não houver alternativa).
        $anyBestId = 0;
        $anyBestDelta = PHP_INT_MAX;
        foreach ($rows as $r) {
            $cid = (int)$r['character_id'];
            $firstTs = (int)($r['first_room_ts'] ?? 0);
            $protected = $firstTs > 0 && ($now - $firstTs) < $protection;
            $value = $usePoints ? (int)$r['hideout_points'] : (int)$r['current_level'];
            $delta = abs($value - $selfValue);
            // Reserva qualquer candidato válido como último recurso.
            if (!$protected && $delta < $anyBestDelta) {
                $anyBestDelta = $delta;
                $anyBestId = $cid;
            }
            // Exclui o oponente atual para forçar troca.
            if ($excludeCharacterId > 0 && $cid === $excludeCharacterId) {
                continue;
            }
            if (!$protected) {
                if ($delta < $bestDelta) {
                    $bestDelta = $delta;
                    $bestId = $cid;
                }
            } else {
                if ($delta < $fallbackDelta) {
                    $fallbackDelta = $delta;
                    $fallbackId = $cid;
                }
            }
        }
        // Prioridade: novo oponente não protegido → não protegido com exclusão ignorada → protegido.
        return $bestId !== 0 ? $bestId : ($anyBestId !== 0 ? $anyBestId : $fallbackId);
    }

    /**
     * Loot previsto do oponente (fracao 33% - reducao por vitorias anteriores,
     * bonus +60%). Devolve STRINGS JSON tal como vai no payload ("" quando vazio).
     * $pointsPreview = delta Elo previsto se ganhar (vai em attacker_rewards).
     */
    public static function opponentLoot(array $defenderHideout, int $winsAgainst, int $pointsPreview = 0): array
    {
        $fraction = max(0.0,
            (float)self::c('hideout_battle_resource_win_amount', 0.33)
            - (float)self::c('hideout_battle_resource_win_amount_reduction', 0.1) * max(0, $winsAgainst)
        );
        $increase = (float)self::c('hideout_battle_resource_win_amount_increase', 60);
        $glue = (int)floor((int)$defenderHideout['current_resource_glue'] * $fraction);
        $stone = (int)floor((int)$defenderHideout['current_resource_stone'] * $fraction);

        $rewards = [];
        if ($pointsPreview !== 0) {
            $rewards['hideout_points'] = $pointsPreview;
        }
        if ($glue > 0)  $rewards['hideout_glue'] = $glue;
        if ($stone > 0) $rewards['hideout_stone'] = $stone;
        ksort($rewards);
        $bonus = [];
        $bonusGlue = (int)round($glue * $increase / 100.0);
        $bonusStone = (int)round($stone * $increase / 100.0);
        if ($bonusGlue > 0)  $bonus['hideout_glue'] = $bonusGlue;
        if ($bonusStone > 0) $bonus['hideout_stone'] = $bonusStone;

        return [
            'rewards'       => $rewards === [] ? '' : json_encode($rewards),
            'bonus_rewards' => $bonus === [] ? '' : json_encode($bonus),
            'glue'          => $glue,
            'stone'         => $stone,
        ];
    }

    /**
     * Projecao do oponente para `hideout_opponent` — shape EXATA da captura real
     * (opponent + opponent_inventory + opponent_inventory_items + opponent_sidekick).
     */
    public static function buildOpponentProjection(array $selfHideout): ?array
    {
        $opponentCid = (int)$selfHideout['current_opponent_id'];
        if ($opponentCid <= 0) {
            return null;
        }
        $oppChar = Db::row('SELECT * FROM `character` WHERE id = ?', [$opponentCid]);
        if ($oppChar === null) {
            return null;
        }
        $oppHideout = self::rowFor($opponentCid);
        if ($oppHideout === null) {
            return null;
        }

        // Atributos TOTAIS: stat_total_*, stat_weapon_damage e online_status NAO sao
        // colunas da tabela `character` -- sao calculados (base + treino + equipamento
        // + boosters/guilda), tal como em Character::duelOpponents.
        $oppProfile = Character::load($opponentCid)->battleProfile('a');
        $oppChar['stat_total_stamina']         = (int)$oppProfile['stamina'];
        $oppChar['stat_total_strength']        = (int)$oppProfile['strength'];
        $oppChar['stat_total_critical_rating'] = (int)$oppProfile['criticalrating'];
        $oppChar['stat_total_dodge_rating']    = (int)$oppProfile['dodgerating'];
        $oppChar['stat_weapon_damage']         = (int)$oppProfile['weapondamage'];
        $oppChar['online_status'] = (GameTime::now() - (int)($oppChar['ts_last_action'] ?? 0)) < 300 ? 1 : 2;

        $wins = self::countWinsAgainst((int)$selfHideout['id'], (int)$oppHideout['id']);
        // Preview do Elo se ganhar (vai dentro de rewards; captura real mostra
        // "hideout_points":4 no oponente).
        $elo = self::pointsDelta((int)$selfHideout['hideout_points'], (int)$oppHideout['hideout_points'], true);
        $loot = self::opponentLoot($oppHideout, $wins, $elo['attacker_delta']);

        $gender = (string)$oppChar['gender'] === 'f' ? 'f' : 'm';
        $appearanceDefaults = [
            'appearance_head_type'        => 1,
            'appearance_skin_color'       => 1,
            'appearance_hair_type'        => 1,
            'appearance_hair_color'       => 1,
            'appearance_eyes_type'        => 1,
            'appearance_eyebrows_type'    => 1,
            'appearance_mouth_type'       => 1,
            'appearance_nose_type'        => 1,
            'appearance_facial_hair_type' => $gender === 'm' ? 1 : 0,
            'appearance_decoration_type'  => 0,
        ];
        $opponent = [
            'id'           => (int)$oppChar['id'],
            'name'         => (string)$oppChar['name'],
            'gender'       => $gender,
            'level'        => (int)$oppChar['level'],
            'stat_base_stamina'        => (int)$oppChar['stat_base_stamina'],
            'stat_base_strength'       => (int)$oppChar['stat_base_strength'],
            'stat_base_critical_rating'=> (int)$oppChar['stat_base_critical_rating'],
            'stat_base_dodge_rating'   => (int)$oppChar['stat_base_dodge_rating'],
            'stat_total_stamina'       => (int)$oppChar['stat_total_stamina'],
            'stat_total_strength'      => (int)$oppChar['stat_total_strength'],
            'stat_total_critical_rating'=> (int)$oppChar['stat_total_critical_rating'],
            'stat_total_dodge_rating'  => (int)$oppChar['stat_total_dodge_rating'],
            'stat_weapon_damage'       => (int)$oppChar['stat_weapon_damage'],
            'honor'                    => (int)$oppChar['honor'],
            'league_points'            => (int)$oppChar['league_points'],
            'league_group_id'          => (int)$oppChar['league_group_id'],
            'online_status'            => (int)$oppChar['online_status'],
            'show_mask'                => (bool)$oppChar['show_mask'],
            'show_cape'                => true,
            'hideout_points'           => (int)$oppHideout['hideout_points'],
            'defender_units'           => (int)$oppHideout['current_defender_units'],
            'wall_level'               => (int)$oppHideout['current_wall_level'],
            'barracks_level'           => (int)$oppHideout['current_barracks_level'],
            'rewards'                  => $loot['rewards'],
            'bonus_rewards'            => $loot['bonus_rewards'],
        ];
        foreach ($appearanceDefaults as $key => $default) {
            $val = $oppChar[$key] ?? null;
            $opponent[$key] = ($val === null || $val === '') ? $default : (int)$val;
        }

        // Inventario + itens equipados (so id+identifier, como na captura).
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$opponentCid]);
        $wrapper = ['opponent' => $opponent];
        if ($inv !== null) {
            $slots = ['mask_item_id', 'cape_item_id', 'suit_item_id', 'belt_item_id',
                      'boots_item_id', 'weapon_item_id', 'gadget_item_id', 'missiles_item_id'];
            $itemIds = [];
            foreach ($slots as $slot) {
                if ((int)($inv[$slot] ?? 0) > 0) {
                    $itemIds[] = (int)$inv[$slot];
                }
            }
            $inventory = ['id' => (int)$inv['id']];
            foreach ($slots as $slot) {
                $inventory[$slot] = (int)($inv[$slot] ?? 0);
            }
            $inventory['sidekick_id'] = (int)($inv['sidekick_id'] ?? 0);
            $wrapper['opponent_inventory'] = $inventory;

            $items = [];
            if ($itemIds !== []) {
                $in = implode(',', array_fill(0, count($itemIds), '?'));
                foreach (Db::rows("SELECT id, identifier FROM `items` WHERE id IN ($in)", $itemIds) as $item) {
                    $items[] = ['id' => (int)$item['id'], 'identifier' => (string)$item['identifier']];
                }
            }
            $wrapper['opponent_inventory_items'] = $items;

            $sid = (int)($inv['sidekick_id'] ?? 0);
            $sidekick = $sid > 0 ? Db::row('SELECT * FROM `sidekicks` WHERE id = ?', [$sid]) : null;
            if ($sidekick !== null) {
                // `sewed_*` NAO sao colunas da tabela `sidekicks` (ver Sidekick.php
                // ~L758, que ja usa o mesmo default). Tudo com ?? para nunca
                // rebentar a resposta inteira por causa de um campo do oponente.
                $wrapper['opponent_sidekick'] = [
                    'id'                   => (int)$sidekick['id'],
                    'identifier'           => (string)($sidekick['identifier'] ?? ''),
                    'sewed_identifier'     => (string)($sidekick['sewed_identifier'] ?? ''),
                    'sewed_level'          => (int)($sidekick['sewed_level'] ?? 0),
                    'name'                 => (string)($sidekick['name'] ?? ''),
                    'quality'              => (int)($sidekick['quality'] ?? 0),
                    'level'                => (int)($sidekick['level'] ?? 0),
                    'stat_stamina'         => (int)($sidekick['stat_stamina'] ?? 0),
                    'stat_strength'        => (int)($sidekick['stat_strength'] ?? 0),
                    'stat_critical_rating' => (int)($sidekick['stat_critical_rating'] ?? 0),
                    'stat_dodge_rating'    => (int)($sidekick['stat_dodge_rating'] ?? 0),
                    'stage1_skill_id'      => (int)($sidekick['stage1_skill_id'] ?? 0),
                    'stage2_skill_id'      => (int)($sidekick['stage2_skill_id'] ?? 0),
                    'stage3_skill_id'      => (int)($sidekick['stage3_skill_id'] ?? 0),
                ];
            }
        }
        return $wrapper;
    }

    // ------------------------------------------------------------------
    // Payload
    // ------------------------------------------------------------------

    /** Objeto `hideout` completo (DOHideout + 60 slots). */
    public static function shapeHideout(array $h): array
    {
        $out = [
            'id'                           => (int)$h['id'],
            'hideout_points'               => (int)$h['hideout_points'],
            'current_level'                => (int)$h['current_level'],
            'idle_worker_count'            => (int)$h['idle_worker_count'],
            'max_worker_count'             => (int)$h['max_worker_count'],
            'ts_time_worker_expires'       => (int)$h['ts_time_worker_expires'],
            'current_resource_glue'        => (int)$h['current_resource_glue'],
            'max_resource_glue'            => (int)$h['max_resource_glue'],
            'current_resource_stone'       => (int)$h['current_resource_stone'],
            'max_resource_stone'           => (int)$h['max_resource_stone'],
            'current_attacker_units'       => (int)$h['current_attacker_units'],
            'max_attacker_units'           => (int)$h['max_attacker_units'],
            'current_defender_units'       => (int)$h['current_defender_units'],
            'max_defender_units'           => (int)$h['max_defender_units'],
            'current_opponent_id'          => (int)$h['current_opponent_id'],
            'current_opponent_server_id'   => 'local',
            'current_opponent_chest_reward' => (bool)$h['current_opponent_chest_reward'],
            'ts_last_opponent_refresh'     => (int)$h['ts_last_opponent_refresh'],
            'active_battle_id'             => (int)$h['active_battle_id'],
            'ts_last_lost_attack'          => (int)$h['ts_last_lost_attack'],
            'current_worker_level'         => (int)$h['current_worker_level'],
            'current_wall_level'           => (int)$h['current_wall_level'],
            'current_barracks_level'       => (int)$h['current_barracks_level'],
            'max_barracks_level'           => (int)$h['max_barracks_level'],
            'current_gem_production_level' => (int)$h['current_gem_production_level'],
            'current_broker_level'         => (int)$h['current_broker_level'],
            'current_robot_storage_level'  => (int)$h['current_robot_storage_level'],
        ];
        for ($l = 0; $l < self::MAX_LEVELS; $l++) {
            for ($s = 0; $s < self::MAX_SLOTS; $s++) {
                $key = self::slotKey($l, $s);
                $out[$key] = (int)($h[$key] ?? -1);
            }
        }
        return $out;
    }

    /** Objeto `hideout_room`. */
    /** Read legacy workshop state and expose the keys/identifiers expected by the client. */
    public static function improvementData(array $room): array
    {
        $data = json_decode((string)($room['additional_value_2'] ?? ''), true);
        $data = is_array($data) ? $data : [];
        if (!isset($data['improvement_slot']) && isset($data['slot'])) {
            $data['improvement_slot'] = (int)$data['slot'];
        }
        unset($data['slot']);
        $names = [1 => 'mask', 2 => 'cape', 3 => 'suit', 4 => 'belt', 5 => 'boots', 6 => 'weapon', 7 => 'gadget'];
        $qualities = [1 => 'common', 2 => 'rare', 3 => 'epic'];
        $slot = (int)($data['improvement_slot'] ?? 0);
        if (isset($names[$slot]) && isset($data['improvement_rarity']) && is_array($data['improvement_rarity'])) {
            foreach ($data['improvement_rarity'] as $index => &$rarity) {
                if (!is_array($rarity)) continue;
                $quality = (int)($rarity['quality'] ?? ((int)$index + 1));
                if (!isset($qualities[$quality])) continue;
                $rarity['id'] = 0;
                $rarity['quality'] = $quality;
                $rarity['identifier'] = 'improvement_' . $names[$slot] . '_' . $qualities[$quality];
            }
            unset($rarity);
            // An object is required even for numeric keys 0, 1, 2.
            $data['improvement_rarity'] = (object)$data['improvement_rarity'];
        }
        return $data;
    }

    public static function shapeRoom(array $r): array
    {
        if ((string)$r['identifier'] === 'gem_production') {
            $r['additional_value_2'] = json_encode(self::improvementData($r));
        }
        return [
            'id'                          => (int)$r['id'],
            'hideout_id'                  => (int)($r['hideout_id'] ?? 0),
            'identifier'                  => (string)$r['identifier'],
            'status'                      => (int)$r['status'],
            'level'                       => (int)$r['level'],
            'current_resource_amount'     => (int)$r['current_resource_amount'],
            'max_resource_amount'         => (int)$r['max_resource_amount'],
            'ts_last_resource_change'     => (int)$r['ts_last_resource_change'],
            'ts_activity_end'             => (int)$r['ts_activity_end'],
            'current_generator_level'     => (int)($r['current_generator_level'] ?? 0),
            'additional_value_1'          => (int)($r['additional_value_1'] ?? 0),
            'additional_value_2'          => (string)($r['additional_value_2'] ?? ''),
            'guild_synergy_additional_value' => (string)($r['guild_synergy_additional_value'] ?? ''),
        ];
    }

    /** Batalha para o payload (JSON strings intactas). */
    public static function shapeBattle(array $b): array
    {
        return [
            'id'                      => (int)$b['id'],
            'attacker_hideout_id'     => (int)$b['attacker_hideout_id'],
            'defender_hideout_id'     => (int)$b['defender_hideout_id'],
            'defender_server_id'      => (string)$b['defender_server_id'],
            'attacker_status'         => (int)$b['attacker_status'],
            'defender_status'         => (int)$b['defender_status'],
            'attacker_count'          => (int)$b['attacker_count'],
            'defender_count'          => (int)$b['defender_count'],
            'attacker_profiles'       => (string)$b['attacker_profiles'],
            'defender_profiles'       => (string)$b['defender_profiles'],
            'rounds'                  => (string)$b['rounds'],
            'attacker_rewards'        => (string)$b['attacker_rewards'],
            'attacker_bonus_rewards'  => (string)$b['attacker_bonus_rewards'],
            'defender_rewards'        => (string)$b['defender_rewards'],
            'destroyed_attacker'      => (int)$b['destroyed_attacker'],
            'destroyed_defender'      => (int)$b['destroyed_defender'],
            'hideout_winner_id'       => (int)$b['hideout_winner_id'],
            'server_winner_id'        => (string)$b['server_winner_id'],
            'attacker_character_name' => (string)$b['attacker_character_name'],
            'defender_character_name' => (string)$b['defender_character_name'],
            'ts_creation'             => (int)$b['ts_creation'],
        ];
    }

    /** Item do historico (DOHideoutHistoryBattle). */
    public static function shapeHistoryBattle(array $b, int $viewerCharacterId): array
    {
        return [
            'id'                      => (int)$b['id'],
            'ts_creation'             => (int)$b['ts_creation'],
            'attacker_hideout_id'     => (int)$b['attacker_hideout_id'],
            'defender_hideout_id'     => (int)$b['defender_hideout_id'],
            'hideout_winner_id'       => (int)$b['hideout_winner_id'],
            'server_winner_id'        => (string)$b['server_winner_id'],
            'attacker_rewards'        => (string)$b['attacker_rewards'],
            'attacker_bonus_rewards'  => (string)$b['attacker_bonus_rewards'],
            'defender_rewards'        => (string)$b['defender_rewards'],
            'attacker_count'          => (int)$b['attacker_count'],
            'defender_count'          => (int)$b['defender_count'],
            'destroyed_attacker'      => (int)$b['destroyed_attacker'],
            'destroyed_defender'      => (int)$b['destroyed_defender'],
            'attacker_character_name' => (string)$b['attacker_character_name'],
            'defender_character_name' => (string)$b['defender_character_name'],
            'character_id'            => (int)$b['character_id'],
            'character_name'          => (string)$b['character_name'],
            'character_gender'        => (string)$b['character_gender'],
            'character_online_status' => (GameTime::now() - (int)($b['ts_last_action'] ?? 0)) < 300 ? 1 : 2,
        ];
    }

    /**
     * Bundle completo de estado (boot/sync): hideout + salas + oponente +
     * batalha ativa + missed attacks. Chaves ausentes quando sem hideout.
     */
    public static function statePayload(int $characterId, ?array $h = null, ?array $rooms = null): array
    {
        $h = $h ?? self::rowFor($characterId);
        if ($h === null) {
            // Sem hideout: so o contador (o cliente le missed_hideout_attacks no boot).
            return ['missed_hideout_attacks' => 0];
        }
        $changed = self::sweepExpiredSlotUnlocks($h);
        $rooms = $rooms ?? self::roomsFor($characterId);
        // Os caps derivam das salas colocadas. Persiste-os quando mudarem: as acoes
        // leem a linha crua da BD (requireRow) e faziam min() contra limites velhos.
        $before = [$h['max_resource_glue'], $h['max_resource_stone'], $h['max_attacker_units'], $h['max_defender_units']];
        self::applyCaps($h, $rooms);
        $after = [$h['max_resource_glue'], $h['max_resource_stone'], $h['max_attacker_units'], $h['max_defender_units']];
        if ($changed || $before != $after) {
            self::saveHideout($h);
        }

        $boosterWindow=self::productionBoosterWindow($characterId);
        // Para a main_building, max_resource_amount depende do nível do personagem —
        // actualiza o valor no array antes de serializar para o cliente.
        foreach ($rooms as &$room) {
            if (in_array($room['identifier'],['glue_production','stone_production'],true)) {
                $meta=json_decode((string)$room['additional_value_2'],true) ?: [];
                $meta['production_booster']=$boosterWindow;
                $room['additional_value_2']=json_encode($meta,JSON_THROW_ON_ERROR);
            }
            if ($room['identifier'] === 'main_building') {
                $room['max_resource_amount'] = self::effectiveMaxResourceAmount($room, $h, $rooms);
                Db::exec(
                    'UPDATE `hideout_rooms` SET max_resource_amount = ? WHERE id = ?',
                    [$room['max_resource_amount'], (int)$room['id']]
                );
            }
        }
        unset($room);

        $payload = [
            'hideout'       => self::shapeHideout($h),
            'hideout_rooms' => array_map([self::class, 'shapeRoom'], $rooms),
        ];
        $activeId = (int)$h['active_battle_id'];
        if ($activeId > 0) {
            $battle = Db::row('SELECT * FROM `hideout_battles` WHERE id = ?', [$activeId]);
            if ($battle !== null) {
                $payload['hideout_battle'] = self::shapeBattle($battle);
            }
        }
        $cap = (int)self::c('hideout_max_missed_attacks', 99);
        $payload['missed_hideout_attacks'] = max(0, min($cap, (int)$h['missed_attacks']));
        $opponent = self::buildOpponentProjection($h);
        if ($opponent !== null) {
            $payload['hideout_opponent'] = $opponent;
        }
        return $payload;
    }

    /**
     * Anexa o estado do hideout a uma resposta de conta (boot/accountState).
     * O cliente funda as chaves por campo (updateData), por isso basta juntar
     * hideout/hideout_rooms/hideout_opponent/missed_hideout_attacks ao topo.
     */
    public static function attachState(array $data, int $characterId): array
    {
        foreach (self::statePayload($characterId) as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * Visita a um hideout ALHEIO (getHideout): o cliente constroi um DOHideout
     * proprio a partir das chaves requested_* (Hideout.fromData). O hideout e
     * identificado pelo id da tabela `hideout` (o cliente compara com o seu
     * get_id() e usa character.hideout_id dos outros).
     */
    public static function visitedPayload(int $hideoutId): array
    {
        $h = Db::row('SELECT * FROM `hideout` WHERE id = ?', [$hideoutId]);
        $owner = $h === null ? null : Db::row('SELECT name, gender FROM `character` WHERE id = ?', [(int)$h['character_id']]);
        if ($h === null || $owner === null) {
            throw new GameError('errRequestInvalidParameter');
        }

        $changed = self::sweepExpiredSlotUnlocks($h);
        $rooms = self::roomsFor((int)$h['character_id']);
        self::applyCaps($h, $rooms);
        if ($changed) {
            self::saveHideout($h);
        }

        $shaped = self::shapeHideout($h);
        $shaped['server_id'] = 'local';
        $shaped['owner_name'] = (string)$owner['name'];
        $shaped['owner_gender'] = ((string)$owner['gender'] === 'f') ? 'f' : 'm';

        return [
            'requested_hideout'                 => $shaped,
            'requested_hideout_rooms'           => array_map([self::class, 'shapeRoom'], $rooms),
            // Aparencia customizada do dono; nao temos optical changes de hideout,
            // vetor vazio mantem a aparencia padrao no dialogo de visita.
            'requested_hideout_optical_changes' => ['optical_changes' => []],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers partilhados pelas acoes (custos, workers, premium)
    // ------------------------------------------------------------------

    /** Row do hideout do personagem ou erro — todas as acoes comecam assim. */
    public static function requireRow(int $characterId): array
    {
        $h = self::rowFor($characterId);
        if ($h === null) {
            throw new GameError('errInvalidHideout');
        }
        return $h;
    }

    /** Valida gold/glue/stone na ordem do flash (erros oficiais distintos). */
    public static function checkCosts(Character $char, array $h, float $gold, float $glue, float $stone): void
    {
        if ($gold > 0 && $char->gameCurrency() < $gold) {
            throw new GameError('errRemoveGameCurrencyNotEnough');
        }
        if ($glue > 0 && (int)$h['current_resource_glue'] < $glue) {
            throw new GameError('errRemoveGlueNotEnough');
        }
        if ($stone > 0 && (int)$h['current_resource_stone'] < $stone) {
            throw new GameError('errRemoveStoneNotEnough');
        }
    }

    /**
     * Desconto do broker no preco em OURO (cliente: getReducedGameCurrencyValue):
     * price × (1 − passiv_bonus_amount_1/100), teto por ceil.
     */
    public static function reducedGold(array $h, float $gold): float
    {
        $brokerLevel = (int)$h['current_broker_level'];
        if ($brokerLevel <= 0 || $gold <= 0) {
            return $gold;
        }
        $cfg = self::roomLevelConfig('broker', $brokerLevel);
        if (!$cfg) {
            return $gold;
        }
        return ceil($gold * (1.0 - (float)($cfg['passiv_bonus_amount_1'] ?? 0) / 100.0));
    }

    /**
     * Debita custos JA validados. Ouro vai direto para a BD (o accountState
     * final recarrega o character); cola/pedra ficam na row em memoria que o
     * chamador grava com saveHideout().
     */
    public static function applyCosts(Character $char, array &$h, float $gold, float $glue, float $stone): void
    {
        if ($gold > 0) {
            Db::exec(
                'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                [(int)ceil($gold), $char->id(), (int)ceil($gold)]
            );
        }
        if ($glue > 0) {
            $h['current_resource_glue'] = (int)$h['current_resource_glue'] - (int)ceil($glue);
        }
        if ($stone > 0) {
            $h['current_resource_stone'] = (int)$h['current_resource_stone'] - (int)ceil($stone);
        }
    }

    /** Debita premium com UPDATE guardado (mesmo padrao da loja). */
    public static function debitPremium(Character $char, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $char->userId(), $cost]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    }

    /** Consome um worker livre (erro especifico verificado pelo chamador). */
    public static function takeWorker(array &$h): void
    {
        $h['idle_worker_count'] = max(0, (int)$h['idle_worker_count'] - 1);
    }

    /** Devolve um worker ao pool (teto max_worker_count, como no flash). */
    public static function giveWorkerBack(array &$h): void
    {
        $h['idle_worker_count'] = min((int)$h['max_worker_count'], (int)$h['idle_worker_count'] + 1);
    }

    /** Credita XP no personagem (xp_production / recompensas de luta). */
    public static function grantXp(int $characterId, int $amount): void
    {
        if ($amount > 0) {
            Db::exec('UPDATE `character` SET xp = xp + ? WHERE id = ?', [$amount, $characterId]);
        }
    }

    // ------------------------------------------------------------------
    // Erros de atividade pendente (familia err<Action>PendingActivity<Suffix>)
    // ------------------------------------------------------------------

    public static function pendingActivityError(string $actionPrefix, int $status): string
    {
        $suffixes = [
            self::STATUS_BUILDING  => 'Building',
            self::STATUS_UPGRADING => 'Upgrading',
            self::STATUS_PLACING   => 'Placing',
            self::STATUS_STORING   => 'Storing',
            self::STATUS_PRODUCING => 'Producing',
        ];
        return "err{$actionPrefix}PendingActivity" . ($suffixes[$status] ?? '');
    }
}
