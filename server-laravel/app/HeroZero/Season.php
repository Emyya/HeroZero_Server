<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Lógica central da Season Pass (battle pass).
 *
 * Status de recompensa (DOSeasonReward.status):
 *   0 = bloqueada (pontos insuficientes ou passe premium por comprar)
 *   1 = desbloqueada / disponível para reclamar (paga com donuts via unlockSeasonReward,
 *       ou pontos suficientes mas ainda não reclamada)
 *   2 = reclamada (claimSeasonReward executado)
 *
 * Catálogos oficiais das constantes; calendário e ordem de rotação locais.
 */
final class Season
{
    private const IDENTIFIER  = 'gods_1';
    private const DURATION    = 30 * 86400;
    private const RESET_COST  = 99; // balancing_constants: season_reset_progress_premium_cost

    // -------------------------------------------------------------------------
    // Catálogo

    /**
     * Catálogo do identificador pedido, derivado de constants_json.data.
     * Mantém IDs históricos 1–104 de gods_1; IDs dos restantes temas são distintos.
     * rewards = JSON com reward_type, reward_factor, reward_identifier.
     */
    public static function rewardCatalog(string $identifier = self::IDENTIFIER): array
    {
        $all = self::constantsData();
        if (!isset($all[$identifier])) throw new GameError('errClaimSeasonRewardNotFound');
        $index = array_search($identifier, self::rotation(), true);
        $tiers = $all[$identifier]['season_points'];
        ksort($tiers, SORT_NUMERIC);
        $out = [];
        $id = $index * 1000;
        foreach ($tiers as $points => $tier) {
            foreach ([false, true] as $premium) {
                $prefix = $premium ? 'premium_' : '';
                $out[] = ['id' => ++$id, 'season_points' => (int)$points, 'premium' => $premium,
                    'rewards' => json_encode(['reward_type' => (int)$tier[$prefix.'reward_type'],
                        'reward_factor' => $tier[$prefix.'reward_factor'],
                        'reward_identifier' => $tier[$prefix.'reward_identifier']], JSON_THROW_ON_ERROR)];
            }
        }
        return $out;
    }

    /** Ordem local: ordem do ficheiro, começando em gods_1 e repetindo no fim. */
    public static function rotation(): array
    {
        $ids = array_keys(self::constantsData());
        $first = array_search(self::IDENTIFIER, $ids, true);
        if ($first === false) throw new \RuntimeException('Catálogo gods_1 ausente.');
        return array_merge(array_slice($ids, $first), array_slice($ids, 0, $first));
    }

    // -------------------------------------------------------------------------
    // BD

    /** Calendário global persistente: independente de contas e da ordem dos logins. */
    public static function currentCycle(?int $now = null): array
    {
        $now ??= GameTime::now();
        // A tabela é criada pela migração, nunca dentro de transacções de jogo.
        $anchor = Db::value('SELECT ts_anchor FROM season_calendar WHERE id = 1');
        if ($anchor === null) {
            Db::exec('INSERT IGNORE INTO season_calendar (id, ts_anchor)
                SELECT 1, COALESCE(MIN(NULLIF(ts_created, 0)), ?) FROM season_progress',
                [$now]);
            $anchor = Db::value('SELECT ts_anchor FROM season_calendar WHERE id = 1');
        }
        $anchor = (int)$anchor;
        $steps = max(0, intdiv($now - $anchor, self::DURATION));
        $rotation = self::rotation();
        $index = $steps % count($rotation);
        $start = $anchor + $steps * self::DURATION;
        return ['season_id' => $index + 1, 'identifier' => $rotation[$index],
            'ts_start' => $start, 'ts_end' => $start + self::DURATION];
    }

