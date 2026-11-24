<?php
declare(strict_types=1);
namespace HeroZero;

/** Balanceamento local: um sorteio equiprovável por patamar e dia do jogo. */
final class DailyMilestones
{
    public const ENERGY = 5;
    public const DUEL = 2;
    public const LEAGUE = 3;
    public const HIDEOUT = 4;

    public static function thresholds(int $type): array
    {
        return match ($type) {
            self::ENERGY => [100,200,300],
            self::DUEL => [10,20,50],
            self::LEAGUE => [5,10,20],
            self::HIDEOUT => [5,10,20],
            default => throw new \InvalidArgumentException('Tipo de patamar inválido.'),
        };
    }

    private static function config(int $type): array
    {
        self::thresholds($type);
        return match ($type) {
            self::ENERGY => ['daily_energy', 'energy_spent', 'quest_energy_spent'],
            self::DUEL => ['daily_duel', 'fight_count', 'duel_battle_won'],
            self::LEAGUE => ['daily_league', 'fight_count', 'league_battle_won'],
            self::HIDEOUT => ['daily_hideout', 'fight_count', 'hideout_battle_won'],
        };
    }

    public static function catalog(int $type, int $threshold): array
    {
        $rank = array_search($threshold, self::thresholds($type), true);
        if ($rank === false) throw new \InvalidArgumentException('Patamar inválido.');
        return [
            ['reward_type'=>3,'reward_value'=>0,'reward_identifier'=>'random_epic'],
            ['reward_type'=>1,'reward_value'=>[10,20,30][$rank],'reward_identifier'=>''],
            ['reward_type'=>4,'reward_value'=>[5,10,15][$rank],'reward_identifier'=>''],
            ['reward_type'=>5,'reward_value'=>[5,10,20][$rank],'reward_identifier'=>''],
            ['reward_type'=>9,'reward_value'=>[3,5,10][$rank],'reward_identifier'=>''],
        ];
    }

    public static function attachAll(array $data, int $cid): array
    {
        return Missiles::transaction([$cid], static function () use ($data,$cid): array {
            foreach ([self::ENERGY,self::DUEL,self::LEAGUE,self::HIDEOUT] as $type) $data = self::attach($data,$cid,$type);
            return $data;
        });
    }

    public static function claimById(int $cid, int $id, bool $discard, int $requestedType = 0): void
    {
        if ($requestedType !== 0) {
            if (!in_array($requestedType, [self::ENERGY,self::DUEL,self::LEAGUE,self::HIDEOUT], true)) {
                throw new GameError('errCollectDailyBonusRewardInvalidStatus');
            }
            [$table] = self::config($requestedType);
            if (!Db::value("SELECT id FROM {$table}_rewards WHERE id = ? AND character_id = ?", [$id,$cid])) {
                throw new GameError('errCollectDailyBonusRewardInvalidStatus');
            }
            self::claim($cid,$id,$discard,$requestedType);
            return;
        }
        // Em instalações antigas os IDs das quatro tabelas podem coincidir.
        // O cliente normal só envia id: escolher um prémio realmente disponível.
        foreach ([self::ENERGY,self::DUEL,self::LEAGUE,self::HIDEOUT] as $type) {
            [$table,$counter] = self::config($type);
            if (Db::value("SELECT r.id FROM {$table}_rewards r
                    JOIN {$table}_progress p ON p.character_id = r.character_id
                   WHERE r.id = ? AND r.character_id = ? AND r.reward_date = ?
                     AND r.status = 0 AND p.progress_date = ?
                     AND p.{$counter} >= r.threshold_value LIMIT 1",
                [$id,$cid,BonusEvents::today(),BonusEvents::today()])) {
                self::claim($cid,$id,$discard,$type);
                return;
            }
        }
        throw new GameError('errCollectDailyBonusRewardInvalidStatus');
    }

    public static function xpReward(int $level, int $percent): int
    {
        $gap = max(0, Character::xpForLevel($level+1) - Character::xpForLevel($level));
        return (int)ceil($gap * $percent / 100);
    }

