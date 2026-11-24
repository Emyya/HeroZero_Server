<?php
declare(strict_types=1);
namespace HeroZero;

/** Eventos de coleção: templates oficiais, ocorrências e política local documentada. */
final class EventQuests
{
    public const TIME=1, FIGHT_WON=2, ENERGY=4, TRAINING=5, WORLDBOSS=6,
        ITEM=7, QUEST=8, DUEL=9, WASH=10, SHOP=11;

    public static function reference(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('event_quests_v1',$src,static function()use($src):array {
            $all=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
            return array_filter($all,static fn($k)=>str_starts_with($k,'event_quest_')
                ||str_starts_with($k,'coins_per_time_')||in_array($k,['event_quests','event_items','item_templates','optical_changes','quest_templates','max_level']),ARRAY_FILTER_USE_KEY);
        });
    }

    public static function definition(string $identifier): array
    {
        return self::reference()['event_quests'][$identifier] ?? throw new GameError('errCreateInvalidEventQuestIdentifier');
    }

    public static function schedule(string $identifier,int $start,int $end): int
    {
        self::definition($identifier);
        if ($end<=$start||$end<=GameTime::now()) throw new \InvalidArgumentException('Intervalo inválido ou já terminado.');
        return Missiles::transaction([],static function()use($identifier,$start,$end):int {
            Db::row('SELECT id FROM event_quest_control WHERE id=1 FOR UPDATE');
            if (Db::row('SELECT id FROM event_quest_occurrences WHERE enabled=1 AND ts_start<? AND ts_end>? LIMIT 1 FOR UPDATE',[$end,$start]))
                throw new \InvalidArgumentException('Já existe um evento de coleção nesse intervalo.');
            Db::exec('INSERT INTO event_quest_occurrences (identifier,ts_start,ts_end) VALUES (?,?,?)',[$identifier,$start,$end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }

    public static function cancel(int $id): void
    {
        Missiles::transaction([],static function()use($id):void {
            Db::row('SELECT id FROM event_quest_control WHERE id=1 FOR UPDATE');
            if (!Db::row('SELECT id FROM event_quest_occurrences WHERE id=? FOR UPDATE',[$id])) throw new \InvalidArgumentException('Ocorrência inexistente.');
            Db::exec('UPDATE event_quest_occurrences SET enabled=0 WHERE id=?',[$id]);
        });
    }

    public static function current(): ?array
    {
        return Db::row('SELECT * FROM event_quest_occurrences WHERE enabled=1 AND ts_start<=? AND ts_end>? ORDER BY id LIMIT 1',[GameTime::now(),GameTime::now()]);
    }

    public static function matches(string $reference,string $identifier): bool
    {
        return $reference===''||$reference==='all'||preg_match('/^'.str_replace('\\*','.*',preg_quote($reference,'/')).'$/D',$identifier)===1;
    }

    /** Fórmula exata de Q.getEventQuestObjectiveRewardAmount; final usa escala local. */
    public static function amount(int $type,float $factor,int $level): int
    {
        $c=self::reference();
        return match($type) {
            1=>(int)round(round(round($c['coins_per_time_base']+$c['coins_per_time_scale']*($c['coins_per_time_level_scale']*$level)**$c['coins_per_time_level_exp'],3)
                *$c['event_quest_objective_reward_game_currency_percentage_base']*$factor*$c['event_quest_objective_reward_game_currency_time'])+$level**$c['event_quest_objective_reward_game_currency_exp']),
            2=>(int)($c['event_quest_objective_reward_premium_currency_base']*round($factor)),
            3=>(int)($c['event_quest_objective_reward_stat_points_base']*round($factor)),
            4=>$level>=(int)$c['max_level']?0:(int)round((Character::xpForLevel($level+1)-Character::xpForLevel($level))*$c['event_quest_objective_reward_xp_percentage_base']*$factor),
            default=>throw new \InvalidArgumentException('Tipo de prémio numérico desconhecido.'),
        };
    }

    public static function skipCost(int $value,int $target): int
    {
        if ($target<1) throw new \InvalidArgumentException('Meta inválida.');
        return (int)ceil((1-min(1,max(0,$value)/$target))*(int)self::reference()['event_quest_objective_instant_finish_amount']);
    }

    private static function active(int $cid): ?array
    {
        return Db::row('SELECT * FROM event_quest_progress WHERE character_id=? AND claimed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid]);
    }

    private static function payload(array $row): array
    {
        return json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
    }

    private static function save(array $row,array $p): void
    {
        Db::exec('UPDATE event_quest_progress SET status=?,payload=?,claimed=? WHERE id=? AND character_id=?',
            [(int)$row['status'],json_encode($p,JSON_THROW_ON_ERROR),(int)$row['claimed'],(int)$row['id'],(int)$row['character_id']]);
    }

    private static function complete(array $row,array $p,bool $collected): bool
    {
        foreach(self::definition($row['identifier'])['objectives'] as $o) {
            $v=(int)($p['values'][$o['index']]??0);
            if ($collected?$v<=(int)$o['value']:$v<(int)$o['value']) return false;
        }
        return true;
    }

    private static function expire(array &$row,array $p): void
    {
        $enabled=Db::value('SELECT enabled FROM event_quest_occurrences WHERE id=?',[(int)$row['occurrence_id']]);
        if ((int)$row['status']===1 && ((int)$row['ts_end']<=GameTime::now()||!(int)$enabled)) {
            $row['status']=self::complete($row,$p,false)?5:3;
            self::save($row,$p);
        }
    }

    /** Preview fixado na aceitação; só é entregue no resgate correspondente. */
    private static function reward(array $char,int $type,float $factor,string $reference,bool $objective=false): array
    {
        $cid=(int)$char['id'];$level=(int)$char['level'];
        if ($type>=1 && $type<=4) {
            $amount=$objective?self::amount($type,$factor,$level):match($type) {
                1=>max(1,(int)round($factor*5000)),2=>(int)round($factor),3=>(int)round($factor),4=>max(1,(int)round($factor*10000)),
            };
            return [[1=>'coins',2=>'premium',3=>'statPoints',4=>'xp'][$type]=>$amount];
        }
        if (!$objective && $type===5) {
            if (!isset(Live::BOOSTER_CATALOG[$reference])) throw new \RuntimeException('Energético inválido: '.$reference);
            return ['booster'=>['booster_id'=>$reference,'days'=>(int)round($factor)]];
        }
        if (!$objective && $type===6) return ['training_sessions'=>(int)round($factor)];
        if ($objective && $type===6) {
            if (!isset(self::reference()['optical_changes'][(int)$reference])) throw new \RuntimeException('Alteração visual inválida.');
            return ['optical_change'=>(int)$reference];
        }
        if (($objective && $type===5)||(!$objective && $type===7)) {
            if ($reference==='random_epic') {
                $pool=array_keys(array_filter(self::reference()['item_templates'],static fn($v)=>in_array((int)$v['type'],range(1,7),true)&&(int)$v['quality']===3));
                if (!$pool) throw new \RuntimeException('Sem equipamentos épicos.');
                $reference=$pool[array_rand($pool)];
            }
            $tpl=self::reference()['item_templates'][$reference]??throw new \RuntimeException('Item inválido: '.$reference);
            $id=Character::load($cid)->createWorldbossRewardItem($reference,(int)$tpl['type'],(int)$tpl['quality']);
            if ((int)$tpl['type']===8) Db::exec('UPDATE items SET charges=? WHERE id=?',[Missiles::PACKAGE_CHARGES,$id]);
            return ['item'=>$id];
        }
        throw new \RuntimeException('Prémio de evento não suportado: '.$type);
    }

    public static function assign(int $cid,string $identifier): void
    {
        self::definition($identifier);
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$identifier):void {
            // Serializar aceitação com cancelamento/agendamento.
            Db::row('SELECT id FROM event_quest_control WHERE id=1 FOR UPDATE');
            $event=self::current();
            if (!$event||$event['identifier']!==$identifier||(int)$char['level']<(int)self::reference()['event_quest_min_level']) throw new GameError('errCreateInvalidEventQuestIdentifier');
            if (self::active($cid)||Db::row('SELECT id FROM event_quest_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$event['id']])) throw new GameError('errAssignEventQuestAlreadyAssigned');
            $d=self::definition($identifier);$p=['values'=>[],'objectives'=>[],'choices'=>[],'base'=>[]];
            foreach($d['objectives'] as $key=>$o) {
                $p['values'][$o['index']]=0;
                if ((int)$o['reward_type']>=5) $p['objectives'][$key]=self::reward($char,(int)$o['reward_type'],(float)$o['reward_factor'],$o['reward_reference'],true);
            }
            foreach([1,2] as $i) {
                foreach(self::reward($char,(int)$d['reward'.$i.'_type'],(float)$d['reward'.$i.'_factor'],$d['reward'.$i.'_flag']) as $key=>$v) {
                    if (is_int($v)) $p['base'][$key]=($p['base'][$key]??0)+$v;else $p['base'][$key]=$v;
                }
            }
            foreach([1,2,3] as $i) $p['choices'][$i]=self::reward($char,(int)$d['reward_item'.$i.'_type'],(float)$d['reward_item'.$i.'_factor'],$d['reward_item'.$i.'_flag']);
            Db::exec('INSERT INTO event_quest_progress (character_id,occurrence_id,identifier,ts_creation,ts_end,payload) VALUES (?,?,?,?,?,?)',
                [$cid,(int)$event['id'],$identifier,GameTime::now(),(int)$event['ts_end'],json_encode($p,JSON_THROW_ON_ERROR)]);
            $id=(int)Db::pdo()->lastInsertId();
            Db::exec('UPDATE `character` SET event_quest_id=? WHERE id=?',[$id,$cid]);
            self::refreshQuestOffers($cid);
        });
    }

    /** Chamado dentro da transação da atividade; não cria progresso retroativo. */
    public static function record(int $cid,int $type,int $amount=1,string $reference=''): void
    {
        if ($amount<1) return;
        StoryDungeon::transaction($cid,static function()use($cid,$type,$amount,$reference):void {
            $row=self::active($cid);if (!$row) return;$p=self::payload($row);self::expire($row,$p);
            if ((int)$row['status']!==1) return;
            foreach(self::definition($row['identifier'])['objectives'] as $o) {
                $idx=$o['index'];$value=(int)$p['values'][$idx];$target=(int)$o['value'];
                if ((int)$o['type']===$type && $value<$target && self::matches($o['reference'],$reference)) $p['values'][$idx]=min($target,$value+$amount);
            }
            self::save($row,$p);
        });
    }

    /** Um só objeto por missão; seleção/drop local com chances dos assets. */
    public static function questReward(int $cid,string $identifier): array
    {
        $row=self::active($cid);if (!$row||(int)$row['status']!==1||(int)$row['ts_end']<=GameTime::now()) return [];
        if (!(int)Db::value('SELECT enabled FROM event_quest_occurrences WHERE id=?',[(int)$row['occurrence_id']])) return [];
        $p=self::payload($row);$c=self::reference();$eligible=[];
        foreach(self::definition($row['identifier'])['objectives'] as $o) {
            if ((int)$o['type']!==self::ITEM||(int)$p['values'][$o['index']]>=(int)$o['value']) continue;
            $item=$c['event_items'][$o['reference']]??null;
            if ($item&&self::matches($item['reward_reference'],$identifier)) $eligible[$o['reference']]=$item;
        }
        if (!$eligible) return [];
        $id=array_rand($eligible);return random_int(1,1000000)<=(int)round($eligible[$id]['reward_chance']*1000000)?['event_item'=>$id,'event_quest_occurrence'=>(int)$row['occurrence_id']]:[];
    }

    /** Impede que um drop de uma ocorrência antiga conte numa edição nova. */
    public static function collectQuestItem(int $cid,array $rewards): void
    {
        $row=self::active($cid);
        if ($row && (int)$row['occurrence_id']===(int)($rewards['event_quest_occurrence']??0) && !empty($rewards['event_item'])) {
            self::record($cid,self::ITEM,1,(string)$rewards['event_item']);
        }
    }

    public static function themedQuest(int $cid,int $type,string $fallback): string
    {
        $row=self::active($cid);if (!$row||(int)$row['status']!==1||(int)$row['ts_end']<=GameTime::now()) return $fallback;
        if (!(int)Db::value('SELECT enabled FROM event_quest_occurrences WHERE id=?',[(int)$row['occurrence_id']])) return $fallback;
        $p=self::payload($row);
        foreach(self::definition($row['identifier'])['objectives'] as $o) {
            if (!in_array((int)$o['type'],[self::TIME,self::FIGHT_WON,self::QUEST],true)||($o['type']===self::TIME&&$type!==1)||($o['type']===self::FIGHT_WON&&$type!==2)
                ||(int)$p['values'][$o['index']]>=(int)$o['value']||in_array($o['reference'],['','all'],true)) continue;
            $pool=array_keys(array_filter(self::reference()['quest_templates'],static fn($v,$k)=>self::matches($o['reference'],$k)&&str_contains($k,$type===1?'time':'fight'),ARRAY_FILTER_USE_BOTH));
            if ($pool) return $pool[array_rand($pool)];
        }
        return $fallback;
    }

    private static function refreshQuestOffers(int $cid): void
    {
        foreach(Db::rows('SELECT id,identifier,type,rewards,fight_npc_identifier FROM quests WHERE character_id=? AND status=1',[$cid]) as $q) {
            $identifier=self::themedQuest($cid,(int)$q['type'],$q['identifier']);
            $r=json_decode($q['rewards'],true)?:[];unset($r['event_item'],$r['event_quest_occurrence']);$r+=self::questReward($cid,$identifier);
            Db::exec('UPDATE quests SET identifier=?,rewards=?,fight_npc_identifier=? WHERE id=? AND character_id=? AND status=1',[$identifier,json_encode($r,JSON_THROW_ON_ERROR),(int)$q['type']===2 ? QuestNpcs::identifier($identifier,(string)$q['fight_npc_identifier']) : '',(int)$q['id'],$cid]);
        }
    }

    private static function deliver(array $char,array $r,bool $discard): void
    {
        $cid=(int)$char['id'];$id=(int)($r['item']??0);
        if ($id>0) {
            $item=Db::row('SELECT * FROM items WHERE id=? AND character_id=? FOR UPDATE',[$id,$cid])??throw new GameError('errInventoryInvalidItem');
            if ($discard) Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);
            elseif ((int)$item['type']===9) {
                Sidekick::create($cid,$item['identifier'],(int)$item['quality'],['stat_base_stamina'=>(int)$item['stat_stamina'],'stat_base_strength'=>(int)$item['stat_strength'],
                    'stat_base_critical_rating'=>(int)$item['stat_critical_rating'],'stat_base_dodge_rating'=>(int)$item['stat_dodge_rating']]);
                Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);
            } else {
                $inv=Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]);
                $limit=(int)$char['level']>=(int)Missiles::constants()['inventory_bag3_unlock_level']?18:((int)$char['level']>=(int)Missiles::constants()['inventory_bag2_unlock_level']?12:6);
                $slot=null;for($i=1;$i<=$limit;$i++)if($inv&&(int)$inv['bag_item'.$i.'_id']===0){$slot='bag_item'.$i.'_id';break;}
                if (!$slot) throw new GameError('errInventoryNoEmptySlot');
                Db::exec("UPDATE inventory SET `$slot`=? WHERE character_id=?",[$id,$cid]);
            }
        }
        Db::exec('UPDATE `character` SET game_currency=game_currency+?,xp=xp+?,stat_points_available=stat_points_available+? WHERE id=?',
            [(int)($r['coins']??0),(int)($r['xp']??0),(int)($r['statPoints']??0),$cid]);
        if (!empty($r['premium'])) Db::exec('UPDATE `user` SET premium_currency=premium_currency+? WHERE id=?',[(int)$r['premium'],(int)$char['user_id']]);
        foreach(UserVouchers::KEYS as $key) if (!empty($r[$key])) UserVouchers::create($cid,$key,(int)$r[$key]);
        if (!empty($r['optical_change'])) Db::exec('INSERT IGNORE INTO event_quest_unlocks (character_id,optical_change_id) VALUES (?,?)',[$cid,(int)$r['optical_change']]);
        if (!empty($r['booster'])) {
            $b=$r['booster'];$definition=Live::BOOSTER_CATALOG[$b['booster_id']];
            $key=[1=>'quest_booster',2=>'stats_booster',3=>'work_booster'][$definition[0]]??throw new \RuntimeException('Energético não suportado.');
            UserVouchers::create($cid,$key,['type'=>(int)$definition[1],'duration'=>(int)$b['days']]);
        }
        if (!empty($r['xp'])) Character::load($cid)->syncLevelFromXp();
    }

    public static function claimObjective(int $cid,string $identifier,bool $discard=false,bool $instant=false): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$identifier,$discard,$instant):void {
            $row=self::active($cid)??throw new GameError('errClaimEventQuestRewardsNoEventQuest');$p=self::payload($row);self::expire($row,$p);
            $o=self::definition($row['identifier'])['objectives'][$identifier]??throw new GameError('errRequestInvalidParameter');
            $idx=$o['index'];$v=(int)$p['values'][$idx];$target=(int)$o['value'];
            if ($instant) {
                if ((int)$row['status']!==1||$v>=$target) throw new GameError('errInvalidFunction');
                $cost=self::skipCost($v,$target);
                if (Db::exec('UPDATE `user` SET premium_currency=premium_currency-? WHERE id=? AND premium_currency>=?',[$cost,(int)$char['user_id'],$cost])!==1) throw new GameError('errRemovePremiumCurrencyNotEnough');
                $p['values'][$idx]=$target;
            } else {
                if ($v>$target) throw new GameError('errFinishObjectiveAlreadyCollected');
                if ($v!==$target||!in_array((int)$row['status'],[1,3,5],true)) throw new GameError('errInvalidFunction');
                $r=$p['objectives'][$identifier]??self::reward($char,(int)$o['reward_type'],(float)$o['reward_factor'],$o['reward_reference'],true);
                self::deliver($char,$r,$discard);$p['values'][$idx]=$target+1;
                // Localização de temporada 2: evento com objetivos, como no cliente.
                $progress=Season::ensureProgress($cid);$season=$progress['identifier']??Season::IDENTIFIER;
                $locations=Herobook::constants()['season_locations'];
                $points=(int)($locations[2][$season]??0);Season::givePoints($cid,$points);
                if (self::complete($row,$p,true)) $row['status']=(int)$row['ts_end']>GameTime::now()?2:5;
            }
            self::save($row,$p);
        });
    }

    public static function claimFinal(int $cid,int $selected,bool $discard=false): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$selected,$discard):void {
            $row=self::active($cid)??throw new GameError('errClaimEventQuestRewardsNoEventQuest');$p=self::payload($row);self::expire($row,$p);
            $won=self::complete($row,$p,true);
            if (!$won&&(int)$row['status']!==3) throw new GameError('errInvalidFunction');
            if ($won) {
                $choice=null;
                foreach($p['choices'] as $i=>$r) if ((int)($r['item']??-$i)===$selected) {$choice=$r;break;}
                if ($choice===null) throw new GameError('errInventoryInvalidItem');
                self::deliver($char,$choice,$discard);
                self::deliver(Db::row('SELECT * FROM `character` WHERE id=?',[$cid]),$p['base'],false);
            }
            // Limpar só previews que ainda não foram entregues.
            foreach(array_merge(array_values($p['objectives']),array_values($p['choices'])) as $r) self::deletePreview($cid,(int)($r['item']??0));
            $row['claimed']=1;$row['status']=$won?2:3;self::save($row,$p);
            Db::exec('UPDATE `character` SET event_quest_id=0 WHERE id=?',[$cid]);
        });
    }

    private static function deletePreview(int $cid,int $id): void
    {
        if ($id<1) return;
        $inv=Db::row('SELECT * FROM inventory WHERE character_id=?',[$cid])?:[];
        $bank=Db::row('SELECT * FROM bank_inventory WHERE character_id=?',[$cid])?:[];
        foreach(array_merge($inv,$bank) as $key=>$value) if (str_ends_with($key,'_id')&&!in_array($key,['id','character_id','sidekick_id'],true)&&(int)$value===$id) return;
        Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);
    }

    /** O parser do cliente usa o offset ATUAL do servidor, não UTC puro. */
    public static function serverDate(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s',$timestamp+Live::gameTimestampOffset());
    }

    private static function shape(array $row,array $p): array
    {
        $d=self::definition($row['identifier']);
        $d['id']=(int)$row['id'];$d['identifier']=$row['identifier'];$d['status']=(int)$row['status'];
        $d['start_date']=self::serverDate((int)$row['ts_creation']);$d['end_date']=self::serverDate((int)$row['ts_end']);
        $d['rewards']=json_encode($p['base'],JSON_THROW_ON_ERROR);
        foreach([1,2,3] as $i) {$r=$p['choices'][$i];$d['reward_item'.$i.'_id']=(int)($r['item']??-$i);$d['reward_item'.$i.'_rewards']=json_encode($r,JSON_THROW_ON_ERROR);}
        foreach($d['objectives'] as $key=>$o) {
            $d['objective'.$o['index'].'_value']=(int)$p['values'][$o['index']];
            $d['objective'.$o['index'].'_reward_item_id']=(int)($p['objectives'][$key]['item']??0);
        }
        $d['worldboss_start_times']=[];
        return $d;
    }

    /** Login/estado normal/parciais; sem convite antigo vindo de fixture. */
    public static function attach(array $data,int $cid): array
    {
        return StoryDungeon::transaction($cid,static function(array $char)use($data,$cid):array {
            unset($data['event_quest']);$row=self::active($cid);
            if ($row) {
                $p=self::payload($row);self::expire($row,$p);$data['event_quest']=self::shape($row,$p);$id=(int)$row['id'];
            } else {
                $id=0;$event=self::current();
                if ($event&&(int)$char['level']>=(int)self::reference()['event_quest_min_level']&&!Db::row('SELECT id FROM event_quest_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$event['id']])) {
                    $data['event_quest']=self::definition($event['identifier'])+['identifier'=>$event['identifier']];
                    $data['event_quest']['start_date']=self::serverDate((int)$event['ts_start']);$data['event_quest']['end_date']=self::serverDate((int)$event['ts_end']);
                }
            }
            if ((int)$char['event_quest_id']!==$id) Db::exec('UPDATE `character` SET event_quest_id=? WHERE id=?',[$id,$cid]);
            if (isset($data['character'])) $data['character']['event_quest_id']=$id;
            if ($row) $data['items']=Character::load($cid)->itemsData(Live::template('autoLoginUser')['items'][0]??[]);
            if (isset($data['current_optical_changes'])) {
                $unlocked=json_decode($data['current_optical_changes']['unlocked_options']??'{}',true)?:[];
                foreach(Db::rows('SELECT optical_change_id FROM event_quest_unlocks WHERE character_id=?',[$cid]) as $u) {
                    $oid=(int)$u['optical_change_id'];$type=(string)self::reference()['optical_changes'][$oid]['type'];
                    $unlocked[$type]=array_values(array_unique(array_merge($unlocked[$type]??[],[$oid])));
                }
                $data['current_optical_changes']['unlocked_options']=json_encode((object)$unlocked,JSON_THROW_ON_ERROR);
            }
            return $data;
        });
    }

    public static function hub(int $cid): array
    {
        $data=self::attach(['character'=>[]],$cid);$e=$data['event_quest']??null;
        return $e ? [['id'=>1000000000+(int)($e['id']??0),'identifier'=>$e['identifier'].'_eventQuest','data'=>'{}']] : [];
    }
}
