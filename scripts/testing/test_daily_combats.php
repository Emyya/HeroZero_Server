<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{DailyEnergy,DailyMilestones,Db,Character,BonusEvents,GameTime,Live,Season,UserVouchers,GameError};
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
foreach ([2,3,4] as $type) {
    foreach (DailyMilestones::thresholds($type) as $rank=>$threshold) {
        check(DailyMilestones::catalog($type,$threshold)===DailyEnergy::catalog([100,200,300][$rank]),"Catálogo {$type}/{$threshold} igual ao de energia");
    }
}
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
    Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE',[$cid]);
    $uid = Character::load($cid)->userId();
    $other = (int)Db::value('SELECT id FROM `character` WHERE id <> ? AND user_id > 0 LIMIT 1',[$cid]);
    foreach ([2=>'duel',3=>'league',4=>'hideout'] as $type=>$kind) {
        clockAt('2030-07-01 12:00:00');
        $table = 'daily_'.$kind;
        $lookup = $kind.'_battle_won';
        $thresholds = DailyMilestones::thresholds($type);
        Db::exec("DELETE FROM {$table}_rewards WHERE character_id = ?",[$cid]);
        Db::exec("DELETE FROM {$table}_progress WHERE character_id = ?",[$cid]);
        Db::exec('UPDATE `character` SET level = 100, xp = ?, guild_id = 0 WHERE id = ?', [Character::xpForLevel(100),$cid]);
        $data = DailyMilestones::attach([],$cid,$type);
        check($data['daily_bonus_lookup'][$lookup]===0 && count($data['daily_bonus_rewards'])===3,"{$kind}: começa a zero com três patamares");
        check($data==DailyMilestones::attach([],$cid,$type),"{$kind}: sorteios e IDs estáveis");
        $id = (int)$data['daily_bonus_rewards'][0]['id'];
        rejects(fn()=>DailyMilestones::claimById($cid,$id,false),'errCollectDailyBonusRewardInvalidStatus');
        foreach ($thresholds as $threshold) {
            Db::exec("UPDATE {$table}_progress SET fight_count = ? WHERE character_id = ?",[$threshold-1,$cid]);
            $wire = DailyMilestones::attach([],$cid,$type);
            $entry = array_values(array_filter($wire['daily_bonus_rewards'],fn($r)=>$r['value']===$threshold))[0];
            check($entry['status']===0,"{$kind}: abaixo de {$threshold} não desbloqueia");
            DailyMilestones::record($cid,1,$type);
            $wire = DailyMilestones::attach([],$cid,$type);
            $entry = array_values(array_filter($wire['daily_bonus_rewards'],fn($r)=>$r['value']===$threshold))[0];
            check($entry['status']===1,"{$kind}: {$threshold} desbloqueia na resposta");
        }
        DailyMilestones::record($cid,500,$type);
        check((int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid])===max($thresholds),"{$kind}: contador limitado ao terceiro patamar");
        if ($other) rejects(fn()=>DailyMilestones::claimById($other,$id,false),'errCollectDailyBonusRewardInvalidStatus');
    // Exercita todas as opções pelo resgate real. Fixtures apenas no rollback.
    foreach ($thresholds as $threshold) foreach (DailyMilestones::catalog($type,$threshold) as $definition) {
        $row = Db::row("SELECT * FROM {$table}_rewards WHERE character_id = ? AND threshold_value = ?",[$cid,$threshold]);
        if ((int)$row['item_id']>0 && (int)$row['status']===0) Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?',[(int)$row['item_id'],$cid]);
        $rewardType = $definition['reward_type'];
        $amount = $definition['reward_value'];
        $item = 0;
        if ($rewardType===3) {
            $item = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
            $reward = ['item'=>$item];
            $slots = []; for ($i=1;$i<=18;$i++) $slots[]='bag_item'.$i.'_id = 0';
            Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id = ?',[$cid]);
        } else $reward = [[1=>'quest_energy',4=>'training_sessions',5=>'stat_points',9=>'xp'][$rewardType]=>$rewardType===9 ? 0 : $amount];
        Db::exec("UPDATE {$table}_rewards SET reward_type = ?, reward_value = ?, reward_identifier = ?, rewards = ?, item_id = ?, status = 0 WHERE id = ?",
            [$rewardType,$amount,$definition['reward_identifier'],json_encode($reward),$item,(int)$row['id']]);
        $preview = DailyMilestones::attach([],$cid,$type);
        if ($rewardType===5) {
            $entry = array_values(array_filter($preview['daily_bonus_rewards'],static fn($r)=>(int)$r['id']===(int)$row['id']))[0];
            $wire = json_decode($entry['rewards'],true);
            check(($wire['statPoints'] ?? 0)===$amount && !isset($wire['stat_points']),'Preview de pontos usa statPoints do cliente, incluindo linhas antigas');
        }
        if ($item) check(in_array($item,array_map('intval',array_column($preview['items'],'id')),true),'Preview épico incluído antes do desenho');
        $before = Db::row('SELECT xp, stat_points_available, quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
        $level = Character::load($cid)->level();
        $points = (int)Season::ensureProgress($cid)['season_points'];
        $coupons = count(UserVouchers::data($cid));
        DailyMilestones::claimById($cid,(int)$row['id'],false);
        $after = Db::row('SELECT xp, stat_points_available, quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
        check((int)Season::ensureProgress($cid)['season_points']===$points+1,'Resgate '.$threshold.'/'.$rewardType.' dá exatamente um ponto de temporada');
        if ($rewardType===3) check((int)Db::value('SELECT quality FROM items WHERE id = ?',[$item])===3
            && (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?',[$cid])===$item,'Épico real entregue à mochila');
        elseif ($rewardType===1 || $rewardType===4) {
            $pending = UserVouchers::data($cid); $coupon = end($pending);
            check(count($pending)===$coupons+1 && json_decode($coupon['rewards'],true)===$reward
                && $before['quest_energy']===$after['quest_energy'] && $before['training_count']===$after['training_count'],'Energia/treinos entregues só em cupão');
        } elseif ($rewardType===5) check((int)$after['stat_points_available']-(int)$before['stat_points_available']===$amount,'Pontos livres para gastar, sem atributos automáticos');
        else check((int)$after['xp']-(int)$before['xp']===DailyEnergy::xpReward($level,$amount),'Percentagem XP calculada no nível do resgate');
        rejects(fn()=>DailyMilestones::claimById($cid,(int)$row['id'],false),'errCollectDailyBonusRewardInvalidStatus');
        check((int)Season::ensureProgress($cid)['season_points']===$points+1 && count(UserVouchers::data($cid))===$coupons+($rewardType===1 || $rewardType===4 ? 1 : 0),'Repetição não duplica prémio nem pontos');
    }

    // Mochila cheia: rollback do prémio e ponto; descarte explícito vende.
    $item = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
    Db::exec("UPDATE {$table}_rewards SET reward_type = 3, reward_value = 0, rewards = ?, item_id = ?, status = 0 WHERE id = ?", [json_encode(['item'=>$item]),$item,$id]);
    $slots=[]; for($i=1;$i<=18;$i++) $slots[]='bag_item'.$i.'_id = 2147483647';
    Db::exec('UPDATE inventory SET '.implode(',',$slots).' WHERE character_id = ?',[$cid]);
    $points = (int)Season::ensureProgress($cid)['season_points'];
    rejects(fn()=>DailyMilestones::claimById($cid,$id,false),'errInventoryNoEmptySlot');
    check((int)Db::value("SELECT status FROM {$table}_rewards WHERE id = ?",[$id])===0
        && (int)Season::ensureProgress($cid)['season_points']===$points,'Mochila cheia não consome prémio nem credita ponto');
    $coins = (int)Db::value('SELECT game_currency FROM `character` WHERE id = ?',[$cid]);
    $sell = (int)Db::value('SELECT sell_price FROM items WHERE id = ?',[$item]);
    $handler = require dirname(__DIR__,2).'/server-laravel/actions/charMisc.php';
    $result = $handler(['action'=>'claimDailyBonusRewardReward','user_id'=>$uid,'id'=>$id,'discard_item'=>'true']);
    check((int)$result['character']['game_currency']===$coins+$sell && !Db::value('SELECT id FROM items WHERE id = ?',[$item]),'Handler permite vender épico e devolve estado atualizado');
    check((int)$result['season_progress']['season_points']===$points+1,'Descarte explícito também resgata o patamar e dá um ponto');


        // Expiração e tombstones no próximo dia.
        $expired = Character::load($cid)->createStoryDungeonItem(['reward_item_identifier'=>'random_epic','reward_item_level_offset'=>0]);
        Db::exec("UPDATE {$table}_rewards SET status = 0, item_id = ?, rewards = ? WHERE id = ?",[$expired,json_encode(['item'=>$expired]),$id]);
        clockAt('2030-07-02 00:00:01');
        $wire = DailyMilestones::attach([],$cid,$type);
        check($wire['daily_bonus_lookup'][$lookup]===0 && !Db::value('SELECT id FROM items WHERE id = ?',[$expired]),"{$kind}: meia-noite zera e elimina preview expirado");
        check($wire['daily_bonus_rewards'][0]['status']===2 && $wire['daily_bonus_rewards'][0]['rewards']==='{}',"{$kind}: tombstone desativa ID antigo");
        rejects(fn()=>DailyMilestones::claimById($cid,$id,false),'errCollectDailyBonusRewardInvalidStatus');

        if ($type===4) {
            $h = HeroZero\Hideout::rowFor($cid);
            if (!$h) $h = HeroZero\Hideout::createRow($cid);
            if (!$other) throw new RuntimeException('Teste de esconderijo exige outra conta.');
            $defender = HeroZero\Hideout::rowFor($other);
            if (!$defender) $defender = HeroZero\Hideout::createRow($other);
            $handler = require dirname(__DIR__,2).'/server-laravel/actions/hideoutExtra.php';
            foreach (['win','loss','tutorial','defense'] as $case) {
                $attackerId = $case==='defense' ? (int)$defender['id'] : (int)$h['id'];
                $defenderId = $case==='tutorial' ? -1 : ($case==='defense' ? (int)$h['id'] : (int)$defender['id']);
                Db::exec("INSERT INTO hideout_battles (attacker_hideout_id,defender_hideout_id,attacker_status,defender_status,attacker_profiles,defender_profiles,rounds,attacker_rewards,attacker_bonus_rewards,defender_rewards,hideout_winner_id,ts_creation) VALUES (?, ?, 1, 1, '[]', '[]', '[]', '{}', '{}', '{}', ?, ?)",[$attackerId,$defenderId,$case==='win' ? $attackerId : $defenderId,GameTime::now()]);
                $battle = (int)$pdo->lastInsertId();
                Db::exec('UPDATE hideout SET active_battle_id = ? WHERE character_id = ?',[$case==='defense' ? 0 : $battle,$cid]);
                $before = (int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid]);
                $expected = $before + (in_array($case,['win','loss'],true) ? 1 : 0);
                $result = $handler(['action'=>'collectHideoutFightRewards','user_id'=>$uid,'hideout_battle_id'=>$battle]);
                check($result['daily_bonus_lookup'][$lookup]===$expected,"esconderijo: {$case} conta só ataque a outro esconderijo e atualiza resposta");
                $handler(['action'=>'collectHideoutFightRewards','user_id'=>$uid,'hideout_battle_id'=>$battle]);
                check((int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid])===$expected,'esconderijo: retry não duplica');
            }
            $handler(['action'=>'collectHideoutFightRewards','user_id'=>$uid,'hideout_battle_id'=>2147483647]);
            check((int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid])===2,'esconderijo: batalha inválida não conta');
        } else {
        // Resgate real de resultados, vitórias/derrotas, retry e defesas.
        $fightTable = $type===2 ? 'duel' : 'league_fight';
        $active = $type===2 ? 'active_duel_id' : 'active_league_fight_id';
        $handler = require dirname(__DIR__,2).'/server-laravel/actions/'.($type===2 ? 'claimDuelRewards.php' : 'leagueMissed.php');
        $action = $type===2 ? 'claimDuelRewards' : 'claimLeagueFightRewards';
        Db::exec("UPDATE {$fightTable} SET character_a_status = 3 WHERE character_a_id = ?",[$cid]);
        foreach (['a','b'] as $winner) {
            Db::exec("INSERT INTO battle (ts_creation,profile_a_stats,profile_b_stats,winner,rounds) VALUES (?, '{}', '{}', ?, '[]')",[GameTime::now(),$winner]);
            $battle = (int)$pdo->lastInsertId();
            Db::exec("INSERT INTO {$fightTable} (ts_creation,battle_id,character_a_id,character_b_id,character_a_status,character_a_rewards,character_b_rewards) VALUES (?, ?, ?, ?, 2, '{}', '{}')",[GameTime::now(),$battle,$cid,$other ?: $cid]);
            $fight = (int)$pdo->lastInsertId();
            Db::exec("UPDATE `character` SET {$active} = ? WHERE id = ?",[$fight,$cid]);
            $before = (int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid]);
            $result = $handler(['action'=>$action,'user_id'=>$uid]);
            check($result['daily_bonus_lookup'][$lookup]===$before+1,"{$kind}: resultado {$winner} conta e atualiza resposta imediata");
            if ($type===2) $handler(['action'=>$action,'user_id'=>$uid]);
            else rejects(fn()=>$handler(['action'=>$action,'user_id'=>$uid]),'errClaimLeagueFightRewardsNoActiveLeagueFight');
            check((int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid])===$before+1,"{$kind}: retry não duplica");
        }
        $before = (int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid]);
        $missed = require dirname(__DIR__,2).'/server-laravel/actions/leagueMissed.php';
        $missed(['action'=>$type===2 ? 'claimMissedDuelsRewards' : 'claimMissedLeagueFightsRewards','user_id'=>$uid]);
        check((int)Db::value("SELECT fight_count FROM {$table}_progress WHERE character_id = ?",[$cid])===$before,"{$kind}: resgatar defesas não conta");
        }

    }
    $state = Live::accountState($uid);
    $overlay = Live::overlayAccount([],$uid);
    $login = require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
    $boot = $login(['user_id'=>$uid,'existing_user_id'=>$uid,'existing_session_id'=>Db::value('SELECT session_id FROM `user` WHERE id = ?',[$uid])]);
    foreach (['duel_battle_won','league_battle_won','hideout_battle_won'] as $key) check($state['daily_bonus_lookup'][$key]===$overlay['daily_bonus_lookup'][$key] && $state['daily_bonus_lookup'][$key]===$boot['daily_bonus_lookup'][$key],"{$key}: login, estado completo e parcial");
    check(count(array_unique(array_column($state['daily_bonus_rewards'],'id')))===count($state['daily_bonus_rewards']),'IDs independentes entre sistemas');
    check($pdo->inTransaction(),'Transação exterior preservada');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
echo "Integração MySQL concluída; alterações de teste revertidas.\n";
