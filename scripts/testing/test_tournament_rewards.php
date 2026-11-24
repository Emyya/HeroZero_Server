<?php
declare(strict_types=1);
// Integração MySQL: herói opcional; todas as alterações são revertidas.
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, Character, GameError, Tournament, Season, GameTime};
function checkReward(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: $label\n";
}
$name = $argv[1] ?? null;
$hero = $name === null ? Db::row('SELECT c.id, c.user_id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.id=(SELECT MIN(c2.id) FROM `character` c2 WHERE c2.user_id=c.user_id) ORDER BY c.id LIMIT 1')
    : Db::row('SELECT id, user_id FROM `character` WHERE name = ?', [$name]);
if (!$hero) throw new RuntimeException('Herói não encontrado.');
$tid = (int)Db::value('SELECT id FROM tournaments ORDER BY id DESC LIMIT 1');
if (!$tid) throw new RuntimeException('Sem torneio existente para o teste.');
$pdo = Db::pdo();
$connection = Illuminate\Support\Facades\DB::connection();
$connection->setPdo($pdo); $connection->setReadPdo($pdo);
$pdo->beginTransaction();
try {
    $cid = (int)$hero['id']; $uid = (int)$hero['user_id'];
    Db::exec('UPDATE tournament_rewards SET claimed = 1 WHERE character_id = ?', [$cid]);
    Db::exec('UPDATE `character` SET pending_tournament_rewards = 1 WHERE id = ?', [$cid]);
    $local = [1 => ['rank'=>1,'value'=>123,'donuts'=>75,'stat_points'=>10],
        2 => ['rank'=>1,'value'=>45,'stat_points'=>40],
        4 => ['rank'=>1,'value'=>14,'xp_percentage'=>50]];
    Db::exec('INSERT INTO tournament_rewards (tournament_id, character_id, week, rewards, claimed) VALUES (?, ?, 202640, ?, 0)', [$tid,$cid,json_encode($local)]);
    $rid = (int)$pdo->lastInsertId();
    $get = require dirname(__DIR__,2).'/server-laravel/actions/getTournamentRewards.php';
    $claim = require dirname(__DIR__,2).'/server-laravel/actions/claimTournamentRewards.php';
    $params = ['user_id'=>$uid];
    Season::ensureProgress($cid);
    Db::exec('UPDATE season_progress SET status=2, season_points=0, ts_start=?, ts_end=? WHERE character_id=?',[GameTime::now()-86400,GameTime::now()+60*86400,$cid]);
    $first = $get($params); $rewards = json_decode($first['tournament_rewards']['rewards'],true);
    checkReward($rewards[1]['reward']['type']===1 && $rewards[1]['reward']['amount']===75 && $rewards[1]['reward']['type_2']===3 && $rewards[1]['reward']['amount_2']===10, 'contrato donuts + atributos');
    checkReward($rewards[2]['reward']['type']===3 && $rewards[2]['reward']['amount']===40, 'contrato atributos');
    $xp = $rewards[4]['xp_amount'];
    checkReward($xp>0 && $rewards[4]['reward']['type']===4 && $rewards[4]['reward']['amount']===$xp, 'XP absoluto apresentado');
    checkReward($rewards['season_reward']['amount']===60, 'pré-visualização 30 XP + 30 habilidades para o passe');
    checkReward($get($params)===$first, 'reabrir preserva prémios');
    $shifted = Tournament::displayRewards($rewards, 2);
    checkReward($shifted[4]['xp_amount']===$xp, 'nível diferente não altera XP já apresentado');
    $before = Db::row('SELECT xp, stat_points_available FROM `character` WHERE id = ?',[$cid]);
    $coins = (int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid]);
    $response = $claim($params);
    checkReward((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===$coins+75, 'donuts creditados');
    checkReward((int)Db::value('SELECT stat_points_available FROM `character` WHERE id = ?',[$cid])===(int)$before['stat_points_available']+50, 'atributos dos dois torneios creditados');
    checkReward((int)Db::value('SELECT xp FROM `character` WHERE id = ?',[$cid])===(int)$before['xp']+$xp, 'XP creditado igual ao mostrado');
    checkReward((int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid])===60 && (int)$response['season_progress']['season_points']===60, 'pontos creditados e progresso atualizado na resposta');
    checkReward((int)$response['character']['pending_tournament_rewards']===0 && (int)Db::value('SELECT claimed FROM tournament_rewards WHERE id = ?',[$rid])===1, 'resgate marcado e resposta atualizada');
    try { $claim($params); throw new RuntimeException('Segundo resgate aceite.'); }
    catch (GameError $e) { echo "OK: segundo resgate rejeitado\n"; }
    checkReward((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===$coins+75 && (int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid])===60, 'sem duplicação de prémios ou pontos do passe');
    $progress=Season::ensureProgress($cid);$end=GameTime::now()+3600;
    foreach([1,2] as $type) foreach([0=>0,1=>30,3=>30,4=>20,10=>20,11=>10,100=>10,101=>0] as $rank=>$expected) {
        $award=Tournament::seasonReward([$type=>['rank'=>$rank]],$progress,$end);
        checkReward($award['amount']===$expected, "passe tipo $type posição $rank");
    }
    checkReward(Tournament::seasonReward([4=>['rank'=>1]],$progress,$end)['amount']===0, 'PvP sem pontos de passe');
    checkReward(Tournament::seasonReward([1=>['rank'=>1]],$progress,(int)$progress['ts_end'])['amount']===0, 'torneio que termina após temporada não concede pontos');
    $inactive=$progress;$inactive['status']=1;
    checkReward(Tournament::seasonReward([1=>['rank'=>1]],$inactive,$end)['amount']===0, 'temporada inativa não concede pontos');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
echo "PASS: integração MySQL com rollback.\n";
