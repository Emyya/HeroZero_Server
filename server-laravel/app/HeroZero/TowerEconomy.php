<?php
declare(strict_types=1);
namespace HeroZero;

/** Economia da academia. Estado e sorteios pertencem ao servidor. */
final class TowerEconomy
{
    public static function fresh(int $towerId, int $now): array
    {
        $pool=['manager_buy_index'=>1,'managers'=>[]];
        $floors=[];
        for($i=1;$i<=30;$i++)$floors[]=['index'=>$i,'level'=>0,'manager_id'=>0,'bank_amount'=>0,'ts_unlocked'=>0];
        $regions=[];
        foreach(TowerEvents::reference()['tower_event_regions'] as $i=>$r)
            $regions[]=['index'=>$i,'ts_unlock_started'=>$i===0?$now:0,'ts_unlock_end'=>$i===0?-1:0,'ts_unlocked'=>$i===0?$now:0];
        return ['id'=>$towerId,'vault'=>['level'=>1,'manager_id'=>0,'manager_pool'=>$pool],
            'elevator'=>['level'=>1,'manager_id'=>0,'bank_amount'=>0,'manager_pool'=>$pool],
            'floors'=>$floors,'regions'=>$regions,'manager_pool'=>$pool,'objectives'=>[],
            'tutorial_step'=>1,'max_floor_index'=>0];
    }
    public static function number(mixed $v, int $min, int $max): int
    {
        if(filter_var($v,FILTER_VALIDATE_INT)===false || (int)$v<$min || (int)$v>$max)throw new GameError('errRequestInvalidParameter');
        return (int)$v;
    }
    public static function spend(array &$p,float $cost): void
    {
        if(!is_finite($cost)||$cost<0||$p['bank']+max(1e-7,abs($cost)*1e-12)<$cost)throw new GameError('errNotEnoughGameCurrency');
        $p['bank']=max(0,$p['bank']-$cost);
    }
    private static function &target(array &$tower,string $kind,int $index): array
    {
        if($kind==='floor') {
            self::number($index,1,30);
            return $tower['floors'][$index-1];
        }
        if(!in_array($kind,['vault','elevator'],true))throw new GameError('errRequestInvalidParameter');
        return $tower[$kind];
    }
    private static function &pool(array &$tower,string $kind): array
    {
        if($kind==='floor')return $tower['manager_pool'];
        if(!in_array($kind,['vault','elevator'],true))throw new GameError('errRequestInvalidParameter');
        return $tower[$kind]['manager_pool'];
    }
    public static function effect(array $tower,string $kind,int $index,int $now): ?array
    {
        $target=self::target($tower,$kind,$index);$pool=self::pool($tower,$kind);
        foreach($pool['managers'] as $m)if($m['id']===$target['manager_id']) {
            $def=TowerEvents::reference()['tower_manager_effects'][$m['effect_id']];$r=$def['rarities'][$m['rarity']];
            if($m['ts_last_used']>0&&$now<$m['ts_last_used']+$r['active_time'])return ['type'=>$def['type'],'value'=>$r['effect_value']];
        }
        return null;
    }
    /** Fórmulas de rendimento por ciclo recuperadas dos calculadores do cliente. */
    public static function rate(array $p,int $now,bool $managedOnly=false,bool $effects=true): float
    {
        $flow=self::flows($p,$now,$managedOnly,$effects);
        return min(array_sum($flow['floors']),$flow['elevator'],$flow['vault']);
    }
    /** Capacidades por segundo, antes de limitar pelo estrangulamento global. */
    public static function flows(array $p,int $now,bool $managedOnly=false,bool $effects=true): array
    {
        $c=TowerEvents::reference();$t=$p['tower'];$floors=[];$count=0;
        $boost=0;
        foreach($p['boosts'] as $b)if($b['ts_start']<=$now&&$now<$b['ts_end'])$boost+=$c['tower_boosters'][$b['id']]['factor'];
        $boost=max(1,$boost);
        foreach($t['floors'] as $f) {
            if(!$f['level'])continue;$count=$f['index'];
            if($managedOnly&&!$f['manager_id'])continue;
            $v=$c['tower_event_floor_levels'][$f['index']]['levels'][$f['level']];
            $e=$effects?self::effect($t,'floor',$f['index'],$now):null;
            $speed=$v['worker_speed']*(($e['type']??0)===3?$e['value']:1);
            $income=$v['worker_income']*(($e['type']??0)===2?$e['value']:1)*$boost;
            $cap=$v['worker_capacity']*$boost;
            $floors[$f['index']]=$v['worker_count']*$cap/(2*(6+24*($f['level']-1)/1000)/$speed+$cap/$income);
        }
        if(!$count)return ['floors'=>[],'elevator'=>0.0,'vault'=>0.0];
        $v=$c['tower_event_elevator_levels'][$t['elevator']['level']];$e=$effects?self::effect($t,'elevator',0,$now):null;
        $speed=$v['speed']*(($e['type']??0)===4?$e['value']:1);
        $cap=$v['capacity']*(($e['type']??0)===7?$e['value']:1)*$boost;
        $load=$v['load_income']*(($e['type']??0)===5?$e['value']:1)*$boost;
        $elevator=$cap/(2*(($count+1)/$speed)+2*$cap/$load);
        $v=$c['tower_event_vault_levels'][$t['vault']['level']];$e=$effects?self::effect($t,'vault',0,$now):null;
        $speed=$v['worker_speed']*(($e['type']??0)===9?$e['value']:1);
        $cap=$v['worker_capacity']*(($e['type']??0)===11?$e['value']:1)*$boost;
        $load=$v['load_income']*(($e['type']??0)===8?$e['value']:1)*$boost;
        $vault=$v['worker_count']*$cap/(60/$speed+2*$cap/$load);
        return ['floors'=>$floors,'elevator'=>$managedOnly&&!$t['elevator']['manager_id']?0.0:$elevator,
            'vault'=>$managedOnly&&!$t['vault']['manager_id']?0.0:$vault];
    }
    /** Produzir -> transportar -> entregar; só a entrega entra no saldo disponível. */
    public static function advanceProduction(array &$p,int $now,float $seconds): void
    {
        if($seconds<=0)return;
        $flow=self::flows($p,$now);
        // Passos de até um segundo: uma moeda não atravessa todas as etapas no mesmo passo.
        while($seconds>0) {
            $step=min(1.0,$seconds);$seconds-=$step;
            $delivered=min($p['tower']['elevator']['bank_amount'],$flow['vault']*$step);
            $p['tower']['elevator']['bank_amount']-=$delivered;$p['bank']+=$delivered;
            $transport=$flow['elevator']*$step;
            foreach($p['tower']['floors'] as &$floor) {
                $move=min($floor['bank_amount'],$transport);
                $floor['bank_amount']-=$move;$transport-=$move;
                $p['tower']['elevator']['bank_amount']+=$move;
                $floor['bank_amount']+=(($flow['floors'][$floor['index']]??0)*$step);
            }
            unset($floor);
        }
    }
    /** Integra expiracões: um booster de 1h nunca rende por todo um intervalo offline. */
    public static function accrue(array &$p,int $now): void
    {
        // O cliente exige início não nulo e fim -1 para considerar a região aberta.
        // Normalizar participações existentes mesmo sem tempo de rendimento decorrido.
        foreach($p['tower']['regions'] as &$region) {
            if((int)$region['index']===0&&(int)$region['ts_unlocked']>0&&(int)$region['ts_unlock_started']===0&&(int)$region['ts_unlock_end']===-1)
                $region['ts_unlock_started']=(int)$region['ts_unlocked'];
        }
        unset($region);
        $from=$p['last'];if($now<=$from)return;
        $onlineEnd=$from+60;$cuts=[$from,$now,min($now,$onlineEnd)];
        foreach($p['boosts'] as $b)$cuts[]=$b['ts_end'];
        foreach(['floor','elevator','vault'] as $kind)foreach(self::pool($p['tower'],$kind)['managers'] as $m) {
            $e=TowerEvents::reference()['tower_manager_effects'][$m['effect_id']]['rarities'][$m['rarity']];
            $cuts[]=$m['ts_last_used']+$e['active_time'];
        }
        $cuts=array_values(array_unique(array_filter($cuts,fn($v)=>$v>=$from&&$v<=$now)));sort($cuts);
        for($i=1;$i<count($cuts);$i++) {
            $a=$cuts[$i-1];$dt=$cuts[$i]-$a;$offline=$a>=$onlineEnd;
            if($offline){$p['idle']+=self::rate($p,$a,true)*$dt*(float)TowerEvents::reference()['TowerIdleFactor'];$p['idle_duration']+=$dt;}
            else self::advanceProduction($p,$a,$dt);
        }
        $p['last']=$now;
    }
    public static function objectiveReached(array $tower,array $task): bool
    {
        $floor=(int)$task['floor_index_required'];$total=$tower['vault']['level']+$tower['elevator']['level'];
        foreach($tower['floors'] as $f)$total+=$f['level'];
        if($tower['max_floor_index']<$floor||$total<$task['total_level_required'])return false;
        return match((int)$task['type']) {
            1=>true,2=>($tower['floors'][$floor-1]['level']??0)>=$task['level_required'],
            3=>$tower['vault']['level']>=$task['level_required'],4=>$tower['elevator']['level']>=$task['level_required'],default=>false,
        };
    }
    /** A ponte do cliente envia intenções, nunca preços/níveis ou sorteios para aceitar. */
    public static function command(array &$p,array $a,int $now): array
    {
        $c=TowerEvents::reference();$t=&$p['tower'];$op=(string)($a['command']??'');
        $kind=(string)($a['kind']??'floor');$index=self::number($a['index']??0,0,30);
        $target=&self::target($t,$kind,$index);$pool=&self::pool($t,$kind);
        $type=['floor'=>1,'vault'=>2,'elevator'=>3][$kind];
        if(in_array($op,['level','unlock'],true)) {
            $old=$target['level'];$n=$op==='unlock'?1:self::number($a['count']??1,1,3000);
            if($op==='unlock') {
                if($kind!=='floor'||$old||$index!==$t['max_floor_index']+1)throw new GameError('errInvalidFunction');
                if($index===2&&$t['floors'][0]['level']<$c['TowerSecondFloorUnlockLevel'])throw new GameError('errInvalidFunction');
                $region=intdiv($index-1,5);
                if(!$t['regions'][$region]['ts_unlocked'])throw new GameError('errInvalidFunction');
            }elseif(!$old||($kind!=='floor'&&$t['floors'][0]['level']<$c['TowerElevatorVaultLevelUpUnlockLevel']))throw new GameError('errInvalidFunction');
            $levels=$kind==='floor'?$c['tower_event_floor_levels'][$index]['levels']:$c['tower_event_'.$kind.'_levels'];
            if(!isset($levels[$old+$n]))throw new GameError('errRequestInvalidParameter');
            $e=self::effect($t,$kind,$index,$now);$factor=in_array($e['type']??0,[1,6,10],true)?$e['value']:1;
            $cost=0;for($l=$old+1;$l<=$old+$n;$l++)$cost+=$levels[$l]['cost']*$factor;
            self::spend($p,$cost);$target['level']+=$n;
            if(!$old){$target['ts_unlocked']=$now;$t['max_floor_index']=$index;}
        } elseif(in_array($op,['region','completeRegion'],true)) {
            $ri=self::number($a['region']??-1,1,count($c['tower_event_regions'])-1);$r=&$t['regions'][$ri];$def=$c['tower_event_regions'][$ri];
            if($op==='region') {
                if($r['ts_unlock_started']||$t['max_floor_index']<$def['from_index']-1)throw new GameError('errInvalidFunction');
                self::spend($p,(float)$def['cost']);$r['ts_unlock_started']=$now;$r['ts_unlock_end']=$now+$def['unlock_time'];
            }else {
                if(!$r['ts_unlock_started']||$r['ts_unlocked']||$now<$r['ts_unlock_end'])throw new GameError('errInvalidFunction');
                $r['ts_unlock_end']=-1;$r['ts_unlocked']=$now;
            }
        } elseif($op==='hire') {
            if(!$target['level']||$t['floors'][0]['level']<$c['TowerManagerUnlockLevel'])throw new GameError('errInvalidFunction');
            $limit=$kind==='floor'?200:100;if(count($pool['managers'])>=$limit)throw new GameError('errInvalidFunction');
            $buy=$pool['manager_buy_index'];$entry=$c['tower_manager_costs'][$buy]??throw new GameError('errInvalidFunction');
            $cost=$buy===1?5:$entry[$kind.'_cost'];self::spend($p,(float)$cost);
            $rarity=1;$roll=random_int(1,10000);if($roll>8750)$rarity=3;elseif($roll>5250)$rarity=2;
            $effects=array_keys(array_filter($c['tower_manager_effects'],fn($e)=>$e['manager_type']===$type));
            $effect=$effects[random_int(0,count($effects)-1)];if($kind==='floor'&&$buy===1){$effect=2;$rarity=1;}
            $manager=['id'=>$buy,'effect_id'=>(int)$effect,'rarity'=>$rarity,'name'=>'Gestor '.$buy,'ts_last_used'=>0,'buy_cost'=>$cost];
            $pool['managers'][]=$manager;$pool['manager_buy_index']++;
            if(!$target['manager_id'])$target['manager_id']=$buy;
            return ['manager'=>$manager];
        } elseif(in_array($op,['assign','unassign','activate','sell'],true)) {
            $id=self::number($a['manager_id']??0,1,100000);$pos=null;
            foreach($pool['managers'] as $i=>$m)if($m['id']===$id){$pos=$i;break;}
            if($pos===null)throw new GameError('errRequestInvalidParameter');$m=&$pool['managers'][$pos];
            $assigned=[];
            if($kind==='floor'){foreach($t['floors'] as $f)if($f['manager_id']===$id)$assigned[]=$f['index'];}
            elseif($t[$kind]['manager_id']===$id)$assigned[]=$index;
            if($op==='assign') {
                if(!$target['level']||$assigned||$target['manager_id'])throw new GameError('errInvalidFunction');$target['manager_id']=$id;
            }elseif($op==='unassign') {
                if($target['manager_id']!==$id)throw new GameError('errInvalidFunction');$target['manager_id']=0;
                $e=$c['tower_manager_effects'][$m['effect_id']]['rarities'][$m['rarity']];
                if($m['ts_last_used']&&$m['ts_last_used']+$e['active_time']>$now)$m['ts_last_used']=$now-$e['active_time'];
            }elseif($op==='activate') {
                $e=$c['tower_manager_effects'][$m['effect_id']]['rarities'][$m['rarity']];
                if(!$assigned||($m['ts_last_used']&&$now<$m['ts_last_used']+$e['active_time']+$e['cooldown']))throw new GameError('errInvalidFunction');
                $m['ts_last_used']=$now;
            }else {
                if($assigned)throw new GameError('errInvalidFunction');$p['bank']+=$m['buy_cost']*$c['TowerManagerSellFactor'];
                unset($m);array_splice($pool['managers'],$pos,1);
            }
        }else throw new GameError('errRequestInvalidParameter');
        return [];
    }
}