    /** Garante que existe uma linha season_progress para o character (idempotente). */
    public static function ensureProgress(int $cid): array
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT season_progress_cycle');
        else $pdo->beginTransaction();
        try {
            // Serializa criação/renovação com os resgates da mesma personagem.
            if (!Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid])) {
                throw new GameError('errClaimSeasonRewardNotFound');
            }
            $row = Db::row('SELECT * FROM `season_progress` WHERE character_id = ? FOR UPDATE', [$cid]);
            $now = GameTime::now();
            $cycle = self::currentCycle($now);
            if ($row === null) {
                Db::exec(
                    'INSERT INTO `season_progress`
                        (character_id, season_id, identifier, status, season_points,
                         premium_unlocked, restarted, claimed_rewards, unlocked_rewards,
                         ts_created, ts_start, ts_end)
                     VALUES (?, ?, ?, 2, 0, 0, 0, ?, ?, ?, ?, ?)',
                    [$cid, $cycle['season_id'], $cycle['identifier'], '[]', '[]', $now,
                        $cycle['ts_start'], $cycle['ts_end']]
                );
            } else {
                // Compatibilidade com os calendários individuais antigos: se o tema
                // é o mesmo e a janela ainda está activa, preserva os resgates.
                // Uma mudança de tema ou expiração inicia progresso vazio.
                $reset = $row['identifier'] !== $cycle['identifier']
                    || (int)$row['ts_end'] <= $now
                    || (int)$row['ts_start'] > $now;
                if ($reset || (int)$row['ts_start'] !== $cycle['ts_start']
                    || (int)$row['ts_end'] !== $cycle['ts_end']
                    || (int)$row['season_id'] !== $cycle['season_id']) {
                    $clear = $reset ? ', status = 2, season_points = 0, premium_unlocked = 0,
                        restarted = 0, claimed_rewards = \'[]\', unlocked_rewards = \'[]\'' : '';
                    Db::exec('UPDATE season_progress SET season_id = ?, identifier = ?,
                        ts_start = ?, ts_end = ?'.$clear.' WHERE character_id = ?',
                        [$cycle['season_id'], $cycle['identifier'], $cycle['ts_start'],
                            $cycle['ts_end'], $cid]);
                }
            }
            $row = Db::row('SELECT * FROM `season_progress` WHERE character_id = ?', [$cid]) ?? [];
            if ($nested) $pdo->exec('RELEASE SAVEPOINT season_progress_cycle');
            else $pdo->commit();
            return $row;
        } catch (\Throwable $e) {
            if ($nested && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT season_progress_cycle');
                $pdo->exec('RELEASE SAVEPOINT season_progress_cycle');
            } elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Shapes para o cliente

    /** Progresso do jogador formatado como DOSeasonProgress. */
    public static function progressForCharacter(int $cid): array
    {
        $row = self::ensureProgress($cid);
        return [
            'id'               => (int)$row['id'],
            'season_id'        => (int)$row['season_id'],
            'character_id'     => $cid,
            'status'           => (int)$row['status'],
            'ts_created'       => (int)$row['ts_created'],
            'ts_start'         => (int)$row['ts_start'],
            'ts_end'           => (int)$row['ts_end'],
            'identifier'       => (string)$row['identifier'],
            'season_points'    => (int)$row['season_points'],
            'premium_unlocked' => (bool)$row['premium_unlocked'],
            'restarted'        => (bool)$row['restarted'],
        ];
    }

    /**
     * Lista de recompensas formatada como array de DOSeasonReward.
     *
     * Status por recompensa:
     *   0 = bloqueada (pontos insuficientes ou passe premium por comprar)
     *   1 = desbloqueada (pontos/avanço e, se premium, passe comprado), não reclamada
     *   2 = reclamada
     */
    public static function rewardsForCharacter(int $cid): array
    {
        $progress   = self::ensureProgress($cid);
        $progressId = (int)$progress['id'];
        $points     = (int)$progress['season_points'];
        $claimed    = json_decode((string)$progress['claimed_rewards'], true);
        $unlocked   = json_decode((string)$progress['unlocked_rewards'], true);
        if (!is_array($claimed))  $claimed  = [];
        if (!is_array($unlocked)) $unlocked = [];

        $tsCreated = (int)$progress['ts_created'];

        $out = [];
        foreach (self::rewardCatalog((string)$progress['identifier']) as $r) {
            $id = $r['id'];
            if (in_array($id, $claimed, true)) {
                $status = 2;
            } elseif ($r['premium'] && !(bool)$progress['premium_unlocked']) {
                $status = 0;
            } elseif (in_array($id, $unlocked, true) || $points >= $r['season_points']) {
                $status = 1;
            } else {
                $status = 0;
            }

            $out[] = [
                'id'                 => $id,
                'season_progress_id' => $progressId,
                'character_id'       => $cid,
                'status'             => $status,
                'ts_created'         => $tsCreated,
                'season_points'      => $r['season_points'],
                'premium'            => $r['premium'],
                'rewards'            => self::clientRewardJson($r['rewards']),
            ];
        }
        return $out;
    }

    /** Contrato ec.get_questEnergy()/get_trainingSessions() do cliente. */
    public static function clientRewardJson(string $raw): string
    {
        $r = json_decode($raw, true);
        $factor = max(1, (int)($r['reward_factor'] ?? 1));
        return match ((int)($r['reward_type'] ?? 0)) {
            4 => json_encode(['xp'=>$factor*10000], JSON_THROW_ON_ERROR),
            6 => json_encode(['training_sessions'=>$factor], JSON_THROW_ON_ERROR),
            8 => json_encode(['quest_energy'=>$factor], JSON_THROW_ON_ERROR),
            11 => json_encode(['hideout_glue'=>$factor], JSON_THROW_ON_ERROR),
            12 => json_encode(['hideout_stone'=>$factor], JSON_THROW_ON_ERROR),
            default => $raw,
        };
    }

    // -------------------------------------------------------------------------
    // Operações

    /**
     * Credita season_points ao jogador.
     * Chamar em quests, torneios, duelos, etc.
     */
    public static function givePoints(int $cid, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        self::ensureProgress($cid);
        Db::exec(
            'UPDATE `season_progress` SET season_points = season_points + ? WHERE character_id = ?',
            [$amount, $cid]
        );
    }

    /**
     * Desbloqueia uma recompensa pelo pagamento de donuts (skip_cost), sem a entregar.
     * O cliente chama claimSeasonReward a seguir para receber a recompensa.
     * Devolve a shape DOSeasonReward com status=1.
     *
     * @throws GameError errClaimSeasonRewardAlreadyClaimed
     * @throws GameError errClaimSeasonRewardNotFound
     */
    public static function unlockReward(int $cid, int $rewardId): array
    {
        $progress   = self::ensureProgress($cid);
        $progressId = (int)$progress['id'];
        $claimed    = json_decode((string)$progress['claimed_rewards'], true);
        $unlocked   = json_decode((string)$progress['unlocked_rewards'], true);
        if (!is_array($claimed))  $claimed  = [];
        if (!is_array($unlocked)) $unlocked = [];

        if (in_array($rewardId, $claimed, true)) {
            throw new GameError('errClaimSeasonRewardAlreadyClaimed');
        }

        $reward = self::findInCatalog($rewardId, (string)$progress['identifier']);
        if ($reward['premium'] && !(bool)$progress['premium_unlocked']) {
            throw new GameError('errClaimSeasonRewardPremiumRequired');
        }

        if (!in_array($rewardId, $unlocked, true)) {
            $unlocked[] = $rewardId;
            // Avança season_points até ao threshold desta recompensa para que o
            // marcador da barra de progresso no cliente reflita o salto pago.
            $currentPoints = (int)$progress['season_points'];
            $newPoints     = max($currentPoints, $reward['season_points']);
            Db::exec(
                'UPDATE `season_progress` SET unlocked_rewards = ?, season_points = ? WHERE character_id = ?',
                [json_encode($unlocked), $newPoints, $cid]
            );
        }

        return [
            'id'                 => $rewardId,
            'season_progress_id' => $progressId,
            'character_id'       => $cid,
            'status'             => 1,
            'ts_created'         => (int)$progress['ts_created'],
            'season_points'      => $reward['season_points'],
            'premium'            => $reward['premium'],
            'rewards'            => self::clientRewardJson($reward['rewards']),
        ];
    }

    /**
     * Reivindica uma recompensa e entrega ao jogador.
     * Requer que a recompensa esteja desbloqueada (pontos suficientes ou paga com donuts).
     * Devolve a shape DOSeasonReward mínima com status=2.
     *
     * @throws GameError errClaimSeasonRewardAlreadyClaimed
     * @throws GameError errClaimSeasonRewardNotEnoughPoints
     * @throws GameError errClaimSeasonRewardPremiumRequired
     * @throws GameError errClaimSeasonRewardNotFound
     */
    public static function claimReward(int $cid, int $rewardId, bool $discard): array
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT season_claim');
        else $pdo->beginTransaction();
        try {
            if (!Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid])) {
                throw new GameError('errClaimSeasonRewardNotFound');
            }
            Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
            $progress   = self::ensureProgress($cid);
            $progress = Db::row('SELECT * FROM season_progress WHERE id = ? AND character_id = ? FOR UPDATE',
                [(int)$progress['id'], $cid]) ?? $progress;
            $claimed    = json_decode((string)$progress['claimed_rewards'], true);
            $unlocked   = json_decode((string)$progress['unlocked_rewards'], true);
            if (!is_array($claimed))  $claimed  = [];
            if (!is_array($unlocked)) $unlocked = [];

            if (in_array($rewardId, $claimed, true)) {
                throw new GameError('errClaimSeasonRewardAlreadyClaimed');
            }

            $reward = self::findInCatalog($rewardId, (string)$progress['identifier']);

            // Verificar acesso: pontos suficientes OU pré-desbloqueado com donuts.
            $hasPoints  = (int)$progress['season_points'] >= $reward['season_points'];
            $wasUnlocked = in_array($rewardId, $unlocked, true);
            if (!$hasPoints && !$wasUnlocked) {
                throw new GameError('errClaimSeasonRewardNotEnoughPoints');
            }

            // Pontos e desbloqueios individuais nunca substituem a compra do passe.
            if ($reward['premium'] && !(bool)$progress['premium_unlocked']) {
                throw new GameError('errClaimSeasonRewardPremiumRequired');
            }

            if (!$discard) {
                self::deliverReward($cid, (array)json_decode((string)$reward['rewards'], true));
            }

            $claimed[] = $rewardId;
            Db::exec(
                'UPDATE `season_progress` SET claimed_rewards = ? WHERE id = ? AND character_id = ?',
                [json_encode($claimed), (int)$progress['id'], $cid]
            );
            if ($nested) $pdo->exec('RELEASE SAVEPOINT season_claim');
            else $pdo->commit();

            // Shape mínima devolvida no season_reward singular (como no servidor oficial).
            return [
                'id'     => $rewardId,
                'status' => 2,
                'rewards' => self::clientRewardJson($reward['rewards']),
            ];
        } catch (\Throwable $e) {
            if ($nested) $pdo->exec('ROLLBACK TO SAVEPOINT season_claim');
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reseta o progresso da season.
     * Debito de donuts feito externamente pela action.
     */
    public static function resetProgress(int $cid): void
    {
        self::ensureProgress($cid);
        Db::exec(
            'UPDATE `season_progress`
             SET season_points = 0, restarted = 1, claimed_rewards = ?, unlocked_rewards = ?
             WHERE character_id = ?',
            ['[]', '[]', $cid]
        );
    }

    public static function resetCost(): int
    {
        return self::RESET_COST;
    }

    /**
     * Shape de constants.seasons para o cliente.
     * Necessário para gj.exists(identifier) retornar true e o botão da season renderizar.
     * Formato: { "<identifier>": { identifier, sidekick_battle_effect_id, ... } }
     */
    public static function constantsData(): array
    {
        $src = __DIR__.'/../../public/assets/data/constants_json.data';
        if (!is_file($src)) $src = __DIR__.'/../../../assets/data/constants_json.data';
        return AssetCache::slice('season_catalogs_v1', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            if (empty($all['seasons'])) throw new \RuntimeException('Catálogos de temporadas ausentes.');
            return $all['seasons'];
        });
    }

    // -------------------------------------------------------------------------
    // Helpers privados

    /** @throws GameError errClaimSeasonRewardNotFound */
    private static function findInCatalog(int $rewardId, string $identifier): array
    {
        foreach (self::rewardCatalog($identifier) as $r) {
            if ($r['id'] === $rewardId) {
                return $r;
            }
        }
        throw new GameError('errClaimSeasonRewardNotFound');
    }

    /**
     * Entrega a recompensa ao jogador.
     * Os atributos livres são creditados em stat_points_available; gemas vão para a mochila.
     */
    private static function deliverReward(int $cid, array $r): void
    {
        $type   = (int)($r['reward_type']       ?? 0);
        $factor = (int)($r['reward_factor']      ?? 0);
        $ident  = (string)($r['reward_identifier'] ?? '');

        switch ($type) {
            case 1: // game_currency — factor é nível de produção (aprox. factor × 5000 moedas)
                $amount = max(1, $factor) * 5000;
                Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$amount, $cid]);
                break;

            case 2: // premium_currency / donuts — factor = quantidade directa
                $amount = max(1, $factor);
                $userId = (int)Db::value('SELECT user_id FROM `character` WHERE id = ?', [$cid]);
                if ($userId > 0) {
                    Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?', [$amount, $userId]);
                }
                break;

            case 4: // XP — escala local factor × 10000; tipo confirmado no JS
                $amount = max(1, $factor) * 10000;
                Db::exec('UPDATE `character` SET xp = xp + ? WHERE id = ?', [$amount, $cid]);
                Character::load($cid)->syncLevelFromXp();
                break;

            case 3: // pontos de atributos gratuitos (como nas recompensas dos torneios)
                $amount = max(1, $factor);
                if (Db::exec('UPDATE `character` SET stat_points_available = stat_points_available + ? WHERE id = ?', [$amount, $cid]) !== 1) {
                    throw new GameError('errClaimSeasonRewardNotFound');
                }
                break;

            case 6: // unidades de treino guardadas nas mensagens
                UserVouchers::create($cid, 'training_sessions', max(1, $factor));
                break;

            case 8: // energia: quantidade direta, confirmada em et.refreshWithoutReward()
                UserVouchers::create($cid, 'quest_energy', max(1, $factor));
                break;

            case 11: // supercola; escala oficial desconhecida, quantidade local = factor
                UserVouchers::create($cid, 'hideout_glue', max(1, $factor));
                break;

            case 12: // zenit; escala oficial desconhecida, quantidade local = factor
                UserVouchers::create($cid, 'hideout_stone', max(1, $factor));
                break;

            case 7: // season item — sidekick vai para sidekicks; equipamento vai para items + bag
                if ($ident !== '') {
                    if (str_contains($ident, 'sidekick')) {
                        self::giveSeasonSidekick($cid, $ident);
                    } else {
                        self::giveSeasonItem($cid, $ident);
                    }
                }
                break;

            case 10: // improvement item (identifier está em item_improvements, NÃO em item_templates)
                self::giveImprovementItem($cid, $ident, $factor);
                break;

            // Tipos 9 (sidekick), 13 (título) — ignorados por ora
        }
    }

    private static function giveSeasonItem(int $cid, string $identifier): void
    {
        if (str_starts_with($identifier, 'missiles_')) {
            Missiles::give($cid, $identifier);
            return;
        }

        // Mapear identifier para tipo de slot (boots, mask, cape, suit, belt, weapon, gadget, sidekick)
        static $typeMap = [
            'boots'    => 5, 'mask' => 1, 'cape' => 2, 'suit' => 3,
            'belt'     => 4, 'weapon' => 6, 'gadget' => 7, 'sidekick' => 8,
        ];
        $type = 6; // fallback weapon
        foreach ($typeMap as $keyword => $t) {
            if (str_contains($identifier, $keyword)) { $type = $t; break; }
        }

        // Qualidade: season items são epic (3), improvement items são epic (3)
        $quality = 3;
        $char = Db::row('SELECT level FROM `character` WHERE id = ?', [$cid]);
        $level = (int)($char['level'] ?? 1);
        // Fórmula do brechó: épico (2.3) + 0.20 pela borda dourada.
        $qualityMult = 2.3 + 0.20;
        $kyberFlat = Hideout::kyberneticBonuses($cid)['item_flat'];
        $budget = (int)ceil(($level * 1.6 + 2 + $level ** 2 * 0.004) * $qualityMult) + $kyberFlat;

        // Distribuir stats
        $stats = array_fill_keys(['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'], 0);
        $keys  = array_keys($stats);
        shuffle($keys);
        // Mesma distribuição dos épicos do brechó: 3 stats (40%) ou 4 (60%).
        if (random_int(1, 100) <= 40) {
            $a = random_int((int)ceil($budget * 0.3), (int)ceil($budget * 0.45));
            $b = random_int((int)ceil(($budget - $a) * 0.4), (int)ceil(($budget - $a) * 0.65));
            $stats[$keys[0]] = $a;
            $stats[$keys[1]] = $b;
            $stats[$keys[2]] = max(1, $budget - $a - $b);
        } else {
            $a = random_int((int)ceil($budget * 0.25), (int)ceil($budget * 0.4));
            $b = random_int((int)ceil($budget * 0.2), (int)ceil($budget * 0.35));
            $c = random_int((int)ceil(($budget - $a - $b) * 0.4), (int)ceil(($budget - $a - $b) * 0.7));
            $stats[$keys[0]] = $a;
            $stats[$keys[1]] = $b;
            $stats[$keys[2]] = $c;
            $stats[$keys[3]] = max(1, $budget - $a - $b - $c);
        }
        $weaponDamage = $type === 6 ? (int)ceil($level * 1.035) + 2 : 0;
        $sell = max(4, (int)round(4 * ($level ** 1.5)));
        $buy  = max(8, (int)round(8 * ($level ** 1.5) * 1.6));

        // O cliente ativa a borda warforged a partir de +15.
        // Só equipamento passa aqui; pets e modificações têm geradores separados.
        $plusItemLevel = 15;

        Db::exec(
            'INSERT INTO `items` (character_id, identifier, type, quality, required_level, charges, item_level,
             ts_availability_start, ts_availability_end, premium_item, buy_price, sell_price,
             stat_stamina, stat_strength, stat_critical_rating, stat_dodge_rating, stat_weapon_damage)
             VALUES (?,?,?,?,?,0,?,?,1,0,?,?,?,?,?,?,?)',
            [$cid, $identifier, $type, $quality, $level, $level, $plusItemLevel, $buy, $sell,
             $stats['stat_stamina'], $stats['stat_strength'],
             $stats['stat_critical_rating'], $stats['stat_dodge_rating'], $weaponDamage]
        );
        $itemId = (int)Db::pdo()->lastInsertId();

        // Atribuir a um slot livre da bag
        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ? LIMIT 1', [$cid]);
        if ($inv) {
            for ($i = 1; $i <= 18; $i++) {
                $f = 'bag_item' . $i . '_id';
                if (array_key_exists($f, $inv) && (int)$inv[$f] <= 0) {
                    Db::exec("UPDATE `inventory` SET `$f` = ? WHERE character_id = ?", [$itemId, $cid]);
                    break;
                }
            }
        }
    }

    private static function giveSeasonSidekick(int $cid, string $identifier): void
    {
        // Não executar ALTER TABLE dentro da transação de resgate (commit implícito).
        if (!ItemImprovement::hasSidekickSchema()) {
            throw new GameError('errActionNotSupported');
        }
        // Bases padrão epic (quality=3) para sidekick de season
        $bases = [
            'stat_base_stamina'         => 60,
            'stat_base_strength'        => 100,
            'stat_base_critical_rating' => 40,
            'stat_base_dodge_rating'    => 23,
        ];
        $sidekickId = \HeroZero\Sidekick::create($cid, $identifier, 3, $bases);
        Db::exec('UPDATE sidekicks SET item_improvement_id = 1 WHERE id = ? AND character_id = ?', [$sidekickId, $cid]);
    }

    private static function giveImprovementItem(int $cid, string $identifier, int $factor): void
    {
        $definition = ItemImprovement::catalog()[$identifier] ?? null;
        if (!$definition) throw new GameError('errInventoryInvalidItem');
        $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]) ?? [];
        $slot = null;
        for ($i = 1; $i <= 18; $i++) {
            $field = 'bag_item' . $i . '_id';
            if (array_key_exists($field, $inventory) && (int)$inventory[$field] === 0) {
                $slot = $field;
                break;
            }
        }
        if ($slot === null) throw new GameError('errInventoryNoEmptySlot');
        $character = Character::load($cid);
        $stats = self::improvementStats($character->level(), (int)$definition['quality'], $factor);
        $itemId = $character->createImprovementItem((int)$definition['type'], (int)$definition['quality'], $stats);
        Db::exec("UPDATE inventory SET `{$slot}` = ? WHERE character_id = ?", [$itemId, $cid]);
    }

    /** Balanceamento local provisório: nível × fator/10 × qualidade; fórmula oficial não capturada. */
    private static function improvementStats(int $level, int $quality, int $factor): array
    {
        $multiplier = [1 => 0.5, 2 => 0.65, 3 => 0.8, 4 => 1.2][$quality] ?? 0.8;
        $budget = max(4, (int)ceil(max(1, $level) * max(1, $factor) / 10 * $multiplier));
        $keys = ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'];
        shuffle($keys);
        $stats = [];
        $remaining = $budget;
        foreach ($keys as $index => $key) {
            $value = $index === 3 ? $remaining : random_int(1, $remaining - (3 - $index));
            $stats[$key] = $value;
            $remaining -= $value;
        }
        return $stats;
    }
}
