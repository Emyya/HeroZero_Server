<?php
declare(strict_types=1);
require __DIR__.'/repair_training_energy.php';
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,Character,GameTime,Live,Router,BonusEvents};
function teCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$pdo=null;$failed=false;
try {
    $now=10000;
    $base=['training_energy'=>92,'max_training_energy'=>100,'ts_last_training_energy_change'=>$now];
    teCheck(trainingEnergyRepairPlan($base,$now)['training_energy']===22,'92/100 → 22/30 preserva oito gastos');
    teCheck(trainingEnergyRepairPlan($base,$now+125)['training_energy']===24,'Regeneração antes da reparação');
    teCheck(trainingEnergyRepairPlan($base,$now+125)['ts_last_training_energy_change']===$now+120,'Preserva cinco segundos de regeneração');
    teCheck(trainingEnergyRepairPlan($base,$now+600)['training_energy']===30,'Barra regenerada fica cheia');
    teCheck(trainingEnergyRepairPlan(array_replace($base,['training_energy'=>0]),$now)['training_energy']===0,'Energia nunca negativa');
    teCheck(trainingEnergyRepairPlan(array_replace($base,['training_energy'=>200]),$now,2)['training_energy']===60,'Respeita bónus de energia');
    $normal=array_replace($base,['training_energy'=>27,'max_training_energy'=>30]);teCheck(trainingEnergyRepairPlan($normal,$now)===$normal,'Contas corretas preservadas/idempotência');
    echo "PASS offline: défice, regeneração, fração de minuto, limite zero, bónus e idempotência.\n";
    if(!in_array('--mysql',$argv,true))exit(0);
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo();$hero=null;foreach($argv as $a)if(str_starts_with($a,'--hero='))$hero=substr($a,7);
    $source=$hero?Db::row('SELECT u.* FROM `user` u JOIN `character` c ON c.user_id=u.id WHERE c.name=?',[$hero]):Db::row('SELECT * FROM `user` ORDER BY id LIMIT 1');
    teCheck($source!==null,'Conta base não encontrada');
    echo "MySQL: cria conta/herói temporários e simula treinos e reparação; tudo revertido por rollback.\n";
    $pdo->beginTransaction();unset($source['id']);$source['email']='training_test_'.bin2hex(random_bytes(8)).'@example.invalid';$source['session_id']=bin2hex(random_bytes(16));
    Db::exec('INSERT INTO `user` (`'.implode('`,`',array_keys($source)).'`) VALUES ('.implode(',',array_fill(0,count($source),'?')).')',array_values($source));$uid=(int)$pdo->lastInsertId();
    $char=Character::createNew($uid,'TreinoTeste'.bin2hex(random_bytes(3)));$cid=$char->id();
    $row=Db::row('SELECT * FROM `character` WHERE id=?',[$cid]);teCheck((int)$row['training_energy']===30&&(int)$row['max_training_energy']===30,'Criação real nasce 30/30');
    teCheck((int)$row['quest_energy']===100&&(int)$row['max_quest_energy']===100,'Energia das missões mantém 100/100');
    $state=Live::accountState($uid);teCheck($state['character']['max_training_energy']===30,'Estado normal com base 30');
    $boot=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','user_id'=>$uid,'user_session_id'=>$source['session_id']]);teCheck($boot['character']['max_training_energy']===30,'Login com base 30');
    Db::exec('UPDATE `character` SET level=10,xp=?,training_count=10,max_training_count=10,ts_last_training=? WHERE id=?',[Character::xpForLevel(10),GameTime::now(),$cid]);
    $overlay=Character::load($cid)->overlayCharacter(Live::template('autoLoginUser')['character']);$pool=json_decode($overlay['training_pool'],true);teCheck(!empty($pool),'Oferta de treino');
    $started=Router::dispatch('startTraining',['action'=>'startTraining','user_id'=>$uid,'training_id'=>$pool[0]]);
    $max=30*BonusEvents::factor(10);teCheck($started['character']['max_training_energy']===$max&&(int)$started['character']['training_energy']===$max,'Início mantém máximo correto com bónus');
    $trainingId=(int)Db::value('SELECT id FROM `training` WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
    $seenCosts=[];
    for($round=0;$round<100;$round++){
        Db::exec('UPDATE `training` SET used_resources=? WHERE id=?',[$round,$trainingId]);
        foreach(Character::load($cid)->trainingQuestsData() as $offer){
            $cost=(int)$offer['energy_cost'];teCheck($cost>=1&&$cost<=9,'Custo de tarefa fora de 1–9');
            $seenCosts[$cost]=true;
        }
        if(isset($seenCosts[1],$seenCosts[9]))break;
    }
    teCheck(isset($seenCosts[1],$seenCosts[9]),'Gerador não produziu custos 1 e 9');
    Db::exec('UPDATE `training` SET used_resources=0 WHERE id=?',[$trainingId]);
    $quests=Character::load($cid)->trainingQuestsData();$q=$quests[0];
    $exercise=Router::dispatch('startTrainingQuest',['action'=>'startTrainingQuest','user_id'=>$uid,'training_quest_id'=>$q['id']]);
    teCheck($exercise['character']['max_training_energy']===$max&&(int)$exercise['character']['training_energy']===$max-(int)$q['energy_cost'],'Exercício debita energia mantendo máximo 30');
    Db::exec('UPDATE `character` SET training_energy=92,max_training_energy=100,ts_last_training_energy_change=? WHERE id=?',[GameTime::now(),$cid]);
    $old=Db::row('SELECT * FROM `character` WHERE id=?',[$cid]);$plan=trainingEnergyRepairPlan($old,GameTime::now());
    Db::exec('UPDATE `character` SET training_energy=?,max_training_energy=?,ts_last_training_energy_change=? WHERE id=?',[$plan['training_energy'],$plan['max_training_energy'],$plan['ts_last_training_energy_change'],$cid]);
    $after=Db::row('SELECT * FROM `character` WHERE id=?',[$cid]);
    foreach($old as $key=>$value)if(!in_array($key,['training_energy','max_training_energy','ts_last_training_energy_change'],true))teCheck($after[$key]===$value,'Reparação preserva '.$key);
    teCheck((int)$after['training_energy']===22&&(int)$after['max_training_energy']===30,'Reparação MySQL preserva gasto');
    echo "PASS MySQL: criação, missões, login, estado, início/exercício e reparação sem alterar os restantes campos; rollback.\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");}
finally{if($pdo&&$pdo->inTransaction())$pdo->rollBack();}
if($failed)exit(1);
