<?php
declare(strict_types=1);
namespace HeroZero;

/** Academia Heroica: ocorrências globais, estado individual e resgates atómicos. */
final class TowerEvents
{
    public static function reference(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        static $reference=null;
        return $reference ??= AssetCache::slice('tower_events_v1',$src,static function()use($src):array {
            $all=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
            return array_filter($all,static fn($k)=>str_starts_with(strtolower($k),'tower')||in_array($k,['item_templates','optical_changes']),ARRAY_FILTER_USE_KEY);
        });
    }
    public static function definition(string $id): array
    {
        return self::reference()['tower_events'][$id]??throw new GameError('errRequestInvalidParameter');
    }
    public static function installed(): bool
    {
        static $ready=null;
        return $ready??=Db::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tower_event_progress'")>0;
    }
    public static function schedule(string $id,int $start,int $end): int
    {
        self::definition($id);
        if($end<=$start||$end<=GameTime::now())throw new \InvalidArgumentException('Intervalo inválido.');
        return Missiles::transaction([],static function()use($id,$start,$end):int {
            Db::row('SELECT id FROM tower_event_control WHERE id=1 FOR UPDATE');
            if(Db::row('SELECT id FROM tower_event_occurrences WHERE enabled=1 AND ts_start<? AND ts_end>? LIMIT 1 FOR UPDATE',[$end,$start]))throw new \InvalidArgumentException('Já existe uma academia nesse intervalo.');
            Db::exec('INSERT INTO tower_event_occurrences(identifier,ts_start,ts_end) VALUES(?,?,?)',[$id,$start,$end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }
    public static function cancel(int $id): void
    {
        Missiles::transaction([],static function()use($id):void {
            Db::row('SELECT id FROM tower_event_control WHERE id=1 FOR UPDATE');
            if(!Db::row('SELECT id FROM tower_event_occurrences WHERE id=? FOR UPDATE',[$id]))throw new \InvalidArgumentException('Ocorrência inexistente.');
            Db::exec('UPDATE tower_event_occurrences SET enabled=0 WHERE id=?',[$id]);
        });
    }
    public static function current(): ?array
    {
        return Db::row('SELECT * FROM tower_event_occurrences WHERE enabled=1 AND ts_start<=? AND ts_end>? ORDER BY id LIMIT 1',[GameTime::now(),GameTime::now()]);
    }
    private static function pending(int $cid,bool $lock=false): ?array
    {
        return Db::row('SELECT * FROM tower_event_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1'.($lock?' FOR UPDATE':''),[$cid]);
    }
    private static function occurrence(array $row): array
    {
        return Db::row('SELECT * FROM tower_event_occurrences WHERE id=?',[(int)$row['occurrence_id']])??throw new GameError('errInvalidFunction');
    }
    private static function available(array $e): bool
    {
        return (int)$e['enabled']===1&&(int)$e['ts_start']<=GameTime::now()&&GameTime::now()<(int)$e['ts_end'];
    }
    public static function fresh(string $id,int $now): array
    {
        $d=self::definition($id);
        return ['started'=>$now,'last'=>$now,'bank'=>8,'idle'=>0,'idle_duration'=>0,'tokens'=>0,'round'=>1,
            'tower'=>TowerEconomy::fresh((int)$d['tower_id'],$now),'items'=>[],'next_item'=>2,'boosts'=>[],
            'claimed'=>[],'rewards'=>[],'active_tower_id'=>(int)$d['tower_id']];
    }
    private static function save(array $row,array $p): void
    {
        Db::exec('UPDATE tower_event_progress SET payload=? WHERE id=? AND character_id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)$row['id'],(int)$row['character_id']]);
    }
    public static function assign(int $cid,string $identifier): void
    {
        self::definition($identifier);
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$identifier):void {
            Db::row('SELECT id FROM tower_event_control WHERE id=1 FOR UPDATE');$e=self::current();
            if(!$e||$e['identifier']!==$identifier||(int)$char['level']<self::reference()['tower_event_min_level'])throw new GameError('errInvalidFunction');
            if(self::pending($cid,true)||Db::row('SELECT id FROM tower_event_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$e['id']]))throw new GameError('errInvalidFunction');
            Db::exec('INSERT INTO tower_event_progress(character_id,occurrence_id,identifier,payload) VALUES(?,?,?,?)',[$cid,(int)$e['id'],$identifier,json_encode(self::fresh($identifier,GameTime::now()),JSON_THROW_ON_ERROR)]);
        });
    }
    private static function objectives(string $id): array
    {
        return self::reference()['tower_event_objectives'][self::definition($id)['objectives']]['index'];
    }
    private static function debit(array $char,int $cost): void
    {
        if($cost<1)throw new GameError('errInvalidFunction');
        if(Db::exec('UPDATE `user` SET premium_currency=premium_currency-? WHERE id=? AND premium_currency>=?',[$cost,(int)$char['user_id'],$cost])!==1)throw new GameError('errRemovePremiumCurrencyNotEnough');
    }
    public static function addItem(array &$p,string $id,int $amount=1): ?array
    {
        $d=self::reference()['tower_items'][$id]??throw new GameError('errRequestInvalidParameter');
        if($amount<1||$amount>10000)throw new GameError('errRequestInvalidParameter');
        if((int)$d['type']===6){$p['tokens']+=$amount;return null;}
        $last=null;
        for($i=0;$i<$amount;$i++){$last=['id'=>$p['next_item']++,'item_id'=>$id,'status'=>1];$p['items'][]=$last;}
        return $last;
    }
    public static function inventory(array $p): array
    {
        return array_merge([['id'=>1,'item_id'=>'event_token_default','status'=>1,'amount'=>$p['tokens']]],$p['items']);
    }
    public static function eventData(string $id,array $e,?array $p): array
    {
        $objectives=[];
        foreach(self::objectives($id) as $i=>$d) {
            $o=['index'=>(int)$i,'ts_rewards_claimed'=>$p['claimed'][$i]??0];
            if(isset($p['rewards'][$i]))$o['rewards']=json_encode($p['rewards'][$i],JSON_THROW_ON_ERROR);
            $objectives[$i]=$o;
        }
        return ['id'=>$id,'ts_event_end'=>(int)$e['enabled']?(int)$e['ts_end']:min(GameTime::now(),(int)$e['ts_end']),
            'ts_user_start'=>$p['started']??0,'objectives'=>(object)$objectives,
            'event_data'=>['pending_idle_income_reward'=>json_encode(['amount'=>$p['idle']??0,'duration'=>$p['idle_duration']??0])],
            'tower_data'=>$p['tower']??null];
    }
    public static function clientState(string $id,array $e,?array $p): array
    {
        return ['occurrence_id'=>(int)$e['id'],'tutorial'=>['current_step'=>($p['tower']['tutorial_step']??1)>11?2:1],
            'towers'=>$p?[$p['tower']]:[],'active_tower_id'=>$p['active_tower_id']??0,
            'inventory'=>['tower_items'=>$p?self::inventory($p):[]],'active_boosts'=>$p['boosts']??[],
            'areas'=>[['id'=>1,'bank_amount'=>$p['bank']??8]],
            'events'=>['active_event_ids'=>[$id],'events_data'=>[$id=>self::eventData($id,$e,$p)]]];
    }
    public static function attach(array $data,int $cid): array
    {
        if(!self::installed()){unset($data['tower_event_data']);return $data;}
        $row=self::pending($cid);$p=null;$e=null;
        if($row) {
            [$row,$e,$p]=StoryDungeon::transaction($cid,static function()use($cid):array {
                $row=self::pending($cid,true);if(!$row)return [null,null,null];
                $e=self::occurrence($row);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
                TowerEconomy::accrue($p,min(GameTime::now(),(int)$e['ts_end']));self::save($row,$p);
                if(!self::available($e)) {
                    $pending=false;
                    foreach(self::objectives($row['identifier']) as $i=>$d)if($p['tokens']>=$d['required_token_amount']&&!isset($p['claimed'][$i])){$pending=true;break;}
                    if(!$pending){Db::exec('UPDATE tower_event_progress SET closed=1 WHERE id=?',[(int)$row['id']]);return [null,null,null];}
                }
                return [$row,$e,$p];
            });
        }
        if(!$e) {
            $candidate=self::current();
            $level=(int)($data['character']['level']??Db::value('SELECT level FROM `character` WHERE id=?',[$cid]));
            if($candidate&&$level>=self::reference()['tower_event_min_level']&&!Db::row('SELECT id FROM tower_event_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$candidate['id']]))$e=$candidate;
        }
        $data['tower_event_data']=$e?self::clientState($e['identifier'],$e,$p):[
            'tutorial'=>['current_step'=>1],'towers'=>[],'active_tower_id'=>0,'inventory'=>['tower_items'=>[]],
            'areas'=>[['id'=>1,'bank_amount'=>0]],'active_boosts'=>[],'events'=>['active_event_ids'=>[],'events_data'=>(object)[]]];
        if(isset($data['current_optical_changes'])) {
            $unlocked=json_decode($data['current_optical_changes']['unlocked_options']??'{}',true)?:[];
            foreach(Db::column('SELECT optical_change_id FROM tower_event_optical_unlocks WHERE character_id=?',[$cid]) as $id) {
                $type=self::reference()['optical_changes'][$id]['type'];$unlocked[$type]=array_values(array_unique(array_merge($unlocked[$type]??[],[(int)$id])));
            }
            $data['current_optical_changes']['unlocked_options']=json_encode((object)$unlocked);
        }
        return $data;
    }
    public static function hub(int $cid): array
    {
        if(!self::installed())return [];$e=self::current();
        if(!$e||(int)Db::value('SELECT level FROM `character` WHERE id=?',[$cid])<5)return [];
        return [['id'=>1300000000+(int)$e['id'],'identifier'=>$e['identifier'].'_tower','data'=>'{}']];
    }
    /** Ações financeiras e prémios, incluindo atualizações de managers, partilham um lock. */
    public static function action(int $cid,array $a): array
    {
        $action=(string)($a['action']??'');
        if($action==='startTowerEvent')self::assign($cid,(string)($a['event_id']??''));
        return StoryDungeon::transaction($cid,static function(array $char)use($cid,$a,$action):array {
            $row=self::pending($cid,true)??throw new GameError('errInvalidFunction');$e=self::occurrence($row);
            $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$id=$row['identifier'];$now=GameTime::now();
            if(isset($a['event_id'])&&$a['event_id']!==$id)throw new GameError('errRequestInvalidParameter');
            if(isset($a['tower_id'])&&(int)$a['tower_id']!==$p['tower']['id'])throw new GameError('errRequestInvalidParameter');
            if($action!=='collectTowerEventObjectiveRewards'&&!self::available($e))throw new GameError('errInvalidFunction');
            TowerEconomy::accrue($p,min($now,(int)$e['ts_end']));$out=[];
            switch($action) {
                case 'startTowerEvent':break;
                case 'syncTowerEvent':
                    // Não importar níveis, dinheiro, managers, boosts ou timestamps do cliente.
                    $raw=$a['tower_data']??'{}';if(!is_string($raw)||strlen($raw)>200000)throw new GameError('errRequestInvalidParameter');
                    $snapshot=json_decode($raw,true);if(!is_array($snapshot))throw new GameError('errRequestInvalidParameter');
                    $step=TowerEconomy::number($snapshot['tutorial_step']??1,1,100);
                    $p['tower']['tutorial_step']=max($p['tower']['tutorial_step'],$step);break;
                case 'towerCommand':$out=TowerEconomy::command($p,$a,$now);break;
                case 'enterTower':
                    if((int)($a['new_tower_id']??0)!==$p['tower']['id'])throw new GameError('errRequestInvalidParameter');
                    $p['active_tower_id']=$p['tower']['id'];$out['active_tower_id']=$p['active_tower_id'];break;
                case 'unlockTowerFloor':
                    $index=TowerEconomy::number($a['floor_index']??0,1,30);
                    // Compra premium usa o custo oficial; o caminho normal é towerCommand.
                    self::debit($char,(int)self::reference()['tower_event_floor_levels'][$index]['premiumUnlockCost']);
                    $bank=$p['bank'];$p['bank']=PHP_FLOAT_MAX;
                    TowerEconomy::command($p,['command'=>'unlock','kind'=>'floor','index'=>$index],$now);$p['bank']=$bank;break;
                case 'collectTowerIdleReward':
                    $out['reward_amount']=$p['idle'];$p['bank']+=$p['idle'];$p['idle']=0;$p['idle_duration']=0;break;
                case 'collectTowerObjectiveReward':
                    $key=(string)($a['objective_key']??'');$def=self::reference()['tower_event_tower_objectives'][self::definition($id)['tower_objectives']]['index'][$key]??throw new GameError('errRequestInvalidParameter');
                    $oid=$def['objective'];
                    if(($a['objective_id']??'')!==$oid||isset($p['tower']['objectives'][$oid])||!TowerEconomy::objectiveReached($p['tower'],self::reference()['tower_objective_tasks'][$oid]))throw new GameError('errInvalidFunction');
                    foreach($def['reward_items'] as $i=>$item)self::addItem($p,$item,(int)$def['reward_amount'][$i]);
                    $p['tower']['objectives'][$oid]=$now;break;
                case 'skipTowerEventObjective':
                    $i=TowerEconomy::number($a['objective_index']??0,1,1000);$d=self::objectives($id)[$i]??throw new GameError('errRequestInvalidParameter');
                    if($p['tokens']>=$d['required_token_amount']||!$d['skip_cost'])throw new GameError('errInvalidFunction');
                    self::debit($char,(int)$d['skip_cost']);$p['tokens']=(int)$d['required_token_amount'];break;
                case 'collectTowerEventObjectiveRewards':
                    $indices=json_decode((string)($a['objective_indices']??'[]'),true);
                    if(!is_array($indices)||!array_is_list($indices)||!count($indices)||count($indices)>100||count(array_unique($indices,SORT_REGULAR))!==count($indices))throw new GameError('errRequestInvalidParameter');
                    foreach($indices as $i) {
                        $i=TowerEconomy::number($i,1,1000);$d=self::objectives($id)[$i]??throw new GameError('errRequestInvalidParameter');
                        if(isset($p['claimed'][$i])||$p['tokens']<$d['required_token_amount'])throw new GameError('errInvalidFunction');
                        $p['rewards'][$i]=self::reward($char,$p,$d,filter_var($a['discard_item']??false,FILTER_VALIDATE_BOOLEAN));$p['claimed'][$i]=$now;
                    }break;
                case 'resetTowerEventProgress':
                    foreach(self::objectives($id) as $i=>$d)if(!isset($p['claimed'][$i]))throw new GameError('errInvalidFunction');
                    self::debit($char,(int)self::reference()['TowerEventResetProgressPremiumCost']);
                    $old=$p;$p=self::fresh($id,$now);$p['round']=$old['round']+1;$p['items']=$old['items'];$p['next_item']=$old['next_item'];break;
                case 'activateAllTowerManagers':
                    $activated=0;
                    foreach(['floor','elevator','vault'] as $kind) {
                        $targets=$kind==='floor'?$p['tower']['floors']:[$p['tower'][$kind]];
                        foreach($targets as $target)if($target['manager_id']) {
                            try {TowerEconomy::command($p,['command'=>'activate','kind'=>$kind,'index'=>$target['index']??0,'manager_id'=>$target['manager_id']],$now);$activated++;}
                            catch(GameError $err){if($err->getMessage()!=='errInvalidFunction')throw $err;}
                        }
                    }
                    if(!$activated)throw new GameError('errInvalidFunction');
                    self::debit($char,(int)self::reference()['TowerActivateAllManagerPremiumCurrencyCost']);break;
                case 'buyTowerBoosterGachaPackage':
                    $d=self::reference()['tower_booster_gacha_packages'][$a['booster_gacha_package_id']??'']??throw new GameError('errRequestInvalidParameter');
                    self::debit($char,(int)$d['premium_currency']);$roll=random_int(1,array_sum(array_column($d['items'],'relative_chance')));
                    foreach($d['items'] as $item){$roll-=$item['relative_chance'];if($roll<=0){$out['tower_item']=self::addItem($p,$item['booster']);break;}}break;
                case 'buyTowerTimeSkipItem':case 'buyTowerInstantIncomeItem':
                    $item=(string)($a[$action==='buyTowerTimeSkipItem'?'timeskip_item_id':'instant_income_item_id']??'');
                    $d=self::reference()['tower_items'][$item]??throw new GameError('errRequestInvalidParameter');
                    if(!$d['is_buyable']||(int)$d['type']!==($action==='buyTowerTimeSkipItem'?5:2))throw new GameError('errInvalidFunction');
                    self::debit($char,(int)$d['premium_currency_cost']);$out['tower_item']=self::addItem($p,$item);break;
                case 'useTowerInventoryItem':$out=self::useItem($p,$a,$now);break;
                default:throw new GameError('errRequestInvalidParameter');
            }
            self::save($row,$p);
            return $out+['event'=>self::eventData($id,$e,$p),'tower_items'=>self::inventory($p),
                'active_boosts'=>$p['boosts'],'tower_authoritative'=>['tower'=>$p['tower'],'bank'=>$p['bank'],'boosts'=>$p['boosts'],'round'=>$p['round']]];
        });
    }
    private static function useItem(array &$p,array $a,int $now): array
    {
        $id=TowerEconomy::number($a['item_id']??0,2,PHP_INT_MAX);$pos=null;
        foreach($p['items'] as $i=>$item)if($item['id']===$id){$pos=$i;break;}
        if($pos===null||$p['items'][$pos]['status']!==1)throw new GameError('errInvalidFunction');
        $d=self::reference()['tower_items'][$p['items'][$pos]['item_id']];$out=[];
        $amount=TowerEconomy::number($a['amount']??1,1,10000);$consume=[$pos];
        if($amount>1) {
            if((int)$d['type']!==1)throw new GameError('errRequestInvalidParameter');
            foreach($p['items'] as $i=>$item)if($i!==$pos&&$item['status']===1&&$item['item_id']===$p['items'][$pos]['item_id']&&count($consume)<$amount)$consume[]=$i;
            if(count($consume)!==$amount)throw new GameError('errUseItemNotEnough');
        }

        if($d['type']===1) {
            $existing=null;foreach($p['boosts'] as $i=>$b)if($b['id']===$d['booster']){$existing=$i;break;}
            if($existing!==null)$p['boosts'][$existing]['ts_end']=max($now,$p['boosts'][$existing]['ts_end'])+(int)$d['booster_duration']*$amount;
            else $p['boosts'][]=['id'=>$d['booster'],'type'=>1,'target_type'=>1,'target_id'=>0,'ts_start'=>$now,'ts_end'=>$now+(int)$d['booster_duration']*$amount];
        }elseif($d['type']===2) {
            $amount=TowerEconomy::rate($p,$now,true,false)*(int)$d['instant_income_time'];$p['bank']+=$amount;$out['instant_income_amount']=$amount;
        }elseif($d['type']===5) {
            if((int)($a['type']??0)!==1||!preg_match('/^(\d+)_(\d+)$/D',(string)($a['reference_id']??''),$m)||(int)$m[1]!==$p['tower']['id'])throw new GameError('errRequestInvalidParameter');
            $region=TowerEconomy::number($m[2],1,5);$r=&$p['tower']['regions'][$region];
            if(!$r['ts_unlock_started']||$r['ts_unlocked']||$r['ts_unlock_end']<=$now)throw new GameError('errInvalidFunction');
            $seconds=(int)$d['timeskip_duration'];$r['ts_unlock_started']-=$seconds;$r['ts_unlock_end']=max($now,$r['ts_unlock_end']-$seconds);unset($r);
        }else throw new GameError('errInvalidFunction');
        foreach($consume as $i)$p['items'][$i]['status']=2;return $out;
    }
    private static function reward(array $char,array &$p,array $d,bool $discard): array
    {
        $cid=(int)$char['id'];$type=(int)$d['reward_type'];$amount=(int)$d['reward_amount'];$info=json_decode($d['reward_data']??'{}',true)?:[];
        if(in_array($type,[46,50],true)){self::addItem($p,$info['identifier'],$amount);return [];}
        if($type===14) {
            $id=(int)$info['optical_change_id'];if(!isset(self::reference()['optical_changes'][$id]))throw new GameError('errInvalidFunction');
            Db::exec('INSERT IGNORE INTO tower_event_optical_unlocks(character_id,optical_change_id) VALUES(?,?)',[$cid,$id]);return ['optical_change'=>$id];
        }
        if(in_array($type,[6,16],true)) {
            if($discard)return [];
            $ref=$info['identifier'];$hero=Character::load($cid);
            if($type===6) {
                $tpl=self::reference()['item_templates'][$ref]??throw new GameError('errInvalidFunction');$itemType=(int)$tpl['type'];
                $id=$hero->createTowerRewardItem($ref,$itemType,(int)$tpl['quality']);
            }else {
                if(!preg_match('/^improvement_(mask|cape|suit|belt|boots|weapon|gadget|sidekick)_(common|rare|epic|legendary)$/D',$ref,$m))throw new GameError('errInvalidFunction');
                $slot=array_search($m[1],[1=>'mask',2=>'cape',3=>'suit',4=>'belt',5=>'boots',6=>'weapon',7=>'gadget',9=>'sidekick'],true);
                $quality=array_search($m[2],[1=>'common',2=>'rare',3=>'epic',4=>'legendary'],true);
                $budget=max(4,(int)ceil($char['level']*.8));$stats=array_fill_keys(['stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating'],intdiv($budget,4));$stats['stat_stamina']+=$budget%4;
                $id=$hero->createImprovementItem($slot,$quality,$stats);$itemType=12;
            }
            if($itemType===9) {
                $item=Db::row('SELECT * FROM items WHERE id=? AND character_id=?',[$id,$cid]);
                Sidekick::create($cid,$ref,(int)$item['quality'],['stat_base_stamina'=>(int)$item['stat_stamina'],'stat_base_strength'=>(int)$item['stat_strength'],'stat_base_critical_rating'=>(int)$item['stat_critical_rating'],'stat_base_dodge_rating'=>(int)$item['stat_dodge_rating']]);
                Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$id,$cid]);return [];
            }
            $inv=Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]);$c=Missiles::constants();
            $limit=$char['level']>=$c['inventory_bag3_unlock_level']?18:($char['level']>=$c['inventory_bag2_unlock_level']?12:6);$slot=null;
            for($i=1;$i<=$limit;$i++)if($inv&&(int)$inv['bag_item'.$i.'_id']===0){$slot='bag_item'.$i.'_id';break;}
            if(!$slot)throw new GameError('errInventoryNoEmptySlot');
            Db::exec("UPDATE inventory SET `$slot`=? WHERE character_id=?",[$id,$cid]);return ['item'=>$id];
        }
        if(!in_array($type,[0,1,2,3,4,5],true))throw new GameError('errInvalidFunction');
        // Vale negativo dos assets significa cálculo dinâmico; política local partilhada com tesouros.
        $r=EventTreasures::bonus($type,$amount<0?(float)$d['reward_factor']:$amount,(int)$char['level']);
        if($amount>=0)$r=[match($type){0=>'premium',1=>'coins',2=>'xp',3=>'quest_energy',4=>'training_sessions',5=>'statPoints'}=>$amount];
        Db::exec('UPDATE `character` SET game_currency=game_currency+?,xp=xp+?,stat_points_available=stat_points_available+? WHERE id=?',[(int)($r['coins']??0),(int)($r['xp']??0),(int)($r['statPoints']??0),$cid]);
        if(!empty($r['premium']))Db::exec('UPDATE `user` SET premium_currency=premium_currency+? WHERE id=?',[$r['premium'],(int)$char['user_id']]);
        foreach(UserVouchers::KEYS as $key)if(!empty($r[$key]))UserVouchers::create($cid,$key,(int)$r[$key]);
        if(!empty($r['xp']))Character::load($cid)->syncLevelFromXp();return $r;
    }
}
