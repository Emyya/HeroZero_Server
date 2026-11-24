<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{TowerEvents as T,TowerEconomy as E,Db,Character,GameTime,GameError,Live};
function checkTower(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function rejectTower(callable $fn):void {try{$fn();}catch(GameError $e){return;}throw new RuntimeException('Aceitou pedido inválido');}
$pdo=null;
try {
    $mysql=in_array('--mysql',$argv,true);$hero='';foreach($argv as $arg)if(str_starts_with($arg,'--hero='))$hero=substr($arg,7);
    $c=T::reference();checkTower(count($c['tower_events'])===6,'Catálogo');
    foreach([[3,false,2.3],[3,true,2.7],[4,false,3.0],[4,true,3.5]] as [$quality,$gold,$factor]) {
        checkTower(Character::shopItemMultiplier($quality,$gold)===$factor,'Multiplicador');
        foreach([1,50,130,300] as $level)for($i=0;$i<20;$i++) {
            $stats=Character::shopItemStats($level,$quality,7,$gold);
            checkTower(array_sum($stats)===(int)ceil(($level*1.6+2+$level**2*.004)*$factor)+7,'Orçamento exato: '.json_encode([$level,$quality,$gold,$stats,$factor,(int)ceil(($level*1.6+2+$level**2*.004)*$factor)+7]));
            checkTower(count(array_filter($stats))>=3,'Distribuição');
        }
    }
    $legacy=T::fresh('earth_towerevent',1000);$legacy['tower']['regions'][0]['ts_unlock_started']=0;
    $legacyBefore=$legacy;E::accrue($legacy,1000);
    checkTower($legacy['tower']['regions'][0]['ts_unlock_started']===1000,'Região inicial legada aberta no cliente');
    $legacyBefore['tower']['regions'][0]['ts_unlock_started']=1000;
    checkTower($legacy===$legacyBefore,'Normalização preserva progresso e saldos');
    $p=T::fresh('earth_towerevent',1000);E::command($p,['command'=>'unlock','kind'=>'floor','index'=>1],1000);
    checkTower($p['bank']==0&&$p['tower']['max_floor_index']===1,'Primeiro piso');
    rejectTower(function()use(&$p){E::command($p,['command'=>'unlock','kind'=>'floor','index'=>3],1000);});
    rejectTower(function()use(&$p){E::command($p,['command'=>'level','kind'=>'floor','index'=>1,'count'=>1001],1000);});
    E::accrue($p,1010);checkTower($p['bank']>0,'Produção inicial');
    E::command($p,['command'=>'level','kind'=>'floor','index'=>1,'count'=>2],1010);
    $p['bank']=1000;
    foreach(['floor','elevator','vault'] as $kind)E::command($p,['command'=>'hire','kind'=>$kind,'index'=>$kind==='floor'?1:0],1010);
    checkTower(E::rate($p,1010,true)>0,'Rendimento automatizado');
    E::command($p,['command'=>'activate','kind'=>'floor','index'=>1,'manager_id'=>1],1010);
    rejectTower(function()use(&$p){E::command($p,['command'=>'activate','kind'=>'floor','index'=>1,'manager_id'=>1],1011);});
    rejectTower(function()use(&$p){E::command($p,['command'=>'sell','kind'=>'floor','index'=>1,'manager_id'=>1],1011);});
    E::accrue($p,2000);checkTower($p['idle']>0,'Rendimento offline');
    foreach($c['tower_events'] as $id=>$d) {
        $state=T::clientState($id,['id'=>1,'enabled'=>1,'ts_end'=>3000],T::fresh($id,1000));
        checkTower(count($state['towers'][0]['floors'])===30,'30 pisos');
        foreach($c['tower_event_objectives'][$d['objectives']]['index'] as $r)checkTower(in_array($r['reward_type'],[0,1,2,3,4,5,6,14,16,46,50],true),'Tipo de recompensa implementado');
    }
    // Conservação de moedas e filas reais com estrangulamentos distintos.
    $flowTest=T::fresh('earth_towerevent',1000);$flowTest['tower']['floors'][0]['level']=6;$flowTest['tower']['max_floor_index']=1;
    $capacities=E::flows($flowTest,1000);$initialBank=$flowTest['bank'];
    E::advanceProduction($flowTest,1000,30);
    $queued=$flowTest['tower']['floors'][0]['bank_amount']+$flowTest['tower']['elevator']['bank_amount'];
    checkTower($flowTest['tower']['floors'][0]['bank_amount']>0,'Produção acumula antes do elevador');
    checkTower(abs(($flowTest['bank']-$initialBank+$queued)-array_sum($capacities['floors'])*30)<1e-8,'Sem criação/perda de moedas nas etapas');
    $vaultBottleneck=T::fresh('earth_towerevent',1000);$vaultBottleneck['tower']['floors'][0]['level']=6;
    $vaultBottleneck['tower']['elevator']['level']=100;$vaultBottleneck['tower']['max_floor_index']=1;
    E::advanceProduction($vaultBottleneck,1000,10);
    checkTower($vaultBottleneck['tower']['elevator']['bank_amount']>0,'Transporte acumula antes do cofre');
    $beforeNoTime=$vaultBottleneck;E::advanceProduction($vaultBottleneck,1000,0);
    checkTower($beforeNoTime===$vaultBottleneck,'Sem entrega repetida no mesmo intervalo');
    $flowTest['last']=1000;E::accrue($flowTest,1001);$afterAccrue=$flowTest;E::accrue($flowTest,1001);
    checkTower($flowTest===$afterAccrue,'Sincronização repetida não produz outra vez');
    echo "PASS offline: fórmulas, catálogo, progressão, gestores, cooldowns e rendimento.\n";
    if(!$mysql)exit;
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$pdo=Db::pdo();
    $connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $rows=$hero!==''?Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]):Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
    checkTower(count($rows)===1,'Herói único');$cid=(int)$rows[0]['id'];$uid=(int)$rows[0]['user_id'];
    echo 'MySQL: '.$rows[0]['name']."; preparação e testes com rollback.\n";
    $pdo->beginTransaction();Db::exec('UPDATE tower_event_occurrences SET enabled=0');Db::exec('UPDATE tower_event_progress SET closed=1 WHERE character_id=?',[$cid]);
    Db::exec('UPDATE `character` SET level=100,xp=? WHERE id=?',[Character::xpForLevel(100),$cid]);Db::exec('UPDATE `user` SET premium_currency=100000 WHERE id=?',[$uid]);
    $clear=function()use($cid){$cols=[];foreach(range(1,18) as $i)$cols[]='bag_item'.$i.'_id=0';Db::exec('UPDATE inventory SET '.implode(',',$cols).' WHERE character_id=?',[$cid]);};$clear();
    foreach($c['tower_events'] as $id=>$def) {
        $pdo->exec('SAVEPOINT tower_variant');
        T::schedule($id,GameTime::now()-1,GameTime::now()+86400);
        $invite=T::attach(['character'=>['level'=>100]],$cid)['tower_event_data'];checkTower($invite['events']['events_data'][$id]['ts_user_start']===0,'Convite');
        $r=T::action($cid,['action'=>'startTowerEvent','event_id'=>$id]);
        // Reproduzir a participação antiga e verificar reparação pelo estado normal.
        $legacyRow=Db::row('SELECT id,payload FROM tower_event_progress WHERE character_id=? AND closed=0',[$cid]);
        $legacyPayload=json_decode($legacyRow['payload'],true);$legacyPayload['tower']['regions'][0]['ts_unlock_started']=0;
        Db::exec('UPDATE tower_event_progress SET payload=? WHERE id=?',[json_encode($legacyPayload),$legacyRow['id']]);
        $legacyState=T::attach(['character'=>['level'=>100]],$cid)['tower_event_data'];
        checkTower($legacyState['towers'][0]['regions'][0]['ts_unlock_started']>0,'Estado normal abre região legada');
        $savedLegacy=json_decode(Db::value('SELECT payload FROM tower_event_progress WHERE id=?',[$legacyRow['id']]),true);
        checkTower($savedLegacy['tower']['regions'][0]['ts_unlock_started']>0,'Normalização persistida sob lock');
        rejectTower(fn()=>T::action($cid,['action'=>'collectTowerEventObjectiveRewards','event_id'=>$id,'objective_indices'=>'[3]']));
        $r=T::action($cid,['action'=>'towerCommand','command'=>'unlock','kind'=>'floor','index'=>1]);
        $productionRow=Db::row('SELECT id,payload FROM tower_event_progress WHERE character_id=? AND closed=0',[$cid]);
        $productionPayload=json_decode($productionRow['payload'],true);$productionPayload['tower']['floors'][0]['level']=6;$productionPayload['last']=GameTime::now()-10;
        Db::exec('UPDATE tower_event_progress SET payload=? WHERE id=?',[json_encode($productionPayload),$productionRow['id']]);
        $productionState=T::attach(['character'=>['level'=>100]],$cid)['tower_event_data'];
        checkTower($productionState['towers'][0]['floors'][0]['bank_amount']>0,'Resposta real envia acumulado de produção');
        $storedProduction=json_decode(Db::value('SELECT payload FROM tower_event_progress WHERE id=?',[$productionRow['id']]),true);
        checkTower($storedProduction['tower']['floors'][0]['bank_amount']>0,'Produção intermédia persistida');
        // Repor apenas o cenário do teste; toda a preparação exterior é revertida.
        Db::exec('UPDATE tower_event_progress SET payload=? WHERE id=?',[$productionRow['payload'],$productionRow['id']]);
        $forged=['floors'=>[['index'=>1,'level'=>1000]],'tutorial_step'=>1];
        $r=T::action($cid,['action'=>'syncTowerEvent','tower_id'=>$def['tower_id'],'tower_data'=>json_encode($forged),'economy_data'=>'[{"id":1,"bank_amount":1e100}]']);
        checkTower($r['tower_authoritative']['tower']['floors'][0]['level']===1&&$r['tower_authoritative']['bank']<10000,'Snapshot não injeta progresso/dinheiro');
        rejectTower(fn()=>T::action($cid,['action'=>'collectTowerObjectiveReward','tower_id'=>$def['tower_id'],'objective_key'=>'1','objective_id'=>'reach_floor2']));
        $row=Db::row('SELECT * FROM tower_event_progress WHERE character_id=? AND closed=0',[$cid]);$p=json_decode($row['payload'],true);$p['tokens']=10000;
        Db::exec('UPDATE tower_event_progress SET payload=? WHERE id=?',[json_encode($p),(int)$row['id']]);
        foreach($c['tower_event_objectives'][$def['objectives']]['index'] as $i=>$reward) {
            $clear();$r=T::action($cid,['action'=>'collectTowerEventObjectiveRewards','event_id'=>$id,'objective_indices'=>json_encode([(int)$i])]);
            $itemId=$r['event']['objectives']->{$i}['rewards']??null;
            $itemId=json_decode($itemId??'{}',true)['item']??0;
            if($itemId) {
                $item=Db::row('SELECT * FROM items WHERE id=?',[$itemId]);
                if($item['type']>=1&&$item['type']<=7) {
                    checkTower((int)$item['quality']===4&&(int)$item['ts_availability_start']===15&&(int)$item['ts_availability_end']===1,'Equipamento lendário dourado com espaço');
                    $level=(int)$item['required_level'];$sum=0;foreach(['stamina','strength','critical_rating','dodge_rating'] as $stat)$sum+=(int)$item['stat_'.$stat];
                    checkTower($sum>=(int)ceil(($level*1.6+2+$level**2*.004)*3.5),'Atributos lendários');
                }
            }
            rejectTower(fn()=>T::action($cid,['action'=>'collectTowerEventObjectiveRewards','event_id'=>$id,'objective_indices'=>json_encode([(int)$i])]));
        }
        $r=T::action($cid,['action'=>'resetTowerEventProgress','event_id'=>$id]);checkTower($r['tower_authoritative']['round']===2,'Reset');
        $pdo->exec('ROLLBACK TO SAVEPOINT tower_variant');$pdo->exec('RELEASE SAVEPOINT tower_variant');
    }
    $pdo->rollBack();echo "PASS MySQL: seis variantes, todos os prémios, resgates duplicados, snapshot forjado, reset e rollback.\n";
}catch(Throwable $e){if($pdo&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n");exit(1);}
