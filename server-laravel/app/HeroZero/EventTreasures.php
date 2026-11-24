<?php
declare(strict_types=1);
namespace HeroZero;

/** Caça às Toupeiras: catálogo oficial e geração/balanceamento local documentados. */
final class EventTreasures
{
    public static function reference(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('event_treasures_v1',$src,static function()use($src):array {
            $all=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
            return array_filter($all,static fn($k)=>str_starts_with($k,'event_treasure')||str_starts_with($k,'quest_instant_finish_range')
                ||in_array($k,['item_templates','max_level']),ARRAY_FILTER_USE_KEY);
        });
    }
    public static function definition(string $id): array
    {
        return self::reference()['event_treasures'][$id]??throw new GameError('errRequestInvalidParameter');
    }
    public static function location(string $id): array
    {
        return self::reference()['event_treasure_locations'][self::definition($id)['location']]['levels'];
    }
    public static function schedule(string $id,int $start,int $end): int
    {
        self::definition($id);
        if($end<=$start||$end<=GameTime::now())throw new \InvalidArgumentException('Intervalo inválido ou terminado.');
        return Missiles::transaction([],static function()use($id,$start,$end):int {
            Db::row('SELECT id FROM event_treasure_control WHERE id=1 FOR UPDATE');
            if(Db::row('SELECT id FROM event_treasure_occurrences WHERE enabled=1 AND ts_start<? AND ts_end>? LIMIT 1 FOR UPDATE',[$end,$start]))
                throw new \InvalidArgumentException('Já existe uma Caça às Toupeiras nesse intervalo.');
            Db::exec('INSERT INTO event_treasure_occurrences (identifier,ts_start,ts_end) VALUES (?,?,?)',[$id,$start,$end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }
    public static function cancel(int $id): void
    {
        Missiles::transaction([],static function()use($id):void {
            Db::row('SELECT id FROM event_treasure_control WHERE id=1 FOR UPDATE');
            if(!Db::row('SELECT id FROM event_treasure_occurrences WHERE id=? FOR UPDATE',[$id]))throw new \InvalidArgumentException('Ocorrência inexistente.');
            Db::exec('UPDATE event_treasure_occurrences SET enabled=0 WHERE id=?',[$id]);
        });
    }
    public static function current(): ?array
    {
        return Db::row('SELECT * FROM event_treasure_occurrences WHERE enabled=1 AND ts_start<=? AND ts_end>? ORDER BY id LIMIT 1',[GameTime::now(),GameTime::now()]);
    }
    public static function state(int $cid): array
    {
        $r=Db::row('SELECT * FROM event_treasure_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1',[$cid]);
        return $r?json_decode($r['payload'],true,512,JSON_THROW_ON_ERROR)+['id'=>(int)$r['id'],'identifier'=>$r['identifier']]:['id'=>0,'identifier'=>''];
    }
    private static function active(int $cid): array
    {
        return Db::row('SELECT * FROM event_treasure_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid])
            ??throw new GameError('errInvalidFunction');
    }
    private static function occurrence(array $row): array
    {
        return Db::row('SELECT * FROM event_treasure_occurrences WHERE id=?',[(int)$row['occurrence_id']])??throw new GameError('errInvalidFunction');
    }
    private static function available(array $row): bool
    {
        $e=self::occurrence($row);
        return (int)$e['enabled']===1&&(int)$e['ts_start']<=GameTime::now()&&(int)$e['ts_end']>GameTime::now();
    }
    private static function requireAvailable(array $row): void
    {
        if(!self::available($row))throw new GameError('errInvalidFunction');
    }
    /** Entrega local de pás; chamada dentro da transação da compra. */
    public static function grantPurchaseShovels(int $cid,int $amount): void
    {
        if ($amount<1) throw new GameError('errRequestInvalidParameter');
        $row=self::active($cid);self::requireAvailable($row);
        $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        $p['shovels']+=$amount;self::save($row,$p);
    }

    private static function save(array $row,array $p): void
    {
        Db::exec('UPDATE event_treasure_progress SET payload=? WHERE id=? AND character_id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)$row['id'],(int)$row['character_id']]);
    }
    private static function debit(array $char,int $amount): void
    {
        if($amount<1)throw new GameError('errInvalidFunction');
        if(Db::exec('UPDATE `user` SET premium_currency=premium_currency-? WHERE id=? AND premium_currency>=?',[$amount,(int)$char['user_id'],$amount])!==1)
            throw new GameError('errRemovePremiumCurrencyNotEnough');
    }
    /** Fórmula local dos bónus. Enum confirmado em UiEventTreasureLevelRewardLine. */
    public static function bonus(int $type,float $factor,int $level): array
    {
        return match($type) {
            0=>['premium'=>max(1,(int)round($factor))],
            1=>['coins'=>max(1,(int)round($level*100*$factor))],
            2=>['xp'=>$level>=(int)self::reference()['max_level']?0:max(1,(int)round((Character::xpForLevel($level+1)-Character::xpForLevel($level))*.1*$factor))],
            3=>['quest_energy'=>max(1,(int)round($factor))],
            4=>['training_sessions'=>max(1,(int)round($factor))],
            5=>['statPoints'=>max(1,(int)round($factor))],
            default=>throw new \RuntimeException('Tipo de bónus não suportado: '.$type),
        };
    }
    /** Tabuleiro completo fica privado; apenas células abertas são serializadas. */
    public static function board(array $definition,int $level): array
    {
        $size=(int)$definition['grid_size'];$keys=[];
        for($x=0;$x<$size;$x++)for($y=0;$y<$size;$y++)$keys[]=$x.'_'.$y;
        shuffle($keys);$hidden=[];$i=0;
        for($n=0;$n<(int)$definition['tokens'];$n++)$hidden[$keys[$i++]]=['type'=>1];
        foreach($definition['bonus_reward'] as $n=>$type)$hidden[$keys[$i++]]=['type'=>2,'reward'=>self::bonus((int)$type,(float)$definition['bonus_reward_factor'][$n],$level)];
        return ['hidden'=>$hidden,'cells'=>[], 'info'=>['found'=>0,'needed'=>(int)$definition['tokens'],
            'boni_found'=>0,'boni_amount'=>count($definition['bonus_reward']),'ts_unlock'=>GameTime::now()+(int)$definition['unlock_duration']]];
    }
    private static function fresh(): array
    {
        return ['tokens'=>0,'shovels'=>0,'last_free'=>0,'level'=>0,'levels'=>[],'rewards'=>[],'claimed'=>[],'unique'=>[],'round'=>1];
    }
    public static function assign(int $cid,string $identifier): void
    {
        self::definition($identifier);
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$identifier):void {
            Db::row('SELECT id FROM event_treasure_control WHERE id=1 FOR UPDATE');
            $e=self::current();
            if(!$e||$e['identifier']!==$identifier||(int)$char['level']<(int)self::reference()['event_treasure_min_level'])throw new GameError('errRequestInvalidParameter');
            if(Db::row('SELECT id FROM event_treasure_progress WHERE character_id=? AND (closed=0 OR occurrence_id=?) LIMIT 1 FOR UPDATE',[$cid,(int)$e['id']]))throw new GameError('errAssignTreasureEventAlreadyAssigned');
            Db::exec('INSERT INTO event_treasure_progress (character_id,occurrence_id,identifier,payload) VALUES (?,?,?,?)',[$cid,(int)$e['id'],$identifier,json_encode(self::fresh(),JSON_THROW_ON_ERROR)]);
        });
    }
    public static function next(int $cid): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid):void {
            $row=self::active($cid);self::requireAvailable($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            $old=$p['levels'][$p['level']]??null;
            if($old&&$old['info']['found']<$old['info']['needed'])throw new GameError('errInvalidFunction');
            $next=$p['level']+1;$d=self::location($row['identifier'])[$next]??throw new GameError('errInvalidFunction');
            $p['levels'][$next]=self::board($d,(int)$char['level']);$p['level']=$next;self::save($row,$p);
        });
    }
    public static function skipCost(int $seconds): int
    {
        if($seconds<=0)return 0;$c=self::reference();
        foreach([1,2,3] as $i)if($seconds<60*$c['quest_instant_finish_range'.$i.'_duration'])return (int)$c['quest_instant_finish_range'.$i.'_premium_amount'];
        return (int)$c['quest_instant_finish_range4_premium_amount'];
    }
    public static function skip(int $cid,int $level): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$level):void {
            $row=self::active($cid);self::requireAvailable($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if(!isset($p['levels'][$level])||$level!==$p['level'])throw new GameError('errInvalidFunction');
            $cost=self::skipCost($p['levels'][$level]['info']['ts_unlock']-GameTime::now());self::debit($char,$cost);
            $p['levels'][$level]['info']['ts_unlock']=GameTime::now();self::save($row,$p);
        });
    }
    public static function free(int $cid): void
    {
        StoryDungeon::transaction($cid,static function()use($cid):void {
            $row=self::active($cid);self::requireAvailable($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$c=self::reference();
            if($p['last_free']!==0&&$p['last_free']+(int)$c['event_treasure_free_reveal_item_cooldown']>GameTime::now())throw new GameError('errClaimFreeTreasureRevealItemsCooldownActive');
            $p['shovels']+=(int)$c['event_treasure_free_reveal_item_amount'];$p['last_free']=GameTime::now();self::save($row,$p);
        });
    }
    public static function open(int $cid,int $level,int $x,int $y,bool $premium): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$level,$x,$y,$premium):void {
            $row=self::active($cid);self::requireAvailable($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            $d=self::location($row['identifier'])[$level]??throw new GameError('errRequestInvalidParameter');
            if(!isset($p['levels'][$level])||$x<0||$y<0||$x>=(int)$d['grid_size']||$y>=(int)$d['grid_size'])throw new GameError('errRequestInvalidParameter');
            $b=&$p['levels'][$level];$key=$x.'_'.$y;
            if($b['info']['ts_unlock']>GameTime::now())throw new GameError('errInvalidFunction');
            if(isset($b['cells'][$key]))throw new GameError('errAlreadyOpened');
            if($premium)self::debit($char,(int)self::reference()['event_treasure_open_cell_premium_cost']);
            elseif($p['shovels']<1)throw new GameError('errUseItemNotEnough');else $p['shovels']--;
            $cell=$b['hidden'][$key]??['type'=>3];$cell+=['x'=>$x,'y'=>$y,'collected'=>false];
            if($cell['type']===1){$b['info']['found']++;$p['tokens']++;}
            elseif($cell['type']===2)$b['info']['boni_found']++;
            else {
                $neighbors=0;
                for($dx=-1;$dx<=1;$dx++)for($dy=-1;$dy<=1;$dy++)if(($dx||$dy)&&isset($b['hidden'][($x+$dx).'_'.($y+$dy)]))$neighbors++;
                $cell['neighbours']=$neighbors;
            }
            $b['cells'][$key]=$cell;unset($b);self::unlockRewards($char,$row,$p);self::save($row,$p);
        });
    }
    /** Previews só são criados quando o patamar é desbloqueado, nunca no estado público de células escondidas. */
    private static function unlockRewards(array $char,array $row,array &$p): void
    {
        foreach(self::definition($row['identifier'])['reward_index'] as $i=>$d) {
            if($p['tokens']<(int)$d['required_token_amount']||isset($p['rewards'][$i]))continue;
            $ref=$d['reward_reference'];$type=(int)$d['reward_type'];$cid=(int)$char['id'];
            // Modificações únicas já entregues nesta ocorrência recebem alternativa ao reiniciar.
            if($type===12&&in_array($ref,$p['unique'],true)) {
                $c=self::reference();$p['rewards'][$i]=['statPoints'=>(int)round($c['event_treasure_unique_reward_alternative_factor'])];continue;
            }
            if($type===5) {
                $tpl=self::reference()['item_templates'][$ref]??throw new \RuntimeException('Template de prémio inexistente.');
                $id=Character::load($cid)->createEventRewardItem($ref,(int)$tpl['type'],(int)$tpl['quality']);
                if((int)$tpl['type']===8)Db::exec('UPDATE items SET charges=? WHERE id=?',[Missiles::PACKAGE_CHARGES,$id]);
            } elseif($type===12) {
                if(!preg_match('/^improvement_(mask|cape|suit|belt|boots|weapon|gadget|sidekick)_(common|rare|epic|legendary)$/D',$ref,$m))throw new \RuntimeException('Modificação inválida.');
                $slot=array_search($m[1],[1=>'mask',2=>'cape',3=>'suit',4=>'belt',5=>'boots',6=>'weapon',7=>'gadget',9=>'sidekick'],true);
                $quality=array_search($m[2],[1=>'common',2=>'rare',3=>'epic',4=>'legendary'],true);
                // Orçamento local, como no casino; fator dos assets não é quantidade de atributos.
                $budget=max(4,(int)ceil((int)$char['level']*.8));$stats=array_fill_keys(['stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating'],intdiv($budget,4));$stats['stat_stamina']+=$budget%4;
                $id=Character::load($cid)->createImprovementItem($slot,$quality,$stats);
            } else throw new \RuntimeException('Tipo de prémio não suportado.');
            $p['rewards'][$i]=['item'=>$id];
        }
    }
    private static function deliver(array $char,array $reward,bool $discard): void
    {
        $cid=(int)$char['id'];$id=(int)($reward['item']??0);
        if($id) {
            $item=Db::row('SELECT * FROM items WHERE id=? AND character_id=? FOR UPDATE',[$id,$cid])??throw new GameError('errInventoryInvalidItem');
            if($discard)Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);
            elseif((int)$item['type']===9) {
                Sidekick::create($cid,$item['identifier'],(int)$item['quality'],['stat_base_stamina'=>(int)$item['stat_stamina'],'stat_base_strength'=>(int)$item['stat_strength'],
                    'stat_base_critical_rating'=>(int)$item['stat_critical_rating'],'stat_base_dodge_rating'=>(int)$item['stat_dodge_rating']]);
                Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);
            } else {
                $inv=Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]);$c=Missiles::constants();
                $limit=(int)$char['level']>=(int)$c['inventory_bag3_unlock_level']?18:((int)$char['level']>=(int)$c['inventory_bag2_unlock_level']?12:6);
                $slot=null;for($i=1;$i<=$limit;$i++)if($inv&&(int)$inv['bag_item'.$i.'_id']===0){$slot='bag_item'.$i.'_id';break;}
                if(!$slot)throw new GameError('errInventoryNoEmptySlot');
                Db::exec("UPDATE inventory SET `$slot`=? WHERE character_id=?",[$id,$cid]);
            }
        }
        Db::exec('UPDATE `character` SET game_currency=game_currency+?,xp=xp+?,stat_points_available=stat_points_available+? WHERE id=?',[(int)($reward['coins']??0),(int)($reward['xp']??0),(int)($reward['statPoints']??0),$cid]);
        if(!empty($reward['premium']))Db::exec('UPDATE `user` SET premium_currency=premium_currency+? WHERE id=?',[(int)$reward['premium'],(int)$char['user_id']]);
        foreach(UserVouchers::KEYS as $key)if(!empty($reward[$key]))UserVouchers::create($cid,$key,(int)$reward[$key]);
        if(!empty($reward['xp']))Character::load($cid)->syncLevelFromXp();
    }
    public static function collect(int $cid,int $level,int $x,int $y,bool $discard=false): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$level,$x,$y,$discard):void {
            $row=self::active($cid);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$key=$x.'_'.$y;
            $cell=$p['levels'][$level]['cells'][$key]??throw new GameError('errInvalidFunction');
            if($cell['type']!==2)throw new GameError('errInvalidFunction');
            if($cell['collected'])throw new GameError('errCollectTreasureEventCellRewardAlreadyCollected');
            self::deliver($char,$cell['reward'],$discard);$p['levels'][$level]['cells'][$key]['collected']=true;self::save($row,$p);
        });
    }
    public static function claim(int $cid,int $index,bool $discard=false): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$index,$discard):void {
            $row=self::active($cid);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if(isset($p['claimed'][$index]))throw new GameError('errCollectTreasureEventRewardAlreadyCollected');
            $reward=$p['rewards'][$index]??throw new GameError('errInvalidFunction');
            self::deliver($char,$reward,$discard);$p['claimed'][$index]=true;
            $d=self::definition($row['identifier'])['reward_index'][$index];
            if(!$discard&&(int)$d['reward_type']===12)$p['unique'][]=$d['reward_reference'];
            self::save($row,$p);
        });
    }
    public static function reset(int $cid): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid):void {
            $row=self::active($cid);self::requireAvailable($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            $defs=self::definition($row['identifier'])['reward_index'];$last=$defs[array_key_last($defs)]['required_token_amount'];
            if($p['tokens']<$last||self::pending($p))throw new GameError('errInvalidFunction');
            self::debit($char,(int)self::reference()['event_treasure_reset_progress_premium_cost']);
            $new=self::fresh();foreach(['shovels','last_free','unique'] as $key)$new[$key]=$p[$key];$new['round']=$p['round']+1;self::save($row,$new);
        });
    }
    private static function pending(array $p): bool
    {
        foreach($p['rewards'] as $i=>$r)if(!isset($p['claimed'][$i]))return true;
        foreach($p['levels'] as $b)foreach($b['cells'] as $cell)if($cell['type']===2&&!$cell['collected'])return true;
        return false;
    }
    public static function attach(array $data,int $cid): array
    {
        return StoryDungeon::transaction($cid,static function(array $char)use($data,$cid):array {
            unset($data['treasure_event']);$row=Db::row('SELECT * FROM event_treasure_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid]);
            if($row) {
                $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$available=self::available($row);
                if(!$available&&!self::pending($p)){Db::exec('UPDATE event_treasure_progress SET closed=1 WHERE id=?',[(int)$row['id']]);$row=null;}
            }
            $id=0;
            if($row) {
                $e=self::occurrence($row);$levels=[];
                foreach($p['levels'] as $i=>$b) {
                    $cells=[];foreach($b['cells'] as $key=>$cell){if(isset($cell['reward']))$cell['reward']=json_encode($cell['reward'],JSON_THROW_ON_ERROR);$cells[$key]=$cell;}
                    $levels[$i]=['info'=>$b['info'],'cells'=>(object)$cells];
                }
                $rewards=[];foreach($p['rewards'] as $i=>$r)$rewards[$i]=json_encode($r,JSON_THROW_ON_ERROR);
                $id=(int)$row['id'];
                $data['treasure_event']=['id'=>$id,'identifier'=>$row['identifier'],'status'=>$available?1:3,
                    'start_date'=>EventQuests::serverDate((int)$e['ts_start']),'end_date'=>EventQuests::serverDate($available?(int)$e['ts_end']:min((int)$e['ts_end'],GameTime::now())),
                    'event_tokens'=>$p['tokens'],'event_reveal_items'=>$p['shovels'],'ts_reveal_item_collected'=>$p['last_free'],
                    'current_level'=>$p['level'],'rewards'=>json_encode((object)$rewards,JSON_THROW_ON_ERROR),
                    'collected_rewards'=>json_encode((object)$p['claimed'],JSON_THROW_ON_ERROR),'levels'=>json_encode((object)$levels,JSON_THROW_ON_ERROR)];
                $data['items']=Character::load($cid)->itemsData(Live::template('autoLoginUser')['items'][0]??[]);
            } else {
                $e=self::current();
                if($e&&(int)$char['level']>=(int)self::reference()['event_treasure_min_level']&&!Db::row('SELECT id FROM event_treasure_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$e['id']]))
                    $data['treasure_event']=['identifier'=>$e['identifier'],'start_date'=>EventQuests::serverDate((int)$e['ts_start']),'end_date'=>EventQuests::serverDate((int)$e['ts_end'])];
            }
            if(isset($data['character']))$data['character']['treasure_event_id']=$id;
            return $data;
        });
    }
    public static function hub(int $cid): array
    {
        $data=self::attach(['character'=>[]],$cid);$e=$data['treasure_event']??null;
        return $e?[['id'=>1100000000+(int)($e['id']??0),'identifier'=>$e['identifier'].'_treasure','data'=>'{}']]:[];
    }
}
