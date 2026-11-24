<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{DailyEnergy,Db,Character,BonusEvents,GameTime,Live,Season,UserVouchers,GameError};
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR,$e->getMessage()."\n"); exit(1); });
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: {$message}\n"; }
function rejects(callable $fn,string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage()===$error,$error); return; }
    throw new RuntimeException('Esperado erro '.$error);
}
function clockAt(string $date): void {
    $ts = (new DateTimeImmutable($date,Live::gameTimezone()))->getTimestamp();
    (new ReflectionProperty(GameTime::class,'offset'))->setValue(null,$ts-GameTime::epoch()-GameTime::uptime());
}
foreach ([100=>[10,5,5,3],200=>[20,10,10,5],300=>[30,15,20,10]] as $threshold=>$values) {
    $catalog = DailyEnergy::catalog($threshold);
    check(count($catalog)===5 && array_column($catalog,'reward_type')===[3,1,4,5,9]
        && array_column(array_slice($catalog,1),'reward_value')===$values,'Cinco opções corretas no patamar '.$threshold);
}
$gap = Character::xpForLevel(101)-Character::xpForLevel(100);
check(DailyEnergy::xpReward(100,3)===(int)ceil($gap*.03),'XP usa diferença entre níveis, não XP em falta nem XP acumulado');
check(count(array_filter(HeroZero\Herobook::constants()['season_locations'][17],static fn($v)=>$v!==1))===0,'Local 17 anuncia um ponto em todos os temas');
if (!in_array('--db',$argv,true)) { echo "Offline concluído. Integração: --db [nome do herói]; usa rollback.\n"; exit; }
$name = $argv[2] ?? null;
$cid = (int)($name ? Db::value('SELECT id FROM `character` WHERE name = ? AND user_id > 0',[$name])
    : Db::value('SELECT id FROM `character` WHERE user_id > 0 ORDER BY id LIMIT 1'));
