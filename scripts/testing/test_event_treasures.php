<?php
declare(strict_types=1);
require_once __DIR__.'/event_reward_assertions.php';
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventTreasures as T,Character,GameTime,GameError,Live,Router,StoryDungeon};
function trCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function trReject(callable $fn,string $code):void {try{$fn();}catch(GameError $e){trCheck($e->getMessage()===$code,'Esperava '.$code.', recebeu '.$e->getMessage());return;}throw new RuntimeException('Pedido devia falhar: '.$code);}
function trRow(int $cid):array {return Db::row('SELECT * FROM event_treasure_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1',[$cid])??throw new RuntimeException('Sem progresso');}
function trSet(int $cid,array $p):void {unset($p['id'],$p['identifier']);Db::exec('UPDATE event_treasure_progress SET payload=? WHERE id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)trRow($cid)['id']]);}
function trPrepareRewards(int $cid):void {
    StoryDungeon::transaction($cid,static function(array $char)use($cid):void {
        $row=trRow($cid);$p=T::state($cid);unset($p['id'],$p['identifier']);$defs=T::definition($row['identifier'])['reward_index'];$p['tokens']=$defs[array_key_last($defs)]['required_token_amount'];
        $args=[$char,$row,&$p];(new ReflectionMethod(T::class,'unlockRewards'))->invokeArgs(null,$args);trSet($cid,$p);
    });
}
$pdo=null;
try {
    $mysql=false;$hero='';foreach(array_slice($argv,1) as $arg) {
        if($arg==='--mysql')$mysql=true;elseif(str_starts_with($arg,'--hero='))$hero=substr($arg,7);else throw new InvalidArgumentException('Uso: test_event_treasures.php [--mysql] [--hero="Nome"]');
    }
    $c=T::reference();trCheck(count($c['event_treasures'])===47,'47 variantes');
    foreach($c['event_treasures'] as $id=>$event) {
        $previous=0;
        foreach($event['reward_index'] as $r){trCheck($r['required_token_amount']>$previous,'Patamares crescentes');$previous=$r['required_token_amount'];trCheck(in_array($r['reward_type'],[5,12],true),'Tipos suportados');}
        $total=0;
        foreach(T::location($id) as $level=>$d) {
            $b=T::board($d,100);$types=array_count_values(array_column($b['hidden'],'type'));
            trCheck(($types[1]??0)===$d['tokens']&&($types[2]??0)===count($d['bonus_reward']),'Conteúdo do tabuleiro');
            trCheck(count($b['hidden'])<=$d['grid_size']**2&&$b['cells']===[],'Tabuleiro válido e secreto');$total+=$d['tokens'];
        }
        trCheck($total>=$previous,'Toupeiras suficientes para todos os prémios');
    }
    foreach([1=>1,449=>1,450=>2,749=>2,750=>3,1049=>3,1050=>4,1800=>4] as $seconds=>$cost)trCheck(T::skipCost($seconds)===$cost,'Custo do desbloqueio');
    trCheck(T::bonus(3,25,100)===['quest_energy'=>25]&&T::bonus(4,15,100)===['training_sessions'=>15],'Energia e treino');
    echo "PASS offline: 47 definições, patamares, 20 quintais por variante, tabuleiros privados e custos.\n";
    if(!$mysql)exit;
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$pdo=Db::pdo();
    $connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $heroes=$hero!==''?Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]):Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
    trCheck(count($heroes)===1,'Herói único');$cid=(int)$heroes[0]['id'];$uid=(int)$heroes[0]['user_id'];echo 'Herói: '.$heroes[0]['name']."; preparação e testes serão revertidos.\n";
    $pdo->beginTransaction();Db::exec('UPDATE event_treasure_occurrences SET enabled=0');Db::exec('UPDATE event_treasure_progress SET closed=1 WHERE character_id=?',[$cid]);
    Db::exec('UPDATE `character` SET level=100,xp=?,active_quest_id=0 WHERE id=?',[Character::xpForLevel(100),$cid]);Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);
    $slots=[];foreach(range(1,18) as $i)$slots[]='bag_item'.$i.'_id=0';Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id=?',[$cid]);
    foreach($c['event_treasures'] as $id=>$event) {
        $pdo->exec('SAVEPOINT treasure_catalog');
        try {
            T::schedule($id,GameTime::now()-1,GameTime::now()+86400);T::assign($cid,$id);T::next($cid);
            $shape=T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event'];trCheck(!str_contains($shape['levels'],'hidden'),'Tabuleiro secreto não enviado');
            trPrepareRewards($cid);trCheck(count(T::state($cid)['rewards'])===count($event['reward_index']),'Todos os previews '.$id);
            foreach($event['reward_index'] as $index=>$r) {
                $preview=Db::row('SELECT * FROM items WHERE id=?',[T::state($cid)['rewards'][$index]['item']]);
                assertEventRewardEquipment($preview,$cid);T::claim($cid,$index,true);
            }
            trReject(fn()=>T::claim($cid,0,true),'errCollectTreasureEventRewardAlreadyCollected');
        }finally{$pdo->exec('ROLLBACK TO SAVEPOINT treasure_catalog');$pdo->exec('RELEASE SAVEPOINT treasure_catalog');}
    }
    echo "PASS MySQL: 47 aceitações, todos os 486 patamares/previews e resgates com descarte; retries recusados.\n";
    $eid=T::schedule('revenge_treasure',GameTime::now()-1,GameTime::now()+86400);
    // Janelas, nível e sobreposição.
    try{T::schedule('ghost_treasure',GameTime::now(),GameTime::now()+100);throw new RuntimeException('Sobreposição aceite');}catch(InvalidArgumentException $e){}
    Db::exec('UPDATE `character` SET level=4 WHERE id=?',[$cid]);trReject(fn()=>T::assign($cid,'revenge_treasure'),'errRequestInvalidParameter');Db::exec('UPDATE `character` SET level=100 WHERE id=?',[$cid]);
    T::assign($cid,'revenge_treasure');trReject(fn()=>T::assign($cid,'revenge_treasure'),'errAssignTreasureEventAlreadyAssigned');
    T::free($cid);trCheck(T::state($cid)['shovels']===20,'20 pás gratuitas');trReject(fn()=>T::free($cid),'errClaimFreeTreasureRevealItemsCooldownActive');
    $p=T::state($cid);$p['last_free']=GameTime::now()-10800;trSet($cid,$p);T::free($cid);trCheck(T::state($cid)['shovels']===40,'Recarga após 3 horas');
    T::next($cid);trReject(fn()=>T::next($cid),'errInvalidFunction');trReject(fn()=>T::open($cid,1,-1,0,false),'errRequestInvalidParameter');
    $p=T::state($cid);$p['shovels']=0;trSet($cid,$p);trReject(fn()=>T::open($cid,1,0,0,false),'errUseItemNotEnough');
    Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);trReject(fn()=>T::open($cid,1,0,0,true),'errRemovePremiumCurrencyNotEnough');Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);
    $before=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);T::open($cid,1,0,0,true);trReject(fn()=>T::open($cid,1,0,0,true),'errAlreadyOpened');trCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$before-1,'Premium debitado uma vez');
    $p=T::state($cid);$p['shovels']=2000;trSet($cid,$p);
    $vouchersBefore=(int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id=?',[$uid]);
    foreach(T::location('revenge_treasure') as $level=>$d) {
        if($level>1){T::next($cid);trReject(fn()=>T::open($cid,$level,0,0,false),'errInvalidFunction');T::skip($cid,$level);trReject(fn()=>T::skip($cid,$level),'errInvalidFunction');}
        for($x=0;$x<$d['grid_size'];$x++)for($y=0;$y<$d['grid_size'];$y++) {
            if(!isset(T::state($cid)['levels'][$level]['cells'][$x.'_'.$y]))T::open($cid,$level,$x,$y,false);
            $p=T::state($cid);$cell=$p['levels'][$level]['cells'][$x.'_'.$y];
            if($cell['type']===2){T::collect($cid,$level,$x,$y);trReject(fn()=>T::collect($cid,$level,$x,$y),'errCollectTreasureEventCellRewardAlreadyCollected');}
            if($cell['type']===3) {
                $expected=0;for($dx=-1;$dx<=1;$dx++)for($dy=-1;$dy<=1;$dy++)if(($dx||$dy)&&isset($p['levels'][$level]['hidden'][($x+$dx).'_'.($y+$dy)]))$expected++;
                trCheck($cell['neighbours']===$expected,'Pista dos 8 vizinhos');
            }
        }
    }
    trCheck(T::state($cid)['tokens']===120,'120 toupeiras reais em 20 quintais');trReject(fn()=>T::next($cid),'errInvalidFunction');
    trCheck((int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id=?',[$uid])===$vouchersBefore+3,'Energia 25/50 e treino 15 em cupões');
    $p=T::state($cid);$item=$p['rewards'][0]['item'];$full=[];foreach(range(1,18) as $i)$full[]='bag_item'.$i.'_id='.$item;Db::exec('UPDATE inventory SET '.implode(',',$full).' WHERE character_id=?',[$cid]);
    trReject(fn()=>T::claim($cid,0),'errInventoryNoEmptySlot');trCheck(!isset(T::state($cid)['claimed'][0]),'Mochila cheia não resgata');
    Db::exec('UPDATE inventory SET bag_item1_id=0 WHERE character_id=?',[$cid]);T::claim($cid,0);trCheck((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id=?',[$cid])===$item,'Equipamento entregue');assertEventRewardEquipment(Db::row('SELECT * FROM items WHERE id=?',[$item]),$cid);
    Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id=?',[$cid]);
    $petBefore=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
    T::claim($cid,9);trCheck((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$petBefore+1,'Pet entregue');
    T::claim($cid,8);$mod=T::state($cid)['rewards'][8]['item'];trCheck(Db::value('SELECT identifier FROM items WHERE id=?',[$mod])==='improvement_weapon_epic','Modificação correta');
    foreach(range(1,7) as $i)T::claim($cid,$i,true);
    $shape=Live::accountState($uid);trCheck($shape['treasure_event']['id']===$shape['character']['treasure_event_id'],'accountState aponta para participação');trCheck(!str_contains($shape['treasure_event']['levels'],'hidden'),'Estado normal não expõe mapa');
    $hub=Router::dispatch('getEventHubEntries',['action'=>'getEventHubEntries','user_id'=>$uid]);trCheck(in_array('revenge_treasure_treasure',array_column($hub['event_hub_entries'],'identifier'),true),'Hub real');
    $shovels=T::state($cid)['shovels'];$premium=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);T::reset($cid);
    trCheck(T::state($cid)['shovels']===$shovels&&T::state($cid)['tokens']===0&&T::state($cid)['level']===0,'Reset conserva pás e limpa progresso');trCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$premium-49,'Reset custa 49');trReject(fn()=>T::reset($cid),'errInvalidFunction');
    trPrepareRewards($cid);trCheck(T::state($cid)['rewards'][8]===['statPoints'=>10],'Modificação única repetida usa alternativa');
    T::cancel($eid);trReject(fn()=>T::free($cid),'errInvalidFunction');trReject(fn()=>T::next($cid),'errInvalidFunction');
    trCheck(T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event']['status']===3,'Cancelamento expira estado');
    foreach(T::definition('revenge_treasure')['reward_index'] as $i=>$d)T::claim($cid,$i,true);
    trCheck(!isset(T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event']),'Expirado e resgatado fecha automaticamente');
    $new=T::schedule('revenge_treasure',GameTime::now()-1,GameTime::now()+86400);T::assign($cid,'revenge_treasure');trCheck(trRow($cid)['occurrence_id']==$new,'Nova edição independente');
    $session=(string)Db::value('SELECT session_id FROM `user` WHERE id=?',[$uid]);
    $boot=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','existing_user_id'=>$uid,'existing_session_id'=>$session,'user_session_id'=>$session]);
    trCheck($boot['treasure_event']['id']===$boot['character']['treasure_event_id'],'Login real, ID e objeto');
    // Expiração pelo relógio, com célula bónus aberta e recolha tardia.
    T::next($cid);T::free($cid);$p=T::state($cid);
    $bonusKey=null;foreach($p['levels'][1]['hidden'] as $key=>$cell)if($cell['type']===2){$bonusKey=$key;break;}
    trCheck($bonusKey!==null,'Célula bónus para expiração');[$bx,$by]=array_map('intval',explode('_',$bonusKey));T::open($cid,1,$bx,$by,false);
    Db::exec('UPDATE event_treasure_occurrences SET ts_end=? WHERE id=?',[GameTime::now()-1,$new]);
    trReject(fn()=>T::open($cid,1,0,0,true),'errInvalidFunction');
    trCheck(T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event']['status']===3,'Expiração por GameTime mantém bónus');
    T::collect($cid,1,$bx,$by);trCheck(!isset(T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event']),'Resgate tardio fecha edição expirada');
    T::schedule('ghost_treasure',GameTime::now()+100,GameTime::now()+200);
    trReject(fn()=>T::assign($cid,'ghost_treasure'),'errRequestInvalidParameter');
    trCheck(!isset(T::attach(['character'=>['id'=>$cid]],$cid)['treasure_event']),'Evento futuro não oferece convite');
    echo "PASS integração MySQL: 20 quintais reais, pistas, pás/cooldown, premium, inventário, pet, modificação, cupões, reset, cancelamento, nova edição, login/hub; rollback.\n";
} catch(Throwable $e){fwrite(STDERR,'FALHOU: '.$e->getMessage()."\n");exit(1);}
finally {if($pdo&&$pdo->inTransaction())$pdo->rollBack();}