    /** Chamado sob lock do herói; também usado dentro da transação da missão. */
    private static function ensure(int $cid, int $type): array
    {
        [$table,$counter] = self::config($type);
        $today = BonusEvents::today();
        $progress = Db::row("SELECT * FROM {$table}_progress WHERE character_id = ? FOR UPDATE", [$cid]);
        if (!$progress || $progress['progress_date'] !== $today) {
            foreach (Db::rows("SELECT id, item_id FROM {$table}_rewards WHERE character_id = ? AND status = 0 AND reward_date <> ?", [$cid,$today]) as $old) {
                if ((int)$old['item_id'] > 0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [(int)$old['item_id'],$cid]);
                Db::exec("UPDATE {$table}_rewards SET status = 2, item_id = 0, rewards = ? WHERE id = ?", ['{}',(int)$old['id']]);
            }
            Db::exec("INSERT INTO {$table}_progress (character_id, progress_date, {$counter}) VALUES (?, ?, 0)
                ON DUPLICATE KEY UPDATE progress_date = VALUES(progress_date), {$counter} = 0", [$cid,$today]);
            $progress = ['character_id'=>$cid,'progress_date'=>$today,$counter=>0];
        }
        foreach (self::thresholds($type) as $threshold) {
            if (Db::value("SELECT id FROM {$table}_rewards WHERE character_id = ? AND reward_date = ? AND threshold_value = ?", [$cid,$today,$threshold])) continue;
            $options = self::catalog($type,$threshold);
            $definition = $options[random_int(0,count($options)-1)];
            $reward = [];
            $itemId = 0;
            if ($definition['reward_type'] === 3) {
                $itemId = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
                $reward['item'] = $itemId;
                $itemType = (int)Db::value('SELECT type FROM items WHERE id = ?', [$itemId]);
                $definition['reward_identifier'] = 'random_epic_'.[1=>'mask',2=>'cape',3=>'suit',4=>'belt',5=>'boots',6=>'weapon',7=>'gadget'][$itemType];
            } else {
                $key = [1=>'quest_energy',4=>'training_sessions',5=>'statPoints',9=>'xp'][$definition['reward_type']];
                $reward[$key] = $definition['reward_type'] === 9 ? 0 : $definition['reward_value'];
            }
            Db::exec("INSERT INTO {$table}_rewards (character_id, reward_date, threshold_value, reward_type, reward_value, reward_identifier, rewards, item_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$cid,$today,$threshold,$definition['reward_type'],$definition['reward_value'],$definition['reward_identifier'],json_encode($reward,JSON_THROW_ON_ERROR),$itemId]);
        }
        return $progress;
    }

    /** Atividade concluída; chamada na transação do resgate para impedir duplicados. */
    public static function record(int $cid, int $amount, int $type): void
    {
        if ($amount <= 0) return;
        Missiles::transaction([$cid], static function () use ($cid,$amount,$type): void {
            [$table,$counter] = self::config($type);
            $max = max(self::thresholds($type));
            self::ensure($cid,$type);
            // Só existem três patamares; o contador não cresce sem limite.
            Db::exec("UPDATE {$table}_progress SET {$counter} = LEAST({$max}, {$counter} + ?) WHERE character_id = ?", [$amount,$cid]);
        });
    }

    private static function reward(array $row, int $level): array
    {
        $reward = json_decode($row['rewards'],true,512,JSON_THROW_ON_ERROR);
        // O cliente Reward lê statPoints. Normaliza também sorteios antigos.
        if (array_key_exists('stat_points',$reward)) {
            $reward['statPoints'] = (int)$reward['stat_points'];
            unset($reward['stat_points']);
        }
        if ((int)$row['reward_type'] === 9 && (int)$row['status'] === 0) $reward['xp'] = self::xpReward($level,(int)$row['reward_value']);
        return $reward;
    }

    public static function attach(array $data, int $cid, int $type): array
    {
        return Missiles::transaction([$cid], static function () use ($data,$cid,$type): array {
            [$table,$counter,$lookup] = self::config($type);
            $progress = self::ensure($cid,$type);
            $level = (int)Db::value('SELECT level FROM `character` WHERE id = ?', [$cid]);
            $data['daily_bonus_lookup'][$lookup] = (int)$progress[$counter];
            $data['daily_bonus_rewards'] = array_values(array_filter($data['daily_bonus_rewards'] ?? [], static fn(array $r): bool => (int)$r['type'] !== $type));
            $definitions = [];
            foreach (Db::rows("SELECT * FROM {$table}_rewards WHERE character_id = ? ORDER BY id", [$cid]) as $row) {
                $current = $row['reward_date'] === BonusEvents::today();
                $status = !$current || (int)$row['status'] !== 0 ? 2 : ((int)$progress[$counter] >= (int)$row['threshold_value'] ? 1 : 0);
                $reward = $current ? self::reward($row,$level) : [];
                // Tombstones actualizam os IDs antigos no mapa incremental do cliente.
                $data['daily_bonus_rewards'][] = ['id'=>(int)$row['id'],'type'=>$type,'value'=>(int)$row['threshold_value'],'status'=>$status,'rewards'=>json_encode((object)$reward,JSON_THROW_ON_ERROR)];
                if (!$current) continue;
                $definitions[(string)$row['threshold_value']] = ['reward_type'=>(int)$row['reward_type'],'reward_value'=>(int)$row['reward_value'],'reward_identifier'=>$row['reward_identifier']];
                if ((int)$row['item_id'] > 0 && (int)$row['status'] === 0) {
                    $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ?', [(int)$row['item_id'],$cid]);
                    if (!$item) throw new \RuntimeException('Preview de patamar inexistente.');
                    if (!in_array((int)$item['id'],array_map('intval',array_column($data['items'] ?? [],'id')),true)) {
                        $data['items'][] = Live::shapeLike(Live::template('autoLoginUser')['items'][0] ?? [],$item);
                    }
                }
            }
            $data['daily_bonus_reward_data'][(string)$type] = (object)$definitions;
            // Uma recompensa singular capturada não pode reativar um ID antigo.
            if (isset($data['daily_bonus_reward']) && (int)$data['daily_bonus_reward']['type'] === $type) unset($data['daily_bonus_reward']);
            return $data;
        });
    }

    public static function claim(int $cid, int $id, bool $discard, int $type): void
    {
        Missiles::transaction([$cid], static function () use ($cid,$id,$discard,$type): void {
            [$table,$counter] = self::config($type);
            $progress = self::ensure($cid,$type);
            $row = Db::row("SELECT * FROM {$table}_rewards WHERE id = ? AND character_id = ? FOR UPDATE", [$id,$cid]);
            if (!$row || $row['reward_date'] !== BonusEvents::today() || (int)$row['status'] !== 0
                || (int)$progress[$counter] < (int)$row['threshold_value']) throw new GameError('errCollectDailyBonusRewardInvalidStatus');
            $char = Character::load($cid);
            $reward = self::reward($row,$char->level());
            $coins = 0;
            if ((int)$row['item_id'] > 0) {
                $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [(int)$row['item_id'],$cid]);
                if (!$item) throw new GameError('errCollectDailyBonusRewardInvalidStatus');
                if ($discard) {
                    $coins = (int)$item['sell_price'];
                    Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [(int)$row['item_id'],$cid]);
                } else {
                    $inv = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
                    $constants = Missiles::constants();
                    $limit = $char->level() >= $constants['inventory_bag3_unlock_level'] ? 18 : ($char->level() >= $constants['inventory_bag2_unlock_level'] ? 12 : 6);
                    $free = null;
                    for ($i=1;$i<=$limit;$i++) if ($inv && (int)$inv['bag_item'.$i.'_id'] === 0) { $free = 'bag_item'.$i.'_id'; break; }
                    if (!$free) throw new GameError('errInventoryNoEmptySlot');
                    Db::exec("UPDATE inventory SET `{$free}` = ? WHERE character_id = ?", [(int)$row['item_id'],$cid]);
                }
            }
            Db::exec('UPDATE `character` SET xp = xp + ?, stat_points_available = stat_points_available + ?, game_currency = game_currency + ? WHERE id = ?',
                [(int)($reward['xp'] ?? 0),(int)($reward['statPoints'] ?? 0),$coins,$cid]);
            foreach (['quest_energy','training_sessions'] as $key) if (!empty($reward[$key])) UserVouchers::create($cid,$key,(int)$reward[$key]);
            Season::givePoints($cid,1);
            Db::exec("UPDATE {$table}_rewards SET status = 1, rewards = ?, item_id = ? WHERE id = ?",
                [json_encode($discard && isset($reward['item']) ? ['coins'=>$coins] : $reward,JSON_THROW_ON_ERROR),$discard ? 0 : (int)$row['item_id'],$id]);
            Character::load($cid)->syncLevelFromXp();
        });
    }
}