if (!$cid) throw new RuntimeException('Herói não encontrado.');
$pdo = Db::pdo();
Illuminate\Support\Facades\DB::connection()->setPdo($pdo);
Illuminate\Support\Facades\DB::connection()->setReadPdo($pdo);
$pdo->beginTransaction();
try {
    clockAt('2030-07-01 12:00:00');
    Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE',[$cid]);
    Db::exec('DELETE FROM daily_energy_rewards WHERE character_id = ?',[$cid]);
    Db::exec('DELETE FROM daily_energy_progress WHERE character_id = ?',[$cid]);
    Db::exec('UPDATE `character` SET level = 100, xp = ?, guild_id = 0, active_quest_id = 0 WHERE id = ?', [Character::xpForLevel(100),$cid]);
    $uid = Character::load($cid)->userId();
    $data = DailyEnergy::attach([],$cid);
    check($data['daily_bonus_lookup']['quest_energy_spent']===0 && count($data['daily_bonus_rewards'])===3,'Dia começa a zero com três prémios bloqueados');
    $again = DailyEnergy::attach([],$cid);
    check($data['daily_bonus_reward_data']==$again['daily_bonus_reward_data']
        && $data['daily_bonus_rewards']===$again['daily_bonus_rewards'],'Sorteio e IDs estáveis nas consultas');
    $id = (int)Db::value('SELECT id FROM daily_energy_rewards WHERE character_id = ? AND threshold_value = 100',[$cid]);
    rejects(fn()=>DailyEnergy::claim($cid,$id,false),'errCollectDailyBonusRewardInvalidStatus');
    DailyEnergy::record($cid,99);
    check(DailyEnergy::attach([],$cid)['daily_bonus_rewards'][0]['status']===0,'99 energias não desbloqueiam 100');
    DailyEnergy::record($cid,1);
    check(DailyEnergy::attach([],$cid)['daily_bonus_rewards'][0]['status']===1,'100 energias desbloqueiam o primeiro prémio');
    DailyEnergy::record($cid,200);
    check(array_column(DailyEnergy::attach([],$cid)['daily_bonus_rewards'],'status')===[1,1,1],'300 energias desbloqueiam os três prémios');
    DailyEnergy::record($cid,50);
    check((int)Db::value('SELECT energy_spent FROM daily_energy_progress WHERE character_id = ?',[$cid])===300,'Acima de 300 não cria patamares extra');
    $other = (int)Db::value('SELECT id FROM `character` WHERE id <> ? AND user_id > 0 LIMIT 1',[$cid]);
    if ($other) rejects(fn()=>DailyEnergy::claim($other,$id,false),'errCollectDailyBonusRewardInvalidStatus');

    // Exercita todas as opções pelo resgate real. Fixtures apenas no rollback.
    foreach ([100,200,300] as $threshold) foreach (DailyEnergy::catalog($threshold) as $definition) {
        $row = Db::row('SELECT * FROM daily_energy_rewards WHERE character_id = ? AND threshold_value = ?',[$cid,$threshold]);
        if ((int)$row['item_id']>0 && (int)$row['status']===0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?',[(int)$row['item_id'],$cid]);
        $type = $definition['reward_type'];
        $amount = $definition['reward_value'];
        $item = 0;
        if ($type===3) {
            $item = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
            $reward = ['item'=>$item];
            $slots = []; for ($i=1;$i<=18;$i++) $slots[]='bag_item'.$i.'_id = 0';
            Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id = ?',[$cid]);
        } else $reward = [[1=>'quest_energy',4=>'training_sessions',5=>'stat_points',9=>'xp'][$type]=>$type===9 ? 0 : $amount];
        Db::exec('UPDATE daily_energy_rewards SET reward_type = ?, reward_value = ?, reward_identifier = ?, rewards = ?, item_id = ?, status = 0 WHERE id = ?',
            [$type,$amount,$definition['reward_identifier'],json_encode($reward),$item,(int)$row['id']]);
        $preview = DailyEnergy::attach([],$cid);
        if ($type===5) {
            $entry = array_values(array_filter($preview['daily_bonus_rewards'],static fn($r)=>(int)$r['id']===(int)$row['id']))[0];
            $wire = json_decode($entry['rewards'],true);
            check(($wire['statPoints'] ?? 0)===$amount && !isset($wire['stat_points']),'Preview de pontos usa statPoints do cliente, incluindo linhas antigas');
        }
        if ($item) check(in_array($item,array_map('intval',array_column($preview['items'],'id')),true),'Preview épico incluído antes do desenho');
        $before = Db::row('SELECT xp, stat_points_available, quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
        $level = Character::load($cid)->level();
        $points = (int)Season::ensureProgress($cid)['season_points'];
        $coupons = count(UserVouchers::data($cid));
        DailyEnergy::claim($cid,(int)$row['id'],false);
        $after = Db::row('SELECT xp, stat_points_available, quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
        check((int)Season::ensureProgress($cid)['season_points']===$points+1,'Resgate '.$threshold.'/'.$type.' dá exatamente um ponto de temporada');
        if ($type===3) check((int)Db::value('SELECT quality FROM items WHERE id = ?',[$item])===3
            && (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?',[$cid])===$item,'Épico real entregue à mochila');
        elseif ($type===1 || $type===4) {
            $pending = UserVouchers::data($cid); $coupon = end($pending);
            check(count($pending)===$coupons+1 && json_decode($coupon['rewards'],true)===$reward
                && $before['quest_energy']===$after['quest_energy'] && $before['training_count']===$after['training_count'],'Energia/treinos entregues só em cupão');
        } elseif ($type===5) check((int)$after['stat_points_available']-(int)$before['stat_points_available']===$amount,'Pontos livres para gastar, sem atributos automáticos');
        else check((int)$after['xp']-(int)$before['xp']===DailyEnergy::xpReward($level,$amount),'Percentagem XP calculada no nível do resgate');
        rejects(fn()=>DailyEnergy::claim($cid,(int)$row['id'],false),'errCollectDailyBonusRewardInvalidStatus');
        check((int)Season::ensureProgress($cid)['season_points']===$points+1 && count(UserVouchers::data($cid))===$coupons+($type===1 || $type===4 ? 1 : 0),'Repetição não duplica prémio nem pontos');
    }

    // Mochila cheia: rollback do prémio e ponto; descarte explícito vende.
    $item = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
    Db::exec('UPDATE daily_energy_rewards SET reward_type = 3, reward_value = 0, rewards = ?, item_id = ?, status = 0 WHERE id = ?', [json_encode(['item'=>$item]),$item,$id]);
    $slots=[]; for($i=1;$i<=18;$i++) $slots[]='bag_item'.$i.'_id = 2147483647';
    Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id = ?',[$cid]);
    $points = (int)Season::ensureProgress($cid)['season_points'];
    rejects(fn()=>DailyEnergy::claim($cid,$id,false),'errInventoryNoEmptySlot');
    check((int)Db::value('SELECT status FROM daily_energy_rewards WHERE id = ?',[$id])===0
        && (int)Season::ensureProgress($cid)['season_points']===$points,'Mochila cheia não consome prémio nem credita ponto');
    $coins = (int)Db::value('SELECT game_currency FROM `character` WHERE id = ?',[$cid]);
    $sell = (int)Db::value('SELECT sell_price FROM items WHERE id = ?',[$item]);
    $handler = require dirname(__DIR__,2).'/server-laravel/actions/charMisc.php';
    $result = $handler(['action'=>'claimDailyBonusRewardReward','user_id'=>$uid,'id'=>$id,'discard_item'=>'true']);
    check((int)$result['character']['game_currency']===$coins+$sell && !Db::value('SELECT id FROM items WHERE id = ?',[$item]),'Handler permite vender épico e devolve estado atualizado');
    check((int)$result['season_progress']['season_points']===$points+1,'Descarte explícito também resgata o patamar e dá um ponto');

    // Meia-noite expira previews, resgates antigos e o contador.
    $expiredItem = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
    Db::exec('UPDATE daily_energy_rewards SET status = 0, item_id = ?, rewards = ? WHERE id = ?',[$expiredItem,json_encode(['item'=>$expiredItem]),$id]);
    clockAt('2030-07-02 00:00:01');
    $next = DailyEnergy::attach([],$cid);
    check($next['daily_bonus_lookup']['quest_energy_spent']===0 && !Db::value('SELECT id FROM items WHERE id = ?',[$expiredItem]),'Meia-noite limpa contador e preview expirado');
    $old = array_values(array_filter($next['daily_bonus_rewards'],static fn($r)=>$r['id']===$id))[0];
    check($old['status']===2 && $old['rewards']==='{}','ID antigo enviado inativo para o mapa incremental do cliente');
    rejects(fn()=>DailyEnergy::claim($cid,$id,false),'errCollectDailyBonusRewardInvalidStatus');
    // Os três sorteios reais do novo dia somam apenas três pontos.
    DailyEnergy::record($cid,300);
    $points = (int)Season::ensureProgress($cid)['season_points'];
    $actual = Db::rows('SELECT id FROM daily_energy_rewards WHERE character_id = ? AND reward_date = ? ORDER BY threshold_value',[$cid,BonusEvents::today()]);
    Db::exec('UPDATE inventory SET bag_item1_id = 0, bag_item2_id = 0, bag_item3_id = 0 WHERE character_id = ?',[$cid]);
    foreach ($actual as $entry) DailyEnergy::claim($cid,(int)$entry['id'],false);
    check((int)Season::ensureProgress($cid)['season_points']===$points+3,'Três patamares reais dão três pontos no total');
    // Cruzar o próximo nível aplica a sincronização de XP na mesma transação.
    $level = Character::load($cid)->level();
    $xpBefore = Character::xpForLevel($level+1)-1;
    Db::exec('UPDATE `character` SET xp = ? WHERE id = ?',[$xpBefore,$cid]);
    $xpId = (int)$actual[0]['id'];
    Db::exec('UPDATE daily_energy_rewards SET reward_type = 9, reward_value = 3, rewards = ?, item_id = 0, status = 0 WHERE id = ?', ['{"xp":0}',$xpId]);
    DailyEnergy::claim($cid,$xpId,false);
    $model = Character::load($cid);
    check($model->level()===$level+1 && (int)$model->field('xp')===$xpBefore+DailyEnergy::xpReward($level,3),'XP do patamar faz subir de nível corretamente');
    clockAt('2030-07-03 00:00:01');
    $next = DailyEnergy::attach([],$cid);
    $config = require dirname(__DIR__,2).'/server-laravel/actions/getDailyBonusRewardData.php';
    $response = $config(['user_id'=>$uid]);
    check((array)$response['daily_bonus_reward_data']['5']===(array)$next['daily_bonus_reward_data']['5'],'Config real usa sorteios novos persistidos');

    // Ciclo real da missão: só o resgate concluído conta; cancelar/retry não conta.
    $quest = Db::row('SELECT id FROM quests WHERE character_id = ? LIMIT 1',[$cid]);
    if (!$quest) throw new RuntimeException('Herói sem missões para testar.');
    Db::exec('UPDATE `character` SET quest_energy = 100, active_quest_id = 0 WHERE id = ?',[$cid]);
    Db::exec('UPDATE quests SET status = 1, energy_cost = 10 WHERE id = ?',[(int)$quest['id']]);
    DailyEnergy::record($cid,90);
    $startHandler = require dirname(__DIR__,2).'/server-laravel/actions/startQuest.php';
    $started = $startHandler(['user_id'=>$uid,'quest_id'=>(int)$quest['id']]);
    check($started['daily_bonus_lookup']['quest_energy_spent']===90
        && isset($started['daily_bonus_reward_data']['5']),'Iniciar missão não credita energia antes da conclusão');
    $ready = array_values(array_filter($started['daily_bonus_rewards'],static fn($r)=>$r['type']===5 && $r['value']===100 && $r['status']===1));
    check(count($ready)===0,'Missão em curso não permite resgatar o patamar antecipadamente');
    $retry = $startHandler(['user_id'=>$uid,'quest_id'=>(int)$quest['id']]);
    check($retry['daily_bonus_lookup']['quest_energy_spent']===90,'Retry de início não avança patamares');
    $abortHandler = require dirname(__DIR__,2).'/server-laravel/actions/abortQuest.php';
    $aborted = $abortHandler(['user_id'=>$uid]);
    check($aborted['daily_bonus_lookup']['quest_energy_spent']===90,'Cancelar missão não conta a sua energia nos patamares');
    rejects(fn()=>$abortHandler(['user_id'=>$uid]),'errNoActiveQuest');
    check((int)Db::value('SELECT energy_spent FROM daily_energy_progress WHERE character_id = ?',[$cid])===90,'Repetir cancelamento não altera contador');
    Db::exec('UPDATE quests SET energy_cost = 30000 WHERE id = ?',[(int)$quest['id']]);
    rejects(fn()=>Character::load($cid)->startQuest((int)$quest['id']),'errRemoveQuestEnergyNotEnough');
    check((int)Db::value('SELECT energy_spent FROM daily_energy_progress WHERE character_id = ?',[$cid])===90,'Missão sem energia não avança patamares');
    Db::exec('UPDATE quests SET type = 1, energy_cost = 10, status = 2, ts_complete = 0 WHERE id = ?',[(int)$quest['id']]);
    Db::exec('UPDATE `character` SET active_quest_id = ? WHERE id = ?',[(int)$quest['id'],$cid]);
    foreach (['instantFinishQuest','checkForQuestComplete','claimQuestRewards'] as $action) {
        $questHandler = require dirname(__DIR__,2).'/server-laravel/actions/'.$action.'.php';
        $response = $questHandler(['user_id'=>$uid]);
        check($response['daily_bonus_lookup']['quest_energy_spent']===($action==='claimQuestRewards' ? 100 : 90)
            && isset($response['daily_bonus_reward_data']['5']),$action.' também devolve patamares atualizados');
    }
    $ready = array_values(array_filter($response['daily_bonus_rewards'],static fn($r)=>$r['type']===5 && $r['value']===100 && $r['status']===1));
    check(count($ready)===1,'Resgate da missão cruza 100 e disponibiliza prémio na mesma resposta');
    rejects(fn()=>Character::load($cid)->claimQuestRewards(),'errClaimQuestRewardsNoActiveQuest');
    check((int)Db::value('SELECT energy_spent FROM daily_energy_progress WHERE character_id = ?',[$cid])===100,'Retry de resgate não duplica energia contabilizada');
    $state = Live::accountState($uid);
    $partial = Live::overlayAccount(['character'=>Live::template('autoLoginUser')['character']],$uid);
    check($state['daily_bonus_lookup']['quest_energy_spent']===100 && $partial['daily_bonus_lookup']['quest_energy_spent']===100,'Estado completo e parcial têm contador real');
    $bootHandler = require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
    $boot = $bootHandler(['user_id'=>$uid,'existing_user_id'=>$uid,'existing_session_id'=>Db::value('SELECT session_id FROM `user` WHERE id = ?',[$uid])]);
    check($boot['daily_bonus_lookup']['quest_energy_spent']===100 && isset($boot['daily_bonus_reward_data']['5']),'Login real mantém patamares e contador');
    check($pdo->inTransaction(),'Resgates/savepoints preservam transação exterior');
    echo "Integração MySQL concluída; alterações de teste revertidas.\n";
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
