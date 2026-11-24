<?php
declare(strict_types=1);
require_once __DIR__.'/event_reward_assertions.php';
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventDraws as D,Character,GameTime,GameError,Live,Router};
function drCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function drReject(callable $fn,string $code):void {try{$fn();}catch(GameError $e){drCheck($e->getMessage()===$code,'Esperava '.$code.', recebeu '.$e->getMessage());return;}throw new RuntimeException('Pedido devia falhar: '.$code);}
function drRow(int $cid):array {return Db::row('SELECT * FROM event_draw_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1',[$cid])??throw new RuntimeException('Sem participação');}
function drPayload(int $cid,array $p):void {unset($p['id'],$p['identifier'],$p['status']);Db::exec('UPDATE event_draw_progress SET payload=? WHERE id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)drRow($cid)['id']]);}
function drScenario(PDO $pdo,int $cid,string $id,callable $fn):void {
    $pdo->exec('SAVEPOINT draw_scenario');
    try{D::schedule($id,GameTime::now()-1,GameTime::now()+86400);D::assign($cid,$id);$fn();}
    finally{$pdo->exec('ROLLBACK TO SAVEPOINT draw_scenario');$pdo->exec('RELEASE SAVEPOINT draw_scenario');}
}
$pdo=null;
try {
    $mysql=false;$hero='';foreach(array_slice($argv,1) as $arg){if($arg==='--mysql')$mysql=true;elseif(str_starts_with($arg,'--hero='))$hero=substr($arg,7);else throw new InvalidArgumentException('Uso: test_event_draws.php [--mysql] [--hero="Nome"]');}
    $c=D::reference();drCheck(count($c['event_draw_pools'])===55,'55 catálogos');$totalRewards=0;
    foreach($c['event_draw_pools'] as $id=>$d) {
        foreach(['_i.webp','_background.webp'] as $suffix)drCheck(is_file(dirname(__DIR__,2).'/server-laravel/public/assets/draw_event/'.$id.$suffix),'Asset público da pirâmide');
        drCheck(count($d['reward_index'])===15&&D::cost($id,[])===0,'15 prémios e primeiro gratuito');$totalRewards+=15;
        foreach($d['reward_index'] as $i=>$r) {
            drCheck($r['chance']>0&&$r['cost_premium_currency']>=0,'Pesos/custos válidos');
            drCheck(in_array($r['reward_type'],[3,5,12],true),'Tipos suportados');
            if($r['reward_type']===5)drCheck(isset($c['item_templates'][$r['reward_reference']]),'Item válido');
        }
        $drawn=[];
        foreach(range(0,14) as $i) {
            $chances=D::chances($id,$drawn);drCheck(abs(array_sum($chances)-1)<1e-10,'Probabilidades somam 1');
            drCheck(D::cost($id,$drawn)===$d['reward_index'][$i]['cost_premium_currency'],'Custo depende do número de sorteios');
            $pick=D::choose($id,$drawn);drCheck(!isset($drawn[$pick]),'Sem prémios repetidos');$drawn[$pick]=1;
        }
        drCheck(D::cost($id,$drawn)===0&&array_sum(D::chances($id,$drawn))===0.0,'Catálogo esgotado');drReject(fn()=>D::choose($id,$drawn),'errInvalidFunction');
    }
    drCheck(is_file(dirname(__DIR__,2).'/server-laravel/public/assets/draw_event/header.webp'),'Cabeçalho público da pirâmide');
    echo "PASS offline: 55 pools, $totalRewards prémios, pesos normalizados, custos progressivos e seleção sem reposição.\n";
    if(!$mysql)exit;
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$pdo=Db::pdo();
    $connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $heroes=$hero!==''?Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]):Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
    drCheck(count($heroes)===1,'Herói único');$cid=(int)$heroes[0]['id'];$uid=(int)$heroes[0]['user_id'];echo 'Herói: '.$heroes[0]['name']."; tudo preparado em transação com rollback.\n";
    $pdo->beginTransaction();Db::exec('UPDATE event_draw_occurrences SET enabled=0');Db::exec('UPDATE event_draw_progress SET closed=1 WHERE character_id=?',[$cid]);
    Db::exec('UPDATE `character` SET level=100,xp=? WHERE id=?',[Character::xpForLevel(100),$cid]);Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);
    $empty=[];foreach(range(1,18) as $i)$empty[]='bag_item'.$i.'_id=0';Db::exec('UPDATE inventory SET '.implode(',',$empty).' WHERE character_id=?',[$cid]);
    foreach($c['event_draw_pools'] as $id=>$d)drScenario($pdo,$cid,$id,static function()use($cid,$uid,$id,$d):void {
        $before=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);$cost=0;$seen=[];
        foreach(range(0,14) as $n) {
            $cost+=$d['reward_index'][$n]['cost_premium_currency'];D::draw($cid);$p=D::state($cid);$new=array_diff(array_keys($p['drawn']),$seen);drCheck(count($new)===1,'Um novo prémio');$index=(int)reset($new);$seen[]=$index;
            if($n===0)drCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$before,'Primeiro sorteio grátis');
            drReject(fn()=>D::draw($cid),'errInvalidFunction');
            $p=D::state($cid);drCheck(isset($p['rewards'][$index]),'Plano persistido');
            if(isset($p['rewards'][$index]['item'])) {
                $item=Db::row('SELECT * FROM items WHERE id=?',[$p['rewards'][$index]['item']]);assertEventRewardEquipment($item,$cid);drCheck($item['identifier']===$d['reward_index'][$index]['reward_reference'],'Referência exata do prémio');
            }
            D::claim($cid,$index,true);drReject(fn()=>D::claim($cid,$index,true),'errClaimDrawEventRewardInvalidReward');
        }
        drCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$before-$cost,'Débito total exato');
        drCheck(count(array_unique($seen))===15&&D::state($cid)['id']===0,'15 sorteios distintos e fecho');
        $shape=D::attach(['character'=>['id'=>$cid]],$cid);drCheck($shape['draw_event']['status']===2&&$shape['character']['draw_event_id']===0,'Último estado fechado enviado');
        drReject(fn()=>D::assign($cid,$id),'errAssignDrawEventAlreadyAssigned');
        drReject(fn()=>D::draw($cid),'errInvalidFunction');
        echo "OK pool: $id — 15 sorteios/resgates, custo $cost donuts.\n";
    });
    echo "PASS MySQL: 55 catálogos completos, 825 sorteios/resgates, sem repetição e débitos exatos.\n";
    drScenario($pdo,$cid,'beetle_draw_event',static function()use($cid,$uid,$empty):void {
        drReject(fn()=>D::claim($cid,0),'errClaimDrawEventRewardInvalidReward');
        // Prepara todos os outros prémios como já sorteados/recolhidos para exercitar um alvo exato.
        $p=D::state($cid);foreach(range(1,14) as $i){$p['drawn'][$i]=GameTime::now();$p['claimed'][$i]=true;}drPayload($cid,$p);
        $before=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);D::draw($cid);D::claim($cid,0);
        drCheck((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$before+1,'Pet entregue à coleção');
    });
    drScenario($pdo,$cid,'beetle_draw_event',static function()use($cid,$uid):void {
        $p=D::state($cid);foreach(range(0,14) as $i)if($i!==2){$p['drawn'][$i]=GameTime::now();$p['claimed'][$i]=true;}drPayload($cid,$p);
        Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);$before=D::state($cid);drReject(fn()=>D::draw($cid),'errRemovePremiumCurrencyNotEnough');drCheck(D::state($cid)===$before,'Falta de saldo não altera sorteio');
        Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);D::draw($cid);$item=D::state($cid)['rewards'][2]['item'];
        $full=[];foreach(range(1,18) as $i)$full[]='bag_item'.$i.'_id='.$item;Db::exec('UPDATE inventory SET '.implode(',',$full).' WHERE character_id=?',[$cid]);
        drReject(fn()=>D::claim($cid,2),'errInventoryNoEmptySlot');drCheck(!isset(D::state($cid)['claimed'][2]),'Mochila cheia preserva prémio');
        Db::exec('UPDATE inventory SET bag_item1_id=0 WHERE character_id=?',[$cid]);D::claim($cid,2);drCheck((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id=?',[$cid])===$item,'Equipamento entregue no espaço livre');assertEventRewardEquipment(Db::row('SELECT * FROM items WHERE id=?',[$item]),$cid);
    });
    drScenario($pdo,$cid,'beetle_draw_event',static function()use($cid):void {
        $p=D::state($cid);foreach(range(0,14) as $i)if($i!==1){$p['drawn'][$i]=GameTime::now();$p['claimed'][$i]=true;}drPayload($cid,$p);D::draw($cid);$id=D::state($cid)['rewards'][1]['item'];D::claim($cid,1);
        $item=Db::row('SELECT * FROM items WHERE id=?',[$id]);drCheck($item['identifier']==='improvement_sidekick_legendary'&&(int)$item['quality']===4,'Modificação lendária de pet entregue');
    });
    drScenario($pdo,$cid,'beetle_draw_event',static function()use($cid):void {
        $p=D::state($cid);foreach(range(0,13) as $i){$p['drawn'][$i]=GameTime::now();$p['claimed'][$i]=true;}drPayload($cid,$p);$before=(int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid]);D::draw($cid);D::claim($cid,14);
        drCheck((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])===$before+5,'Cinco pontos de atributos diretos');
    });
    $eid=D::schedule('revenge_draw_event',GameTime::now()-1,GameTime::now()+86400);
    Db::exec('UPDATE `character` SET level=4 WHERE id=?',[$cid]);drReject(fn()=>D::assign($cid,'revenge_draw_event'),'errRequestInvalidParameter');Db::exec('UPDATE `character` SET level=100 WHERE id=?',[$cid]);
    try{D::schedule('fox_draw_event',GameTime::now(),GameTime::now()+100);throw new RuntimeException('Sobreposição aceite');}catch(InvalidArgumentException $e){}
    Router::dispatch('assignDrawEvent',['action'=>'assignDrawEvent','user_id'=>$uid,'draw_event_id'=>'revenge_draw_event']);
    $other=Db::row('SELECT c.id,c.level FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.id<>? ORDER BY c.id LIMIT 1',[$cid]);
    if($other) {
        $otherId=(int)$other['id'];Db::exec('UPDATE `character` SET level=100 WHERE id=?',[$otherId]);Db::exec('UPDATE event_draw_progress SET closed=1 WHERE character_id=?',[$otherId]);
        $invite=D::attach(['character'=>['id'=>$otherId]],$otherId);
        drCheck(($invite['draw_event']['identifier']??'')==='revenge_draw_event'&&!isset($invite['draw_event']['id']),'Convite global chega à segunda conta');
        D::assign($otherId,'revenge_draw_event');D::draw($otherId);
        drCheck(count(D::state($otherId)['drawn'])===1&&count(D::state($cid)['drawn'])===0,'Sorteios individuais não alteram outra conta');
        drCheck(D::state($otherId)['id']!==D::state($cid)['id'],'IDs de participação distintos');
    }
    $boot=Live::accountState($uid);drCheck($boot['character']['draw_event_id']===$boot['draw_event']['id'],'Estado normal ID/objeto');
    $result=Router::dispatch('drawEventReward',['action'=>'drawEventReward','user_id'=>$uid]);$p=D::state($cid);$index=(int)array_key_first($p['drawn']);drCheck($result['draw_event']['ts_reward'.$index.'_drawn']>0,'Handler real do sorteio');
    $hub=Router::dispatch('getEventHubEntries',['action'=>'getEventHubEntries','user_id'=>$uid]);drCheck(in_array('revenge_draw_event_draw',array_column($hub['event_hub_entries'],'identifier'),true),'Hub real');
    $session=(string)Db::value('SELECT session_id FROM `user` WHERE id=?',[$uid]);$login=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','existing_user_id'=>$uid,'existing_session_id'=>$session,'user_session_id'=>$session]);
    drCheck($login['draw_event']['id']===$login['character']['draw_event_id']&&$login['draw_event']['ts_reward'.$index.'_drawn']>0,'Login real preserva sorteio');
    D::cancel($eid);drReject(fn()=>D::draw($cid),'errInvalidFunction');drCheck(D::attach(['character'=>['id'=>$cid]],$cid)['draw_event']['status']===3,'Cancelamento preserva pendência');
    $result=Router::dispatch('claimDrawEventReward',['action'=>'claimDrawEventReward','user_id'=>$uid,'reward_index'=>$index,'discard_item'=>true]);drCheck($result['draw_event']['status']===4&&$result['character']['draw_event_id']===0,'Resgate tardio fecha estado abortado');
    $next=D::schedule('revenge_draw_event',GameTime::now()-1,GameTime::now()+86400);D::assign($cid,'revenge_draw_event');drCheck(count(D::state($cid)['drawn'])===0&&D::cost('revenge_draw_event',[])===0,'Nova edição começa gratuita e sem progresso anterior');
    D::draw($cid);$p=D::state($cid);$index=(int)array_key_first($p['drawn']);Db::exec('UPDATE event_draw_occurrences SET ts_end=? WHERE id=?',[GameTime::now()-1,$next]);
    drCheck(D::attach(['character'=>['id'=>$cid]],$cid)['draw_event']['status']===3,'Expiração por GameTime');D::claim($cid,$index,true);drCheck(D::state($cid)['id']===0,'Expirado resgatado fecha');
    D::schedule('fox_draw_event',GameTime::now()+100,GameTime::now()+200);drReject(fn()=>D::assign($cid,'fox_draw_event'),'errRequestInvalidParameter');
    drCheck($pdo->inTransaction(),'Transação exterior preservada');
    echo "PASS integração MySQL: mochila cheia/retry, equipamento, pet, modificação lendária, atributos, handlers, calendário, expiração, login e hub; rollback.\n";
} catch(Throwable $e){fwrite(STDERR,'FALHOU: '.$e->getMessage()."\n");exit(1);}
finally{if($pdo&&$pdo->inTransaction())$pdo->rollBack();}
