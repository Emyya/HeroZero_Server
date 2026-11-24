<?php
declare(strict_types=1);
// Datas offline + integração MySQL com rollback. Não altera o relógio persistente.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, Tournament, GameTime, Live, Character};
function checkCalendar(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "OK: $label\n";}
function stamp(string $date): int {return (new DateTimeImmutable($date,Live::gameTimezone()))->getTimestamp();}
foreach ([1=>0,2=>3,3=>2] as $type=>$day) {
    foreach (['2026-10-04 20:59:59','2026-10-04 21:00:00','2026-12-10 05:55:00','2027-03-25 22:00:00','2026-10-22 22:00:00'] as $date) {
        $now=stamp($date);$p=Tournament::period($type,$now);
        $start=(new DateTimeImmutable('@'.$p['ts_start']))->setTimezone(Live::gameTimezone());
        $end=(new DateTimeImmutable('@'.$p['ts_end']))->setTimezone(Live::gameTimezone());
        checkCalendar($p['ts_start']<=$now && $p['ts_end']>$now && (int)$end->format('w')===$day && $end->format('H:i:s')==='21:00:00' && $start->format('H:i:s')==='21:00:00',"período tipo $type em $date");
    }
}
checkCalendar(Tournament::period(1,stamp('2027-03-25 22:00:00'))['ts_end']-Tournament::period(1,stamp('2027-03-25 22:00:00'))['ts_start']===167*3600,'mudança para verão mantém 21:00 (167h)');
checkCalendar(Tournament::period(1,stamp('2026-10-22 22:00:00'))['ts_end']-Tournament::period(1,stamp('2026-10-22 22:00:00'))['ts_start']===169*3600,'mudança para inverno mantém 21:00 (169h)');
if (($argv[1]??'')==='--offline') {echo "PASS: calendário offline.\n";exit;}
$name=$argv[1]??null;
$hero=$name===null?Db::row('SELECT c.id,c.user_id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.id=(SELECT MIN(c2.id) FROM `character` c2 WHERE c2.user_id=c.user_id) ORDER BY c.id LIMIT 1'):Db::row('SELECT id,user_id FROM `character` WHERE name=?',[$name]);
if(!$hero)throw new RuntimeException('Herói não encontrado.');
$pdo=Db::pdo();$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
$offset=new ReflectionProperty(GameTime::class,'offset');$savedOffset=$offset->getValue();
$setTime=static function(int $now)use($offset):void{$offset->setValue(GameTime::offset()+$now-GameTime::now());};
$pdo->beginTransaction();
try {
    $cid=(int)$hero['id'];$uid=(int)$hero['user_id'];
    Db::exec('UPDATE tournaments SET status=4 WHERE status IN (1,2)');
    Db::exec('UPDATE tournament_rewards SET claimed=1 WHERE character_id=?',[$cid]);
    Db::exec('UPDATE `character` SET pending_tournament_rewards=0 WHERE id=?',[$cid]);
    // Dois ciclos antigos ativos: só uma base deve ser transportada e nenhum paga.
    Db::exec('INSERT INTO tournaments (week,ts_start,ts_end,status) VALUES (4,?,?,1)',[stamp('2031-12-01 02:32:36'),stamp('2031-12-08 02:32:36')]);
    $legacy=(int)$pdo->lastInsertId();
    Db::exec('INSERT INTO tournament_snapshots (tournament_id,character_id,xp_start,stat_base_start) VALUES (?,?,123,456)',[$legacy,$cid]);
    Db::exec('INSERT INTO tournaments (week,ts_start,ts_end,status) VALUES (4,?,?,1)',[stamp('2031-12-01 00:00:00'),stamp('2031-12-08 00:00:00')]);
    $now=stamp('2031-12-04 10:00:00');$setTime($now);
    $cycles=Tournament::synchronize();
    // O lock deve impedir outro PDO de avançar o calendário em paralelo.
    $cfg=config('herozero.db');
    $second=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$cfg['host'],$cfg['port'],$cfg['name'],$cfg['charset']),$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $second->exec('SET SESSION innodb_lock_wait_timeout=1');$second->beginTransaction();
    try { $second->query('SELECT id FROM tournament_calendar_lock WHERE id=1 FOR UPDATE'); throw new RuntimeException('Lock não bloqueou segunda ligação.'); }
    catch (PDOException $e) { checkCalendar((int)($e->errorInfo[1]??0)===1205,'lock bloqueia calendário concorrente'); }
    finally { $second->rollBack(); }
    checkCalendar(count($cycles)===3 && (int)Db::value('SELECT COUNT(*) FROM tournaments WHERE status=1')===3,'um ciclo ativo por tipo');
    checkCalendar((int)Db::value('SELECT status FROM tournaments WHERE id=?',[$legacy])===4 && (int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE tournament_id=?',[$legacy])===0,'cadeias antigas canceladas sem prémios adicionais');
    foreach($cycles as $type=>$cycle) checkCalendar((int)Db::value('SELECT xp_start FROM tournament_snapshots WHERE tournament_id=? AND character_id=?',[$cycle['id'],$cid])===123,"base preservada tipo $type");
    $ids=array_column($cycles,'id');
    checkCalendar(array_column(Tournament::synchronize(),'id')===$ids,'sincronização repetida não duplica ciclos');
    $times=Tournament::timestamps();
    checkCalendar(Live::timeKeys([])['tournament_end_timestamps']===$times['tournament_end_timestamps'],'resposta parcial tem datas por tipo');
    $bootHandler=require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
    $session=(string)Db::value('SELECT session_id FROM `user` WHERE id=?',[$uid]);
    $boot=$bootHandler(['user_id'=>$uid,'existing_user_id'=>$uid,'existing_session_id'=>$session,'user_session_id'=>$session]);
    checkCalendar($boot['tournament_end_timestamps']===$times['tournament_end_timestamps'] && $boot['global_tournament_end_timestamp']===$times['global_tournament_end_timestamp'],'boot real com calendários separados');
    $state=Live::accountState($uid);
    checkCalendar($state['tournament_end_timestamps']===$times['tournament_end_timestamps'],'estado normal com calendários separados');

    foreach([1,2,3] as $type) {
        $lb=Tournament::leaderboard($type,$cid,null,0);
        checkCalendar($lb['tournament_end_timestamps']===$times['tournament_end_timestamps'] && $lb['global_tournament_end_timestamp']===$cycles[3]['ts_end'],"ranking usa calendário real tipo $type");
    }
    // Processa os três limites, um de cada vez, mantendo os outros IDs.
    foreach([1,3,2] as $type) {
        $old=$cycles;$setTime((int)$old[$type]['ts_end']);$cycles=Tournament::synchronize();
        checkCalendar($cycles[$type]['id']!==$old[$type]['id'],"novo ciclo no limite tipo $type");
        foreach([1,2,3] as $other)if($other!==$type)checkCalendar($cycles[$other]['id']===$old[$other]['id'],"limite $type preserva ciclo $other");
        $row=Db::row('SELECT rewards FROM tournament_rewards WHERE tournament_id=? AND character_id=?',[$old[$type]['id'],$cid]);
        $rewards=json_decode($row['rewards'],true);
        checkCalendar(array_keys($rewards)===[Tournament::rewardKey($type)],"prémio contém apenas torneio $type");
        $count=(int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE tournament_id=?',[$old[$type]['id']]);
        Tournament::synchronize();
        checkCalendar((int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE tournament_id=?',[$old[$type]['id']])===$count,"encerramento $type idempotente");
    }
    $get=require dirname(__DIR__,2).'/server-laravel/actions/getTournamentRewards.php';
    $claim=require dirname(__DIR__,2).'/server-laravel/actions/claimTournamentRewards.php';
    foreach([1,3,2] as $type) {
        $payload=$get(['user_id'=>$uid]);$rewards=json_decode($payload['tournament_rewards']['rewards'],true);
        checkCalendar(array_values(array_diff(array_keys($rewards),['season_reward']))===[Tournament::rewardKey($type)],"janela separada/resgate tipo $type");
        $claim(['user_id'=>$uid]);
    }
    checkCalendar((int)Db::value('SELECT pending_tournament_rewards FROM `character` WHERE id=?',[$cid])===0,'todos os prémios separados resgatados');
    // Um salto não inventa prémios por semanas sem snapshots.
    $before=(int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE character_id=?',[$cid]);
    $setTime(stamp('2032-01-05 10:00:00'));$cycles=Tournament::synchronize();
    checkCalendar((int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE character_id=?',[$cid])===$before+3,'salto temporal encerra só ciclos existentes');
    foreach($cycles as $type=>$cycle)checkCalendar($cycle['ts_start']<=GameTime::now() && $cycle['ts_end']>GameTime::now(),"salto alcança período atual tipo $type");
    checkCalendar((int)Db::value('SELECT COUNT(*) FROM tournaments WHERE status=1')===3,'continua sem cadeias duplicadas');
    try { Db::exec('INSERT INTO tournaments (type,week,ts_start,ts_end,status) VALUES (1,?,?,?,1)',[$cycles[1]['week'],$cycles[1]['ts_start'],$cycles[1]['ts_end']]); throw new RuntimeException('Índice permitiu ciclo duplicado.'); }
    catch(PDOException $e){checkCalendar((int)($e->errorInfo[1]??0)===1062,'índice rejeita período duplicado');}
    $countBefore=(int)Db::value('SELECT COUNT(*) FROM tournament_rewards');
    $idsBefore=array_column($cycles,'id');$clockBefore=GameTime::now();
    $setTime(max(array_column($cycles,'ts_end')));
    try { Tournament::rewardTransaction(0,static function():void {Tournament::synchronize();throw new RuntimeException('rollback de fecho');}); }
    catch(RuntimeException $e){checkCalendar($e->getMessage()==='rollback de fecho','falha propagada no fecho');}
    $setTime($clockBefore);
    checkCalendar((int)Db::value('SELECT COUNT(*) FROM tournament_rewards')===$countBefore && array_column(Tournament::synchronize(),'id')===$idsBefore,'falha reverte prémios, estados e novos ciclos');

} finally {
    if($pdo->inTransaction())$pdo->rollBack();
    $offset->setValue($savedOffset);
}
echo "PASS: integração MySQL revertida; relógio persistente intacto.\n";
