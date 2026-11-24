<?php
declare(strict_types=1);
// Integração real; alterações apenas dentro de transação com rollback.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventQuests,Character,GameTime,GameError,Live,Router,Worldboss,Season};
function eqCheck(bool $ok,string $text):void {if(!$ok)throw new RuntimeException($text);echo "OK: $text\n";}
function eqReject(callable $fn,string $code):void {try{$fn();}catch(GameError $e){eqCheck($e->getMessage()===$code,$code);return;}throw new RuntimeException('Aceitou operação que devia falhar: '.$code);}
function eqScenario(PDO $pdo,string $key,int $cid,callable $fn):void {
    $pdo->exec('SAVEPOINT event_integration');
    try{EventQuests::schedule($key,GameTime::now()-1,GameTime::now()+86400);EventQuests::assign($cid,$key);$fn();}
    finally{$pdo->exec('ROLLBACK TO SAVEPOINT event_integration');$pdo->exec('RELEASE SAVEPOINT event_integration');}
}
function eqState(int $cid):array {return EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest'];}
function eqComplete(int $cid,string $key):void {
    foreach(EventQuests::definition($key)['objectives'] as $oid=>$o) {
        EventQuests::record($cid,(int)$o['type'],(int)$o['value'],str_replace('*','test',$o['reference']));
        EventQuests::claimObjective($cid,$oid,true);
    }
}
$pdo=null;
try {
    $name='';foreach(array_slice($argv,1) as $a)if(str_starts_with($a,'--hero='))$name=substr($a,7);else throw new InvalidArgumentException('Uso: test_event_quests_integration.php --hero=Nome');
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo();$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $heroes=$name!==''?Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]):Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
    if(count($heroes)!==1)throw new RuntimeException('Herói não encontrado ou ambíguo.');$cid=(int)$heroes[0]['id'];$uid=(int)$heroes[0]['user_id'];
    echo "Herói: {$heroes[0]['name']}. Todos os dados preparados e sessões de login serão revertidos.\n";
    $pdo->beginTransaction();
    Db::exec('UPDATE event_quest_occurrences SET enabled=0');Db::exec('UPDATE event_quest_progress SET claimed=1 WHERE character_id=?',[$cid]);
    Db::exec('UPDATE `character` SET level=100,xp=?,event_quest_id=0,active_quest_id=0,active_worldboss_attack_id=0,worldboss_event_id=0,active_duel_id=0,quest_energy=100,training_count=100,ts_last_wash_item=0 WHERE id=?',[Character::xpForLevel(100),$cid]);
    Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);
    $slots=[];foreach(range(1,18) as $i)$slots[]='bag_item'.$i.'_id=0';Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id=?',[$cid]);
    eqScenario($pdo,'server_launch_2026_cube',$cid,static function()use($cid,$uid):void {
        $oid='server_launch_2026_cube_duels_fought';$o=EventQuests::definition('server_launch_2026_cube')['objectives'][$oid];
        EventQuests::record($cid,EventQuests::DUEL,(int)$o['value']);$e=eqState($cid);$item=$e['objective1_reward_item_id'];
        $assign=[];foreach(range(1,18) as $i)$assign[]='bag_item'.$i.'_id='.$item;Db::exec('UPDATE inventory SET '.implode(',',$assign).' WHERE character_id=?',[$cid]);
        eqReject(fn()=>EventQuests::claimObjective($cid,$oid),'errInventoryNoEmptySlot');
        eqCheck(eqState($cid)['objective1_value']===$o['value'],'mochila cheia preserva prémio/progresso');
        Db::exec('UPDATE inventory SET bag_item1_id=0 WHERE character_id=?',[$cid]);
        EventQuests::claimObjective($cid,$oid);
        eqCheck((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id=?',[$cid])===$item,'equipamento entregue no espaço livre');
        $points=(int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid]);
        eqReject(fn()=>EventQuests::claimObjective($cid,$oid),'errFinishObjectiveAlreadyCollected');
        eqCheck((int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid])===$points,'retry não duplica pontos de temporada');
        // Resposta parcial real do Router, sem passar pelo handler de eventos.
        $data=Router::dispatch('getEventHubEntries',['action'=>'getEventHubEntries','user_id'=>$uid]);
        eqCheck($data['event_quest']['objective1_value']===$o['value']+1,'hub recebe estado atualizado');
        eqCheck(in_array('server_launch_2026_cube_eventQuest',array_column($data['event_hub_entries'],'identifier'),true),'hub usa identificador interpretado pelo cliente');
    });
    eqScenario($pdo,'easter',$cid,static function()use($cid):void {
        eqComplete($cid,'easter');$e=eqState($cid);$before=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
        EventQuests::claimFinal($cid,$e['reward_item1_id']);
        eqCheck((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$before+1,'pet final criado uma só vez');
        eqReject(fn()=>EventQuests::claimFinal($cid,$e['reward_item1_id']),'errClaimEventQuestRewardsNoEventQuest');
    });
    eqScenario($pdo,'birthday_event',$cid,static function()use($cid,$uid):void {
        eqComplete($cid,'birthday_event');$e=eqState($cid);$before=(int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id=?',[$uid]);
        EventQuests::claimFinal($cid,$e['reward_item3_id']);
        eqCheck((int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id=?',[$uid])===$before+1,'energético final entregue como cupão');
    });
    eqScenario($pdo,'sci_fi_week',$cid,static function()use($cid):void {
        EventQuests::record($cid,EventQuests::WORLDBOSS,2,'sci_fi_week_stage2');
        EventQuests::claimObjective($cid,'sci_fi_week_worldboss_defeated');
        eqCheck((int)Db::value('SELECT COUNT(*) FROM event_quest_unlocks WHERE character_id=? AND optical_change_id=117',[$cid])===1,'aparência 117 desbloqueada');
        $data=EventQuests::attach(['character'=>['id'=>$cid],'current_optical_changes'=>['unlocked_options'=>'{}']],$cid);
        $u=json_decode($data['current_optical_changes']['unlocked_options'],true);eqCheck(in_array(117,array_merge(...array_values($u)),true),'aparência incluída no estado');
    });
    eqScenario($pdo,'halloween',$cid,static function()use($cid,$uid):void {
        $q=Db::row('SELECT * FROM quests WHERE character_id=? AND status=1 ORDER BY type,id LIMIT 1',[$cid]);
        if(!$q){Character::load($cid)->regenerateQuests();$q=Db::row('SELECT * FROM quests WHERE character_id=? AND status=1 ORDER BY type,id LIMIT 1',[$cid]);}
        eqCheck(str_contains($q['identifier'],'_halloween_'),'missões existentes recebem tema do objetivo');
        $occurrence=(int)Db::value('SELECT occurrence_id FROM event_quest_progress WHERE character_id=? AND claimed=0',[$cid]);
        $r=json_decode($q['rewards'],true);$r['event_item']='halloween_pumpkin';$r['event_quest_occurrence']=$occurrence;
        Db::exec('UPDATE quests SET rewards=? WHERE id=?',[json_encode($r),(int)$q['id']]);
        Character::load($cid)->startQuest((int)$q['id']);
        Db::exec('UPDATE quests SET status=3 WHERE id=?',[(int)$q['id']]);
        Character::load($cid)->claimQuestRewards();$e=eqState($cid);
        eqCheck($e['objective1_value']===1&&$e['objective2_value']===1,'missão real conta objetivo temático e objeto anunciado');
        eqReject(fn()=>Character::load($cid)->claimQuestRewards(),'errClaimQuestRewardsNoActiveQuest');
        EventQuests::collectQuestItem($cid,['event_item'=>'halloween_pumpkin','event_quest_occurrence'=>$occurrence+1]);
        eqCheck(eqState($cid)['objective2_value']===1,'objeto de outra ocorrência não conta');
        $live=Live::accountState($uid);eqCheck($live['event_quest']['id']===$live['character']['event_quest_id'],'accountState envia ID e objeto associados');
        $session=(string)Db::value('SELECT session_id FROM `user` WHERE id=?',[$uid]);
        $boot=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','existing_user_id'=>$uid,'existing_session_id'=>$session,'user_session_id'=>$session]);
        eqCheck($boot['event_quest']['id']===$boot['character']['event_quest_id'],'autoLoginUser envia estado real');
        // Expiração real, com objetivo pronto ainda resgatável.
        EventQuests::record($cid,EventQuests::ITEM,999,'halloween_bat');
        Db::exec('UPDATE event_quest_progress SET ts_end=? WHERE id=?',[GameTime::now()-1,$e['id']]);
        eqCheck(eqState($cid)['status']===3,'expiração de evento incompleto');
        EventQuests::claimObjective($cid,'halloween_eventitem_bat_collected',true);
        EventQuests::claimFinal($cid,0);
        $next=EventQuests::schedule('halloween',GameTime::now()+86400,GameTime::now()+172800);
        eqCheck($next>$occurrence,'nova edição tem ID distinto');
    });
    eqScenario($pdo,'carnival_event',$cid,static function()use($cid):void {
        Character::load($cid)->refreshShopItems(1,true);eqCheck(eqState($cid)['objective1_value']===1,'refresh real da loja conta uma vez');
        Db::exec('UPDATE training SET status=4 WHERE character_id=? AND status=2',[$cid]);
        $char=Character::load($cid);$training=$char->trainingsData([])[0];
        $before=(int)Db::value('SELECT training_count FROM `character` WHERE id=?',[$cid]);
        $char->startTraining((int)$training['id']);$cost=$before-(int)Db::value('SELECT training_count FROM `character` WHERE id=?',[$cid]);
        eqCheck(eqState($cid)['objective2_value']===$cost&&$cost>0,'treino real conta unidades motivacionais consumidas');
        eqReject(fn()=>Character::load($cid)->startTraining((int)$training['id']),'errStartTrainingAlreadyActive');
        eqCheck(eqState($cid)['objective2_value']===$cost,'treino repetido não duplica unidades');
    });
    eqScenario($pdo,'easter',$cid,static function()use($cid):void {
        $item=Character::load($cid)->createWorldbossRewardItem('mask_easter_bunny1',1,3);
        Db::exec('UPDATE inventory SET bag_item1_id=? WHERE character_id=?',[$item,$cid]);
        Character::load($cid)->washItem($item,false);
        eqCheck(eqState($cid)['objective4_value']===1,'lavagem real avança objetivo');
        eqReject(fn()=>Character::load($cid)->washItem($item,false),'errRemoveGameCurrencyNotEnough');
        eqCheck(eqState($cid)['objective4_value']===1,'lavagem recusada não conta');
    });
    $startDuel=require dirname(__DIR__,2).'/server-laravel/actions/startDuel.php';
    eqScenario($pdo,'carnival_event',$cid,static function()use($cid,$uid,$startDuel):void {
        $enemy=Db::row('SELECT id FROM `character` WHERE id>0 AND user_id<>0 AND id<>? ORDER BY level,id LIMIT 1',[$cid]);
        if(!$enemy)throw new RuntimeException('Sem oponente para testar duelo.');
        Db::exec('UPDATE `character` SET ts_last_duel=0,duel_stamina=60 WHERE id=?',[$cid]);
        $startDuel(['action'=>'startDuel','user_id'=>$uid,'character_id'=>(int)$enemy['id']]);
        Router::dispatch('checkForDuelComplete',['action'=>'checkForDuelComplete','user_id'=>$uid]);
        Router::dispatch('claimDuelRewards',['action'=>'claimDuelRewards','user_id'=>$uid]);
        // Carnaval não tem objetivo de duelo; teste garante não contar noutro tipo.
        eqCheck(eqState($cid)['objective1_value']===0,'duelo não é contado como renovação de loja');
    });
    eqScenario($pdo,'olympia_2016_rio',$cid,static function()use($cid,$uid,$startDuel):void {
        $enemy=Db::row('SELECT id FROM `character` WHERE id>0 AND user_id<>0 AND id<>? ORDER BY level,id LIMIT 1',[$cid]);
        Db::exec('UPDATE `character` SET ts_last_duel=0,duel_stamina=60 WHERE id=?',[$cid]);
        $startDuel(['action'=>'startDuel','user_id'=>$uid,'character_id'=>(int)$enemy['id']]);
        Router::dispatch('checkForDuelComplete',['action'=>'checkForDuelComplete','user_id'=>$uid]);
        Router::dispatch('claimDuelRewards',['action'=>'claimDuelRewards','user_id'=>$uid]);
        eqCheck(eqState($cid)['objective3_value']===1,'duelo real conta nas Olimpíadas; valor='.eqState($cid)['objective3_value']);
        Router::dispatch('claimDuelRewards',['action'=>'claimDuelRewards','user_id'=>$uid]);
        eqCheck(eqState($cid)['objective3_value']===1,'resgate repetido de duelo não duplica progresso');
    });
    eqScenario($pdo,'carnival_event',$cid,static function()use($cid):void {
        Db::exec('UPDATE `character` SET active_league_fight_id=0,active_dungeon_quest_id=0 WHERE id=?',[$cid]);
        Db::exec('UPDATE work SET status=2 WHERE character_id=? AND status=1',[$cid]);
        $boss=Worldboss::create('carnival_stage2',1,1,3600);
        for($i=0;$i<10;$i++) {
            Worldboss::action($cid,'startWorldbossAttack',$boss);
            Worldboss::action($cid,'instantFinishWorldbossAttack');
            Worldboss::action($cid,'finishWorldbossAttack',$boss);
            if((int)Db::value('SELECT npc_hitpoints_current FROM worldboss_event WHERE id=?',[$boss])===0)break;
        }
        eqCheck((int)Db::value('SELECT npc_hitpoints_current FROM worldboss_event WHERE id=?',[$boss])===0,'vilão temático derrotado');
        Worldboss::action($cid,'claimWorldbossEventRewards',$boss,1,true);
        eqCheck(eqState($cid)['objective6_value']===1,'resgate de vilão derrotado conta uma vez');
        eqReject(fn()=>Worldboss::action($cid,'claimWorldbossEventRewards',$boss,1,true),'errClaimWorldbossEventRewardsInvalidStatus');
        eqCheck(eqState($cid)['objective6_value']===1,'retry do vilão não duplica progresso');
    });
    $date=EventQuests::serverDate(GameTime::now()+86400);
    eqCheck((new DateTimeImmutable($date,new DateTimeZone('UTC')))->getTimestamp()-Live::gameTimestampOffset()===GameTime::now()+86400,'data do protocolo preserva timestamp no fuso do cliente');
    eqCheck($pdo->inTransaction(),'todos os fluxos mantêm transação exterior');
    echo "PASS: integração de inventário/pets/cupões/aparências, missões, treinos, loja, login, hub e expiração.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
finally {if($pdo&&$pdo->inTransaction())$pdo->rollBack();}
