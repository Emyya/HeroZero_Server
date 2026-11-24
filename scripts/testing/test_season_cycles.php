<?php
declare(strict_types=1);
// Integração MySQL com rollback; herói opcional. Não altera o ficheiro do relógio.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db,Season,GameTime,Live,GameError};
function cycleCheck(bool $ok,string $label):void {if(!$ok) throw new RuntimeException($label);echo "OK: $label\n";}
$pdo=Db::pdo();
$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
$hero=isset($argv[1]) ? Db::row('SELECT id,user_id FROM `character` WHERE name=?',[$argv[1]]) : Db::row('SELECT c.id,c.user_id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.id=(SELECT MIN(c2.id) FROM `character` c2 WHERE c2.user_id=c.user_id) ORDER BY c.id LIMIT 1');
if(!$hero) throw new RuntimeException('Herói não encontrado.');
$offset=new ReflectionProperty(GameTime::class,'offset');$oldOffset=$offset->getValue();
$epoch=new ReflectionProperty(GameTime::class,'epoch');$oldEpoch=$epoch->getValue();
$uptime=new ReflectionProperty(GameTime::class,'cachedUptime');$oldUptime=$uptime->getValue();
$cachedAt=new ReflectionProperty(GameTime::class,'cachedAt');$oldCachedAt=$cachedAt->getValue();
$setTime=static function(int $now)use($offset,$epoch,$uptime,$cachedAt):void {$offset->setValue(null,0);$epoch->setValue(null,$now);$uptime->setValue(null,0);$cachedAt->setValue(null,time());};
$pdo->beginTransaction();
try {
 $cid=(int)$hero['id'];$uid=(int)$hero['user_id'];$end=2000000000;$duration=30*86400;
 Db::exec('UPDATE season_calendar SET ts_anchor=? WHERE id=1',[$end-$duration]);
 $setTime($end-1);Season::ensureProgress($cid);
 Db::exec('UPDATE season_progress SET ts_start=?,ts_end=?,identifier=\'gods_1\',season_id=1,status=2,season_points=1000,premium_unlocked=1,restarted=1,claimed_rewards=?,unlocked_rewards=? WHERE character_id=?',[$end-$duration,$end,'[1,2]','[3]',$cid]);
 cycleCheck((int)Season::ensureProgress($cid)['season_points']===1000,'antes do fim preserva progresso');
 $setTime($end);$p=Season::ensureProgress($cid);
 cycleCheck($p['identifier']==='gods_2' && (int)$p['season_id']===2,'primeiro novo ciclo usa gods_2');
 cycleCheck(str_contains(Season::rewardCatalog('gods_2')[0]['rewards'],'boots_season_gods_2'),'recompensas usam identificador do novo tema');
 cycleCheck((int)$p['ts_start']===$end && (int)$p['ts_end']===$end+$duration,'renova no instante exato do fim');
 foreach(['season_points','premium_unlocked','restarted'] as $field) cycleCheck((int)$p[$field]===0,"limpa $field");
 cycleCheck($p['claimed_rewards']==='[]' && $p['unlocked_rewards']==='[]','limpa resgates e desbloqueios');
 cycleCheck(Season::ensureProgress($cid)===$p,'renovação idempotente');
 try {Season::claimReward($cid,1,false);throw new RuntimeException('Resgate antigo aceite.');} catch(GameError $e) {cycleCheck(str_contains($e->getMessage(),'NotFound'),'resgate antigo não usa pontos do ciclo anterior');}
 $setTime($end+3*$duration+1234);$p=Season::ensureProgress($cid);
 cycleCheck($p['identifier']==='beetle','salto avança todos os temas: gods_2, gods_3, fairytale_1, beetle');
 cycleCheck((int)$p['ts_start']===$end+3*$duration && (int)$p['ts_end']===$end+4*$duration,'salto de vários ciclos preserva limites e tempo restante');
 $state=Live::accountState($uid);
 cycleCheck((int)$state['season_progress']['ts_end']===(int)$p['ts_end'],'estado normal usa novo ciclo');
 cycleCheck((int)$state['character']['active_season_progress_id']===(int)$p['id'],'referência do progresso consistente');
 cycleCheck(count($state['season_rewards'])===count(Season::rewardCatalog('beetle')),'coleção completa no novo ciclo');
 $boot=require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
 $user=Db::row('SELECT * FROM `user` WHERE id=?',[$uid]);
 $response=$boot(['user_id'=>$uid,'existing_user_id'=>$uid,'existing_session_id'=>$user['session_id'] ?? $user['user_session_id'] ?? '']);
 cycleCheck((int)$response['season_progress']['ts_end']===(int)$p['ts_end'],'login usa novo ciclo');
 $catalogs=Season::constantsData();cycleCheck(count($catalogs)===80,'80 catálogos disponíveis');
 $ids=[];
 foreach($catalogs as $identifier=>$catalog) {
  $rewards=Season::rewardCatalog($identifier);
  cycleCheck(count($rewards)===2*count($catalog['season_points']),"catálogo $identifier completo");
  foreach($rewards as $reward) {if(isset($ids[$reward['id']])) throw new RuntimeException('ID repetido entre temas');$ids[$reward['id']]=true;}
 }
 $setTime($end+80*$duration);$wrap=Season::ensureProgress($cid);
 cycleCheck($wrap['identifier']==='gods_2','rotação completa repete ordem');
 // Reproduz as três janelas divergentes observadas nos HARs, sem gravar dados.
 $setTime(1802675877);
 Db::exec('UPDATE season_calendar SET ts_anchor=? WHERE id=1',[1793394786]);
 $fixtures = [
  ['sonnet46.har', 70, 'fairytale_1', 1801191989, 1803783989],
  ['laliene.har', 800, 'gods_3', 1802066393, 1804658393],
  ['skel.har', 25, 'gods_3', 1801373579, 1803965579],
 ];
 $windows=[];
 $testHeroes=Db::column('SELECT id FROM `character` ORDER BY id LIMIT 3');
 cycleCheck(count($testHeroes)===3,'três personagens disponíveis para testar sincronização');
 $fixtureIndex=0;
 foreach ($fixtures as [$file,$points,$identifier,$start,$finish]) {
  $fixtureCid=(int)$testHeroes[$fixtureIndex++];
  Season::ensureProgress($fixtureCid);
  Db::exec('UPDATE season_progress SET identifier=?,ts_start=?,ts_end=?,season_points=?,claimed_rewards=?,unlocked_rewards=? WHERE character_id=?',
   [$identifier,$start,$finish,$points,'[3001]','[3003]',$fixtureCid]);
  $p=Season::ensureProgress($fixtureCid);
  $windows[]=[$p['identifier'],(int)$p['ts_start'],(int)$p['ts_end']];
  cycleCheck((int)$p['season_points']===($identifier==='fairytale_1' ? $points : 0),"$file preserva apenas progresso do tema actual");
 }
 cycleCheck($windows[0]===$windows[1] && $windows[1]===$windows[2], 'três HARs convergem para tema e datas iguais');
 foreach(array_reverse($testHeroes) as $testCid) {
  $again=Season::ensureProgress((int)$testCid);
  cycleCheck([$again['identifier'],(int)$again['ts_start'],(int)$again['ts_end']]===$windows[0], 'ordem inversa dos logins mantém calendário');
 }
 // Criação tardia também entra no ciclo global, sem ganhar mais 30 dias.
 Db::exec('DELETE FROM season_progress WHERE character_id=?',[$cid]);
 $fresh=Season::ensureProgress($cid);
 cycleCheck([$fresh['identifier'],(int)$fresh['ts_start'],(int)$fresh['ts_end']]===$windows[0], 'conta sem progresso entra no ciclo global');
 $p=Season::ensureProgress($cid);
 $setTime((int)$p['ts_end']+2*$duration+123);
 $late=Season::ensureProgress($cid);
 cycleCheck($late['identifier']===Season::currentCycle()['identifier'] && (int)$late['ts_start']===Season::currentCycle()['ts_start'], 'conta inactiva salta para o ciclo global');
 echo "PASS: ciclos de temporada (MySQL, rollback)\n";
} finally {
 if($pdo->inTransaction())$pdo->rollBack();
 $offset->setValue(null,$oldOffset);$epoch->setValue(null,$oldEpoch);$uptime->setValue(null,$oldUptime);$cachedAt->setValue(null,$oldCachedAt);
}
