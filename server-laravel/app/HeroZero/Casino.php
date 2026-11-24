<?php
declare(strict_types=1);
namespace HeroZero;

/** Casino: protocolo do cliente, catálogos capturados, balanceamento local. */
final class Casino
{
    // Regra local: 0 corresponde a ilimitado no cliente HTML5.
    public const MAX_DAILY_SPINS = 0;
    public const MACHINES = ['slots' => 1, 'roleta' => 2, 'spine' => 3];
    // Tipos dos símbolos confirmados em UiLuckyWheelSlice.setSymbol.
    public const TYPES = [1=>'coins',2=>'item',3=>'booster',4=>'sidekick',5=>'stat_points',
        6=>'xp',7=>'quest_energy',8=>'training_sessions',9=>'hideout_glue',10=>'hideout_stone',
        11=>'optical_change',12=>'improvement'];

    public static function reference(): array
    {
        $src = dirname(__DIR__, 3).'/assets/data/constants_json.data';
        return AssetCache::slice('casino_reference_v1', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            return array_filter($all, static fn($key) => str_contains($key, 'casino')
                || in_array($key, ['item_templates','optical_changes','optical_change_categories','item_set_templates']), ARRAY_FILTER_USE_KEY);
        });
    }

    /** Listas da configuração capturada; não são probabilidades oficiais. */
    public static function catalogs(): array
    {
        $src = __DIR__.'/../../data/initEnvironment.json';
        return AssetCache::slice('casino_catalogs_v2', $src, static function () use ($src): array {
            $config = json_decode(file_get_contents($src), true, 512, JSON_THROW_ON_ERROR);
            $out = [];
            foreach ($config['extendedConfig']['content_mapping'] as $key => $entries) {
                if ($key === 'other_casino_default') $variant = 'normal';
                elseif (str_starts_with($key, 'casino_')) $variant = substr($key, 7);
                elseif ($key === 'event_item_casino_event') $variant = 'item_casino';
                else continue;
                $out[$variant] = ['items'=>array_values($entries[6] ?? []),
                    'sidekicks'=>array_values($entries[7] ?? []), 'optical'=>array_values($entries[14] ?? [])];
            }
            // Identificadores exatos do catálogo capturado em casino_ofc.har.
            $out['no_cash_and_xp']['items']=array_column(array_column(array_filter(self::capturedNoCashRewards()[0]['rewards'],static fn($r)=>$r['genericRewardType']===6),'genericRewardData'),'identifier');
            // Variante visual referenciada nos assets; pool local das aparências compráveis.
            $out['optical_changes'] = ['items'=>$out['normal']['items'], 'sidekicks'=>[],
                'optical'=>array_map('intval', array_keys(array_filter(self::reference()['optical_changes'],
                    static fn($row) => !$row['default'] && $row['buyable'])))];
            return $out;
        });
    }

    public static function schedule(string $variant, int $machine, int $days, ?int $start = null): int
    {
        if (!isset(self::catalogs()[$variant]) || !in_array($machine, self::MACHINES, true)
            || $days < 1 || $days > 365) throw new \InvalidArgumentException('Evento, máquina ou duração inválidos.');
        $start ??= GameTime::now();
        if ($start < GameTime::now() - 1) throw new \InvalidArgumentException('O início não pode ser anterior ao relógio do jogo.');
        $end = $start + $days * 86400;
        return Missiles::transaction([], static function () use ($variant,$machine,$start,$end): int {
            Db::row('SELECT id FROM casino_control WHERE id = 1 FOR UPDATE');
            if (Db::row('SELECT id FROM casino_events WHERE enabled = 1 AND ts_start < ? AND ts_end > ? LIMIT 1 FOR UPDATE', [$end,$start])) {
                throw new \InvalidArgumentException('Já existe casino agendado nesse intervalo. Cancela-o ou escolhe outra janela.');
            }
            Db::exec('INSERT INTO casino_events (variant,machine_type,ts_start,ts_end) VALUES (?,?,?,?)', [$variant,$machine,$start,$end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }

    /** Preparação do arranque: janela fixa desde o epoch, nunca renovada. */
    public static function ensureOpeningEvent(int $epoch): ?int
    {
        $now = GameTime::now();
        $end = $epoch + 3 * 86400;
        if ($epoch <= 0 || $epoch > $now || $now >= $end) return null;
        return Missiles::transaction([], static function () use ($epoch, $end): ?int {
            if (!Db::row('SELECT id FROM casino_control WHERE id = 1 FOR UPDATE')) {
                throw new \RuntimeException('Falta casino_control; executar a migração do casino.');
            }
            // Um calendário já utilizado, mesmo cancelado, não é um servidor novo.
            if (Db::row('SELECT id FROM casino_events LIMIT 1 FOR UPDATE')) return null;
            // schedule() destina-se a novos eventos futuros. Aqui o início é o
            // nascimento do servidor, anterior aos segundos de preparação da BD.
            Db::exec('INSERT INTO casino_events (variant,machine_type,ts_start,ts_end) VALUES (?,?,?,?)',
                ['no_cash_and_xp', self::MACHINES['slots'], $epoch, $end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }

    public static function cancel(?int $id = null): void
    {
        Missiles::transaction([], static function () use ($id): void {
            Db::row('SELECT id FROM casino_control WHERE id = 1 FOR UPDATE');
            if ($id !== null && !Db::row('SELECT id FROM casino_events WHERE id = ?', [$id])) throw new \InvalidArgumentException('Evento inexistente.');
            if ($id === null) Db::exec('UPDATE casino_events SET enabled = 0 WHERE enabled = 1 AND ts_end > ?', [GameTime::now()]);
            else Db::exec('UPDATE casino_events SET enabled = 0 WHERE id = ?', [$id]);
        });
    }

    public static function current(): ?array
    {
        return Db::row('SELECT * FROM casino_events WHERE enabled = 1 AND ts_start <= ? AND ts_end > ? ORDER BY id LIMIT 1',
            [GameTime::now(),GameTime::now()]);
    }

    public static function eventIdentifier(array $event): string
    {
        // CasinoEvent.get_baseIdentifier remove as duas primeiras partes.
        return 'casino_'.$event['id'].'_'.($event['variant'] === 'normal' ? 'default' : $event['variant']);
    }

    /** O mesmo calendário é servido no boot, initGame e respostas parciais. */
    public static function constants(?array $event = null): array
    {
        $constants = array_intersect_key(self::reference(), array_flip(['casino_min_level','casino_teaser_min_level',
            'casino_max_daily_spins','casino_premium_currency_amount','resource_free_casino_spin_usage_amount',
            'resource_casino_jeton_usage_amount','casino_multi_spin_amount_5','casino_multi_spin_amount_10']));
        $constants['casino_max_daily_spins'] = self::MAX_DAILY_SPINS;
        $constants['casino_machine_type'] = (int)($event['machine_type'] ?? 1);
        $constants['slotmachine_event_start'] = gmdate('Y-m-d H:i:s', (int)($event['ts_start'] ?? 0));
        $constants['slotmachine_event_end'] = gmdate('Y-m-d H:i:s', max(0,(int)($event['ts_end'] ?? 1)-1));
        // Extensão local evita conversões de relógio/fuso nos eventos simulados.
        $constants['casino_event_start_timestamp'] = (int)($event['ts_start'] ?? 0);
        $constants['casino_event_end_timestamp'] = (int)($event['ts_end'] ?? 0);
        $constants['casino'] = [];
        foreach (array_keys(self::TYPES) as $type) $constants['casino']['var'.str_pad((string)$type,3,'0',STR_PAD_LEFT)] =
            ['symbol1'=>$type,'symbol2'=>$type,'symbol3'=>$type];
        return $constants;
    }

    public static function state(int $cid): array
    {
        return (json_decode((string)Db::value('SELECT casino_state FROM `character` WHERE id = ?',[$cid]),true) ?: []) + ['active'=>null];
    }

    private static function save(int $cid, array $state): void
    {
        Db::exec('UPDATE `character` SET casino_state = ? WHERE id = ?', [json_encode($state,JSON_THROW_ON_ERROR),$cid]);
    }

    public static function pending(int $cid): array
    {
        $active = self::state($cid)['active'];
        if (!is_array($active)) throw new GameError('errGetCasinoRewardCharacterHasNoActiveSpin');
        return $active;
    }

    /** Contrato do parser Reward do cliente, incluindo resultados antigos já pagos. */
    public static function clientRewards(array $rewards): array
    {
        return array_map(static function (string $json): string {
            $reward=json_decode($json,true,512,JSON_THROW_ON_ERROR);
            if (isset($reward['stat_points'])) {
                $reward['statPoints']=(int)($reward['statPoints'] ?? $reward['stat_points']);
                unset($reward['stat_points']);
            }
            return json_encode($reward,JSON_THROW_ON_ERROR);
        },$rewards);
    }

    /** Os previews só podem ser utilizados depois do resgate. */
    public static function assertItemsAvailable(int $cid, array $ids): void
    {
        $ids=array_filter(array_map('intval',$ids),static fn($id)=>$id>0);
        if (!$ids) return;
        $active=self::state($cid)['active'];
        foreach ($active['rewards'] ?? [] as $reward) {
            $item=(int)(json_decode($reward,true)['item'] ?? 0);
            if ($item>0 && in_array($item,$ids,true)) throw new GameError('errInventoryInvalidItem');
        }
    }

    /** Reset por data local; não concede giros grátis implicitamente. */
    public static function syncDaily(int $cid): void
    {
        Missiles::transaction([$cid], static function () use ($cid): void {
            $char = Db::row('SELECT slotmachine_spin_count,ts_last_slotmachine_refill FROM `character` WHERE id = ?',[$cid]);
            if ($char && BonusEvents::dateOf((int)$char['ts_last_slotmachine_refill']) < BonusEvents::today()) {
                Db::exec('UPDATE `character` SET slotmachine_spin_count = 0,ts_last_slotmachine_refill = ? WHERE id = ?', [GameTime::now(),$cid]);
            }
        });
    }

    public static function attach(array $data, int $cid): array
    {
        $event = self::current();
        $active = self::state($cid)['active'];
        // Um resultado já pago continua acessível depois do fim/cancelamento.
        if (is_array($active)) $event = $active['event'] ?? ['id'=>0,'variant'=>'normal','machine_type'=>1,'ts_start'=>0,'ts_end'=>0];
        $data['casino_identifier'] = $event ? self::eventIdentifier($event) : 'casino_0_default';
        $data['constants'] = array_replace($data['constants'] ?? [], self::constants($event));
        if (!$event) {
            $data['constants']['casino_event_start_timestamp'] = 0;
            $data['constants']['casino_event_end_timestamp'] = 0;
        }
        if (isset($data['character'])) {
            $data['character']['current_slotmachine_spin'] = is_array($active) ? 1 : 0;
            $data['character']['slotmachine_spin_count'] = (int)Db::value('SELECT slotmachine_spin_count FROM `character` WHERE id = ?',[$cid]);
            $data['character']['ts_last_slotmachine_refill'] = (int)Db::value('SELECT ts_last_slotmachine_refill FROM `character` WHERE id = ?',[$cid]);
        }
        $unlocked = json_decode($data['current_optical_changes']['unlocked_options'] ?? '{}',true) ?: [];
        foreach (Db::rows('SELECT identifier FROM casino_collections WHERE character_id = ? AND reward_type = 11',[$cid]) as $row) {
            $id = (int)$row['identifier'];
            $type = (string)self::reference()['optical_changes'][$id]['type'];
            $unlocked[$type] = array_values(array_unique(array_merge($unlocked[$type] ?? [],[$id])));
        }
        if (isset($data['current_optical_changes'])) $data['current_optical_changes']['unlocked_options'] = json_encode((object)$unlocked);
        return $data;
    }

    /** Pesos locais, separados dos identificadores oficiais. */
    public static function weights(string $variant): array
    {
        $catalog = self::catalogs()[$variant] ?? throw new \InvalidArgumentException('Casino desconhecido.');
        $weights = [1=>25,2=>8,3=>7,4=>4,5=>8,6=>20,7=>10,8=>8,9=>4,10=>4,11=>10,12=>4];
        if (!$catalog['items']) unset($weights[2]);
        if (!$catalog['sidekicks']) unset($weights[4]);
        if (!$catalog['optical']) unset($weights[11]);
        if (in_array($variant,['no_cash_and_xp','only_items','item_casino','complete_your_collection'],true)) unset($weights[1],$weights[6]);
        if (in_array($variant,['only_items','item_casino','complete_your_collection'],true)) $weights = [2=>100];
        if ($variant==='praise_the_sidekicks') $weights[4]=60;
        if ($variant==='go_crazy') $weights[2]=40;
        if ($catalog['optical']) $weights[11]=40;
        if ($variant==='no_cash_and_xp') $weights=[2=>8,3=>7,4=>4,5=>8,7=>10,8=>8];
        return $weights;
    }

    public static function choose(array $weights, ?int $ticket = null): int
    {
        $ticket ??= random_int(1,array_sum($weights));
        if ($ticket<1 || $ticket>array_sum($weights)) throw new \InvalidArgumentException('Sorteio inválido.');
        foreach ($weights as $type=>$weight) { if ($ticket <= $weight) return (int)$type; $ticket-=$weight; }
        throw new \LogicException('Pool vazio.');
    }

    /** Variantes base confirmadas pelo utilizador; todas fazem parte do catálogo. */
    public static function baseRewardVariants(int $type, string $variant = 'normal'): array
    {
        if ($type===7) return $variant==='no_cash_and_xp' ? [['quest_energy'=>20],['quest_energy'=>100]] : [['quest_energy'=>10],['quest_energy'=>60]];
        if ($type===5 && $variant==='no_cash_and_xp') return [['statPoints'=>2],['statPoints'=>20]];
        if ($type===8) return [['training_sessions'=>4],['training_sessions'=>40]];
        if ($type!==3) return [];
        $rewards=[];
        foreach ($variant==='no_cash_and_xp' ? ['booster_hideout2','booster_league2','booster_quest3','booster_stats3','booster_work3'] : ['booster_quest3','booster_stats3','booster_work3'] as $id) foreach ([3,14] as $days) {
            $rewards[]=['booster'=>['booster_id'=>$id,'days'=>$days]];
        }
        return $rewards;
    }

    /** Quantidades fixadas no giro, nunca recalculadas no resgate. */
    public static function numericReward(int $type, int $level, int $quality, string $variant): array
    {
        $variants=self::baseRewardVariants($type,$variant);
        if ($variants) {
            // Qualidade 3 gera três símbolos iguais: garantir a variante máxima.
            if ($type===3) {
                $days=$quality>=3 ? 14 : 3;
                $variants=array_values(array_filter($variants,static fn($r)=>$r['booster']['days']===$days));
                return $variants[random_int(0,count($variants)-1)];
            }
            return $variants[$quality>=3 ? 1 : 0];
        }
        $factor = $quality * ($variant==='great_rewards' ? 2 : 1);
        return match($type) {
            1=>['coins'=>max(1,$level)*100*$factor], 6=>['xp'=>max(1,$level)*200*$factor],
            5=>['statPoints'=>3*$factor], 9=>['hideout_glue'=>max(1,intdiv($level,2))*$factor],
            10=>['hideout_stone'=>max(1,intdiv($level,2))*$factor],
            default=>[],
        };
    }

    private static function missing(int $cid, string $variant, int $type, array $pool): array
    {
        if ($type!==11 && $variant!=='complete_your_collection') return $pool;
        $owned = Db::column('SELECT identifier FROM casino_collections WHERE character_id = ? AND reward_type = ?',[$cid,$type]);
        if ($type===2) $owned = array_merge($owned,Db::column('SELECT identifier FROM items WHERE character_id = ?',[$cid]));
        return array_values(array_diff(array_map('strval',$pool),array_map('strval',$owned)));
    }

    private static function prize(int $cid, array $char, array $event, int $type, int $quality, array $reserved = []): array
    {
        $variant = $event['variant']; $catalog = self::catalogs()[$variant];
        $reward = self::numericReward($type,(int)$char['level'],$quality,$variant);
        $identifier = null;
        if (in_array($type,[2,4,11],true)) {
            $pool = $catalog[[2=>'items',4=>'sidekicks',11=>'optical'][$type]];
            if ($variant==='no_cash_and_xp') {
                if ($type===2) $pool[]='random_epic';
                if ($type===4) $pool=array_keys(array_filter(self::reference()['item_templates'],static fn($row)=>(int)$row['type']===9 && !($row['premium_only'] ?? false)));
            }
            $pool = self::missing($cid,$variant,$type,$pool);
            if ($type===11 || $variant==='complete_your_collection') $pool=array_values(array_diff(array_map('strval',$pool),$reserved));
            if (!$pool) { $type=5; $reward=self::numericReward(5,(int)$char['level'],$quality,$variant); }
            else {
                $identifier = (string)$pool[array_rand($pool)];
                if ($type===11) $reward=['optical_change'=>(int)$identifier];
                else {
                    $itemType=$identifier==='random_epic' ? 0 : (int)self::reference()['item_templates'][$identifier]['type'];
                    if ($type===4) {
                        // Preview como item tipo 9; Sidekick só é criado no resgate.
                        $id=Character::load($cid)->createSpecialItem($identifier,9);
                    } else {
                        $hero=Character::load($cid);
                        if ($identifier==='random_epic') {
                            $id=$hero->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>5]);
                            $identifier=(string)Db::value('SELECT identifier FROM items WHERE id=?',[$id]);
                            $itemType=(int)Db::value('SELECT type FROM items WHERE id=?',[$id]);
                        } else {
                            $slot=[1=>'mask',2=>'cape',3=>'suit',4=>'belt',5=>'boots',6=>'weapon',7=>'gadget',8=>'weapon'][$itemType];
                            $id=$hero->createBlacksmithItem($slot,5,3);
                            Db::exec('UPDATE items SET identifier=?,type=?,required_level=?,charges=? WHERE id=? AND character_id=?',
                                [$identifier,$itemType,(int)$char['level'],$itemType===8?100:0,$id,$cid]);
                            if ($itemType===8) Db::exec('UPDATE items SET stat_stamina=0,stat_strength=0,stat_critical_rating=0,stat_dodge_rating=0,stat_weapon_damage=? WHERE id=?',
                                [Missiles::damage((int)$char['level']+5),$id]);
                        }
                    }
                    if ($itemType!==9 && $itemType!==8) Db::exec('UPDATE items SET quality = ? WHERE id = ?', [3,$id]);
                    if ($variant==='no_cash_and_xp' && $itemType===8) Db::exec('UPDATE items SET quality=1 WHERE id=? AND character_id=?',[$id,$cid]);
                    $reward=['item'=>$id];
                }
            }
        } elseif ($type===12) {
            $budget=max(4,(int)ceil((int)$char['level']*0.8));
            $stats=array_fill_keys(['stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating'],intdiv($budget,4));
            $stats['stat_stamina']+=$budget%4;
            $id=Character::load($cid)->createImprovementItem(random_int(1,7),3,$stats);
            $identifier=(string)Db::value('SELECT identifier FROM items WHERE id = ?',[$id]);
            $reward=['item'=>$id];
        }
        if (isset($reward['item'])) self::prepareRewardItem($cid,(int)$reward['item']);
        $item = isset($reward['item']) ? Db::row('SELECT * FROM items WHERE id = ?',[$reward['item']]) : null;
        return ['type'=>$type,'quality'=>$quality,'identifier'=>$identifier,'reward'=>$reward,'item_snapshot'=>$item];
    }

    private static function prepareRewardItem(int $cid, int $itemId): void
    {
        // Borda warforged +15; a qualidade mantém-se épica. Gemas não são destinos.
        Db::exec('UPDATE items SET ts_availability_start=15 WHERE id=? AND character_id=? AND type BETWEEN 1 AND 8',[$itemId,$cid]);
        Db::exec('UPDATE items SET ts_availability_end=1 WHERE id=? AND character_id=? AND (type BETWEEN 1 AND 7 OR type=9) AND ts_availability_end=0',[$itemId,$cid]);
    }

    public static function luck(int $cid): float
    {
        $inventory=Db::row('SELECT * FROM inventory WHERE character_id = ?',[$cid]) ?? [];
        $counts=[];
        foreach (['mask','cape','suit','belt','boots','weapon','gadget','missiles'] as $slot) {
            $id=(int)($inventory[$slot.'_item_id'] ?? 0);
            if (!$id) continue;
            $identifier=Db::value('SELECT identifier FROM items WHERE id = ? AND character_id = ?',[$id,$cid]);
            $set=self::reference()['item_templates'][$identifier]['item_set_identifier'] ?? '';
            if ($set!=='') $counts[$set]=($counts[$set] ?? 0)+1;
        }
        $luck=0.0;
        foreach ($counts as $set=>$count) foreach (self::reference()['item_set_templates'][$set]['bonus'] ?? [] as $threshold=>$bonus) {
            if ($count >= (int)$threshold && (int)$bonus['type']===6) $luck+=(float)$bonus['value'];
        }
        return $luck;
    }

    public static function spin(int $cid, int $amount): array
    {
        if (!in_array($amount,[1,5,10],true)) throw new GameError('errRequestInvalidParameter');
        return Missiles::transaction([$cid], static function () use ($cid,$amount): array {
            Db::row('SELECT id FROM casino_control WHERE id = 1 FOR UPDATE');
            $event=self::current();
            $char=Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]);
            if (!$event || !$char || (int)$char['level']<(int)self::reference()['casino_min_level']) throw new GameError('errInvalidFunction');
            $state=self::state($cid);
            if ($state['active']!==null) throw new GameError('errSpinCasinoMachineCharacterHasActiveSpin');
            self::syncDaily($cid);
            $count=(int)Db::value('SELECT slotmachine_spin_count FROM `character` WHERE id = ?',[$cid]);
            if (self::MAX_DAILY_SPINS>0 && $count+$amount>self::MAX_DAILY_SPINS) throw new GameError('errSpinCasinoMachineDailyLimitReached');
            // Igual ao cliente: uma fonte cobre o lote inteiro; sem mistura silenciosa.
            $unused=json_decode((string)$char['unused_resources'],true) ?: [];
            $used=json_decode((string)$char['used_resources'],true) ?: [];
            $source=null;
            foreach ([2=>(int)self::reference()['resource_free_casino_spin_usage_amount'],4=>(int)self::reference()['resource_casino_jeton_usage_amount']] as $resource=>$unit) {
                if ((int)($unused[$resource] ?? 0)>=$amount*$unit) { $source=$resource; $cost=$amount*$unit; break; }
            }
            if ($source!==null) {
                $unused[$source]-=$cost;
                if (!$unused[$source]) unset($unused[$source]);
                $used[$source]=($used[$source] ?? 0)+$cost;
                Db::exec('UPDATE `character` SET unused_resources = ?,used_resources = ? WHERE id = ?', [json_encode((object)$unused),json_encode((object)$used),$cid]);
            } else {
                $cost=$amount*(int)self::reference()['casino_premium_currency_amount'];
                if (Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',[$cost,(int)$char['user_id'],$cost])!==1) throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            $prizes=[]; $best=null; $luck=self::luck($cid);
            for ($i=0;$i<$amount;$i++) {
                // +sorte aumenta os pesos dos resultados de qualidade 2/3 (regra local).
                $quality=self::choose([1=>75,2=>20+(int)round($luck*100),3=>5+(int)round($luck*100)]);
                $weights=self::weights($event['variant']);
                if ($event['variant']==='no_cash_and_xp' && (int)$char['level']<60) unset($weights[4]);
                $type=self::choose($weights);
                $reserved=array_values(array_filter(array_column($prizes,'identifier'),static fn($id)=>$id!==null));
                $prize=self::prize($cid,$char,$event,$type,$quality,$reserved);
                $prizes[]=$prize;
                if (!$best || $prize['quality']>$best['quality']) $best=$prize;
            }
            $type=$best['type'];
            $others=array_values(array_diff(array_keys(self::TYPES),[$type]));shuffle($others);
            $slots=[$type,$best['quality']>=2 ? $type : $others[0],$best['quality']>=3 ? $type : $others[1]];
            $active=['event'=>$event,'slots'=>$slots, 'reward_type'=>$type,
                'reward_quality'=>$best['quality'], 'rewards'=>array_map(static fn($p)=>json_encode($p['reward'],JSON_THROW_ON_ERROR),$prizes),
                'prizes'=>$prizes,'amount'=>$amount];
            $state['active']=$active;self::save($cid,$state);
            Db::exec('UPDATE `character` SET slotmachine_spin_count = ? WHERE id = ?',[$count+$amount,$cid]);
            return $active;
        });
    }

    private static function place(int $cid, int $id): void
    {
        $item=Db::row('SELECT id FROM items WHERE id = ? AND character_id = ? FOR UPDATE',[$id,$cid]);
        if (!$item) throw new GameError('errInventoryInvalidItem');
        $inventory=Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE',[$cid]);
        $level=(int)Db::value('SELECT level FROM `character` WHERE id = ?',[$cid]);
        $limit=$level>=(int)Missiles::constants()['inventory_bag3_unlock_level'] ? 18 : ($level>=(int)Missiles::constants()['inventory_bag2_unlock_level'] ? 12 : 6);
        for ($i=1;$i<=$limit;$i++) if ($inventory && (int)$inventory['bag_item'.$i.'_id']===0) {
            Db::exec('UPDATE inventory SET `bag_item'.$i.'_id` = ? WHERE character_id = ?',[$id,$cid]); return;
        }
        throw new GameError('errInventoryNoEmptySlot');
    }

    public static function apply(int $cid, bool $discard): void
    {
        Missiles::transaction([$cid], static function () use ($cid,$discard): void {
            $state=self::state($cid); $active=$state['active'];
            if (!is_array($active)) throw new GameError('errApplyCasinoRewardCharacterHasNoActiveSpin');
            $char=Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]);
            // Resultados antigos têm apenas rewards; mantém-se o resgate sem perder saldos.
            $prizes=$active['prizes'] ?? array_map(static fn($r)=>['type'=>0,'identifier'=>null,'quality'=>1,'reward'=>json_decode($r,true)],$active['rewards']);
            $xp=0;
            foreach ($prizes as $prize) {
                $reward=$prize['reward']; $type=(int)$prize['type']; $identifier=$prize['identifier'];
                $itemId=(int)($reward['item'] ?? 0);
                if ($discard) {
                    if ($itemId>0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?',[$itemId,$cid]);
                    continue;
                }
                if ($itemId>0) {
                    self::prepareRewardItem($cid,$itemId);
                    if ($type===4) {
                        if (!Db::row('SELECT id FROM items WHERE id = ? AND character_id = ?',[$itemId,$cid])) throw new GameError('errInventoryInvalidItem');
                        if (!ItemImprovement::hasSidekickSchema()) throw new GameError('errActionNotSupported');
                        $petId=Sidekick::create($cid,$identifier,3,['stat_base_stamina'=>60,'stat_base_strength'=>100,'stat_base_critical_rating'=>40,'stat_base_dodge_rating'=>23]);
                        Db::exec('UPDATE sidekicks SET item_improvement_id=1 WHERE id=? AND character_id=?',[$petId,$cid]);
                        Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?',[$itemId,$cid]);
                    } else self::place($cid,$itemId);
                }
                Db::exec('UPDATE `character` SET game_currency = game_currency + ?,xp = xp + ?,stat_points_available = stat_points_available + ? WHERE id = ?',
                    [(int)($reward['coins'] ?? 0),(int)($reward['xp'] ?? 0),(int)($reward['stat_points'] ?? $reward['statPoints'] ?? 0),$cid]);
                if (!empty($reward['premium'])) Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?',[(int)$reward['premium'],(int)$char['user_id']]);
                $xp+=(int)($reward['xp'] ?? 0);
                foreach (UserVouchers::KEYS as $key) if (!empty($reward[$key])) UserVouchers::create($cid,$key,(int)$reward[$key]);
                if (!empty($reward['booster'])) {
                    $booster=$reward['booster'];
                    $definition=Live::BOOSTER_CATALOG[$booster['booster_id']] ?? null;
                    $key=[1=>'quest_booster',2=>'stats_booster',3=>'work_booster',5=>'league_booster',8=>'hideout_booster'][(int)($definition[0] ?? 0)] ?? null;
                    if ($key===null) throw new GameError('errRequestInvalidParameter');
                    UserVouchers::create($cid,$key,['type'=>(int)$definition[1],'duration'=>(int)$booster['days']]);
                }
                if ($identifier!==null && in_array($type,[2,4,11],true)) Db::exec('INSERT IGNORE INTO casino_collections (character_id,reward_type,identifier,ts_collected) VALUES (?,?,?,?)',[$cid,$type,$identifier,GameTime::now()]);
                self::recordWin($char,$active,$prize);
            }
            if ($xp>0) { Sidekick::giveXpToActive($cid,(int)round($xp*0.02)); Character::load($cid)->syncLevelFromXp(); }
            $state['active']=null;self::save($cid,$state);
        });
    }

    private static function recordWin(array $char, array $active, array $prize): void
    {
        $map=[1=>[401,'coins'],5=>[404,'stat_points'],6=>[405,'xp'],7=>[406,'quest_energy'],8=>[407,'training_sessions']];
        if (isset($map[$prize['type']])) {
            [$type,$key]=$map[$prize['type']];$value=(string)($prize['reward'][$key] ?? ($key==='stat_points' ? $prize['reward']['statPoints'] : 0));
        } elseif (in_array((int)$prize['type'],[2,4],true)) {
            $type=402;$item=$prize['item_snapshot'] ?? [];
            $value='('.((int)$prize['type']===4?'sidekick':'item').':'.$prize['identifier']
                .';sa:'.(int)($item['stat_stamina'] ?? 0).';sr:'.(int)($item['stat_strength'] ?? 0)
                .';c:'.(int)($item['stat_critical_rating'] ?? 0).';do:'.(int)($item['stat_dodge_rating'] ?? 0).';)';
        } elseif ((int)$prize['type']===3) {
            $type=403;$value='(booster:'.$prize['reward']['booster']['booster_id'].';d:'.$prize['reward']['booster']['days'].';)';
        } else return; // O cliente não define mensagens para símbolos 9–12.
        // Campo/enum confirmados em SlotmachineLogMessage, sem dados de sessão.
        $message=['type'=>$type,'character_id'=>(int)$char['id'],'character_name'=>htmlspecialchars($char['name'],ENT_QUOTES,'UTF-8'),
            'character_gender'=>$char['gender'],'character_level'=>(int)$char['level'],'timestamp'=>GameTime::now(),
            'value1'=>$value,'value2'=>'','value3'=>''];
        Db::exec('INSERT INTO casino_wins (character_id,event_id,ts_created,message) VALUES (?,?,?,?)',
            [(int)$char['id'],(int)($active['event']['id'] ?? 0),GameTime::now(),json_encode($message,JSON_THROW_ON_ERROR)]);
    }

    public static function room(int $cid, bool $join): void
    {
        if ($join) Db::exec('INSERT INTO casino_rooms (character_id,ts_seen) VALUES (?,?) ON DUPLICATE KEY UPDATE ts_seen=VALUES(ts_seen)',[$cid,GameTime::now()]);
        else Db::exec('DELETE FROM casino_rooms WHERE character_id = ?',[$cid]);
    }

    public static function wins(int $cid): array
    {
        Db::exec('UPDATE casino_rooms SET ts_seen = ? WHERE character_id = ?', [GameTime::now(),$cid]);
        return Db::column('SELECT message FROM casino_wins WHERE ts_created <= ? AND ts_created > ? ORDER BY id DESC LIMIT 30', [GameTime::now(),GameTime::now()-3*86400]);
    }

    /** Catálogo oficial capturado; nomes traduzidos, restantes campos preservados. */
    public static function capturedNoCashRewards(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../data/casino_no_cash_and_xp_rewards.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public static function sections(int $cid): array
    {
        $event=self::current();
        if (!$event) return [];
        $variant=$event['variant'];$catalog=self::catalogs()[$variant];
        if ($variant==='no_cash_and_xp') return self::capturedNoCashRewards();
        $level=(int)Db::value('SELECT level FROM `character` WHERE id = ?',[$cid]);
        $entries=[];
        $generic=[1=>1,3=>8,5=>5,6=>2,7=>3,8=>4,9=>12,10=>13];
        foreach (self::weights($variant) as $type=>$weight) {
            if (in_array($type,[2,4,11],true)) {
                foreach ($catalog[[2=>'items',4=>'sidekicks',11=>'optical'][$type]] as $id) {
                    $entries[]=['genericRewardType'=>[2=>6,4=>7,11=>14][$type],'genericRewardAmount'=>1,
                        'genericRewardData'=>$type===11 ? ['optical_change_id'=>(int)$id] : ['identifier'=>$id]];
                }
            } elseif (self::baseRewardVariants($type)) {
                foreach (self::baseRewardVariants($type) as $reward) {
                    $entries[]=['genericRewardType'=>$generic[$type],
                        'genericRewardAmount'=>$type===3 ? 1 : reset($reward),
                        'genericRewardData'=>$type===3 ? $reward['booster'] : null];
                }
            } elseif ($type===12) {
                $entries[]=['genericRewardType'=>16,'genericRewardAmount'=>1,'genericRewardData'=>['identifier'=>'improvement_mask_epic']];
            } else {
                $reward=self::numericReward($type,$level,1,$variant);$amount=reset($reward);
                $entries[]=['genericRewardType'=>$generic[$type],'genericRewardAmount'=>$amount,'genericRewardData'=>null];
            }
        }
        return [['name'=>'Prémios do casino — '.str_replace('_',' ',$variant),'headerColorIndex'=>0,
            'preventDuplicates'=>$variant==='complete_your_collection' || (bool)$catalog['optical'],'rewards'=>$entries]];
    }

    public static function hub(): array
    {
        $event=self::current();
        return $event ? [['id'=>(int)$event['id'],'identifier'=>self::eventIdentifier($event).'_casino','data'=>'{}']] : [];
    }
}
