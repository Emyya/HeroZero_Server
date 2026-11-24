<?php
declare(strict_types=1);
namespace HeroZero;

/** Pirâmide da Sorte: custos e pesos do cliente, entrega local documentada. */
final class EventDraws
{
    public static function reference(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('event_draws_v1',$src,static function()use($src):array {
            $c=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
            return array_intersect_key($c,array_flip(['event_draw_pools','draw_quest_min_level','item_templates']));
        });
    }
    public static function definition(string $id): array
    {
        return self::reference()['event_draw_pools'][$id]??throw new GameError('errRequestInvalidParameter');
    }
    public static function schedule(string $id,int $start,int $end): int
    {
        self::definition($id);
        if($end<=$start||$end<=GameTime::now())throw new \InvalidArgumentException('Intervalo inválido ou terminado.');
        return Missiles::transaction([],static function()use($id,$start,$end):int {
            Db::row('SELECT id FROM event_draw_control WHERE id=1 FOR UPDATE');
            if(Db::row('SELECT id FROM event_draw_occurrences WHERE enabled=1 AND ts_start<? AND ts_end>? LIMIT 1 FOR UPDATE',[$end,$start]))
                throw new \InvalidArgumentException('Já existe uma Pirâmide da Sorte nesse intervalo.');
            Db::exec('INSERT INTO event_draw_occurrences (identifier,ts_start,ts_end) VALUES (?,?,?)',[$id,$start,$end]);
            return (int)Db::pdo()->lastInsertId();
        });
    }
    public static function cancel(int $id): void
    {
        Missiles::transaction([],static function()use($id):void {
            Db::row('SELECT id FROM event_draw_control WHERE id=1 FOR UPDATE');
            if(!Db::row('SELECT id FROM event_draw_occurrences WHERE id=? FOR UPDATE',[$id]))throw new \InvalidArgumentException('Ocorrência inexistente.');
            Db::exec('UPDATE event_draw_occurrences SET enabled=0 WHERE id=?',[$id]);
        });
    }
    public static function current(): ?array
    {
        return Db::row('SELECT * FROM event_draw_occurrences WHERE enabled=1 AND ts_start<=? AND ts_end>? ORDER BY id LIMIT 1',[GameTime::now(),GameTime::now()]);
    }
    public static function state(int $cid): array
    {
        $row=Db::row('SELECT * FROM event_draw_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1',[$cid]);
        return $row?json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR)+['id'=>(int)$row['id'],'identifier'=>$row['identifier'],'status'=>(int)$row['status']]:['id'=>0,'identifier'=>''];
    }
    private static function active(int $cid,string $error='errInvalidFunction'): array
    {
        return Db::row('SELECT * FROM event_draw_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid])??throw new GameError($error);
    }
    private static function occurrence(array $row): array
    {
        return Db::row('SELECT * FROM event_draw_occurrences WHERE id=?',[(int)$row['occurrence_id']])??throw new GameError('errInvalidFunction');
    }
    private static function available(array $row): bool
    {
        $e=self::occurrence($row);
        return (int)$e['enabled']===1&&(int)$e['ts_start']<=GameTime::now()&&(int)$e['ts_end']>GameTime::now();
    }
    private static function pending(array $p): bool
    {
        foreach($p['drawn'] as $i=>$ts)if(!isset($p['claimed'][$i]))return true;
        return false;
    }
    private static function save(array $row,array $p): void
    {
        Db::exec('UPDATE event_draw_progress SET payload=?,status=?,closed=? WHERE id=? AND character_id=?',
            [json_encode($p,JSON_THROW_ON_ERROR),(int)$row['status'],(int)$row['closed'],(int)$row['id'],(int)$row['character_id']]);
    }
    private static function settle(array &$row,array $p): void
    {
        if(!self::available($row)) {
            $row['status']=self::pending($p)?3:4;$row['closed']=self::pending($p)?0:1;
        } elseif(count($p['drawn'])===count(self::definition($row['identifier'])['reward_index'])&&!self::pending($p)) {
            $row['status']=2;$row['closed']=1;
        }
    }
    /** Mesmas probabilidades mostradas por DrawEvent.refresh, sem prémios repetidos. */
    public static function chances(string $id,array $drawn): array
    {
        $weights=[];$total=0;
        foreach(self::definition($id)['reward_index'] as $i=>$r) {$w=isset($drawn[$i])?0:(int)$r['chance'];$weights[$i]=$w;$total+=$w;}
        return array_map(static fn($w)=>$total>0?$w/$total:0.0,$weights);
    }
    public static function cost(string $id,array $drawn): int
    {
        $r=self::definition($id)['reward_index'];$n=count($drawn);
        return $n<count($r)?(int)$r[$n]['cost_premium_currency']:0;
    }
    public static function choose(string $id,array $drawn): int
    {
        $weights=[];$total=0;
        foreach(self::definition($id)['reward_index'] as $i=>$r)if(!isset($drawn[$i])){$weights[$i]=(int)$r['chance'];$total+=(int)$r['chance'];}
        if($total<=0)throw new GameError('errInvalidFunction');
        $roll=random_int(1,$total);
        foreach($weights as $i=>$w){$roll-=$w;if($roll<=0)return $i;}
        throw new \LogicException('Sorteio inválido.');
    }
    public static function assign(int $cid,string $id): void
    {
        self::definition($id);
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$id):void {
            Db::row('SELECT id FROM event_draw_control WHERE id=1 FOR UPDATE');$e=self::current();
            if(!$e||$e['identifier']!==$id||(int)$char['level']<(int)self::reference()['draw_quest_min_level'])throw new GameError('errRequestInvalidParameter');
            if(Db::row('SELECT id FROM event_draw_progress WHERE character_id=? AND (closed=0 OR occurrence_id=?) LIMIT 1 FOR UPDATE',[$cid,(int)$e['id']]))throw new GameError('errAssignDrawEventAlreadyAssigned');
            Db::exec('INSERT INTO event_draw_progress (character_id,occurrence_id,identifier,payload) VALUES (?,?,?,?)',
                [$cid,(int)$e['id'],$id,json_encode(['drawn'=>[],'claimed'=>[],'rewards'=>[]],JSON_THROW_ON_ERROR)]);
        });
    }
    /** Preview preparado no sorteio; fator 20 não é interpretado como atributos oficiais. */
    private static function prize(array $char,array $definition): array
    {
        $cid=(int)$char['id'];$type=(int)$definition['reward_type'];$ref=$definition['reward_reference'];
        if($type===3)return ['statPoints'=>(int)round($definition['reward_factor'])];
        if($type===5) {
            $tpl=self::reference()['item_templates'][$ref]??throw new \RuntimeException('Template de prémio inexistente.');
            $id=Character::load($cid)->createEventRewardItem($ref,(int)$tpl['type'],(int)$tpl['quality']);
            if((int)$tpl['type']===8)Db::exec('UPDATE items SET charges=? WHERE id=?',[Missiles::PACKAGE_CHARGES,$id]);
        } elseif($type===12) {
            if(!preg_match('/^improvement_(mask|cape|suit|belt|boots|weapon|gadget|sidekick)_(common|rare|epic|legendary)$/D',$ref,$m))throw new \RuntimeException('Modificação inválida.');
            $slot=array_search($m[1],[1=>'mask',2=>'cape',3=>'suit',4=>'belt',5=>'boots',6=>'weapon',7=>'gadget',9=>'sidekick'],true);
            $quality=array_search($m[2],[1=>'common',2=>'rare',3=>'epic',4=>'legendary'],true);
            // Orçamento local alinhado com tesouros/casino; template define slot e qualidade.
            $budget=max(4,(int)ceil((int)$char['level']*.8));$stats=array_fill_keys(['stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating'],intdiv($budget,4));$stats['stat_stamina']+=$budget%4;
            $id=Character::load($cid)->createImprovementItem($slot,$quality,$stats);
        } else throw new \RuntimeException('Tipo de prémio não suportado.');
        return ['item'=>$id];
    }
    public static function draw(int $cid): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid):void {
            $row=self::active($cid);$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if(!self::available($row)||self::pending($p))throw new GameError('errInvalidFunction');
            $index=self::choose($row['identifier'],$p['drawn']);$cost=self::cost($row['identifier'],$p['drawn']);
            if($cost>0&&Db::exec('UPDATE `user` SET premium_currency=premium_currency-? WHERE id=? AND premium_currency>=?',[$cost,(int)$char['user_id'],$cost])!==1)
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            $p['rewards'][$index]=self::prize($char,self::definition($row['identifier'])['reward_index'][$index]);
            $p['drawn'][$index]=max(1,GameTime::now());self::save($row,$p);
        });
    }
    private static function deliver(array $char,array $r,bool $discard): void
    {
        $cid=(int)$char['id'];$id=(int)($r['item']??0);
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
        if(!empty($r['statPoints']))Db::exec('UPDATE `character` SET stat_points_available=stat_points_available+? WHERE id=?',[(int)$r['statPoints'],$cid]);
    }
    public static function claim(int $cid,int $index,bool $discard=false): void
    {
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$index,$discard):void {
            $row=self::active($cid,'errClaimDrawEventRewardInvalidReward');$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
            if(!isset($p['drawn'][$index])||isset($p['claimed'][$index])||!isset($p['rewards'][$index]))throw new GameError('errClaimDrawEventRewardInvalidReward');
            self::deliver($char,$p['rewards'][$index],$discard);$p['claimed'][$index]=true;self::settle($row,$p);self::save($row,$p);
        });
    }
    private static function shape(array $row,array $p): array
    {
        $e=self::occurrence($row);$available=self::available($row);
        $out=['id'=>(int)$row['id'],'identifier'=>$row['identifier'],'status'=>(int)$row['status'],
            'start_date'=>EventQuests::serverDate((int)$e['ts_start']),
            'end_date'=>EventQuests::serverDate($available?(int)$e['ts_end']:min((int)$e['ts_end'],GameTime::now()))];
        foreach(self::definition($row['identifier'])['reward_index'] as $i=>$r) {
            $out['ts_reward'.$i.'_drawn']=(int)($p['drawn'][$i]??0);$out['reward'.$i.'_collected']=isset($p['claimed'][$i]);
            $out['reward'.$i]=json_encode($p['rewards'][$i]??(object)[],JSON_THROW_ON_ERROR);
        }
        return $out;
    }
    public static function attach(array $data,int $cid): array
    {
        return StoryDungeon::transaction($cid,static function(array $char)use($data,$cid):array {
            unset($data['draw_event']);$id=0;
            $row=Db::row('SELECT * FROM event_draw_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid]);
            if($row) {
                $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);self::settle($row,$p);self::save($row,$p);
                $data['draw_event']=self::shape($row,$p);if(!(int)$row['closed'])$id=(int)$row['id'];
                // Previews devem estar disponíveis antes de os diálogos resolverem IDs.
                $data['items']=Character::load($cid)->itemsData(Live::template('autoLoginUser')['items'][0]??[]);
            } else {
                $e=self::current();
                if($e&&(int)$char['level']>=(int)self::reference()['draw_quest_min_level']&&!Db::row('SELECT id FROM event_draw_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$e['id']]))
                    $data['draw_event']=['identifier'=>$e['identifier'],'start_date'=>EventQuests::serverDate((int)$e['ts_start']),'end_date'=>EventQuests::serverDate((int)$e['ts_end'])];
                else {
                    // Último estado fechado permite atualizar/fechar um diálogo já aberto no cliente.
                    $last=Db::row('SELECT * FROM event_draw_progress WHERE character_id=? AND closed=1 ORDER BY id DESC LIMIT 1',[$cid]);
                    if($last)$data['draw_event']=self::shape($last,json_decode($last['payload'],true,512,JSON_THROW_ON_ERROR));
                }
            }
            if(isset($data['character']))$data['character']['draw_event_id']=$id;
            return $data;
        });
    }
    public static function hub(int $cid): array
    {
        $data=self::attach(['character'=>[]],$cid);$e=$data['draw_event']??null;
        if(!$e||(isset($e['id'])&&!$data['character']['draw_event_id']))return [];
        return [['id'=>1200000000+(int)($e['id']??0),'identifier'=>$e['identifier'].'_draw','data'=>'{}']];
    }
}
