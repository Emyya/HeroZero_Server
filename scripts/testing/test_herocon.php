<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{HeroCon as H,Db,GameTime,GameError,Character,Season,Herobook};
function hcCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function hcReject(callable $fn,string $error):void {try{$fn();}catch(GameError $e){hcCheck($e->getMessage()===$error,'Erro: '.$e->getMessage());return;}throw new RuntimeException('Pedido devia falhar: '.$error);}
$pdo=null;$failed=false;
try {
    hcCheck(count(H::themes())===13&&!in_array(H::EXCLUDED,H::themes(),true),'13 temas sem missões especiais');
    hcCheck(H::points(45)===['score'=>45,'fraction'=>0],'Pontos diretos');
    foreach([1=>10,3=>10,4=>9,20=>8,50=>5,100=>4,300=>1,301=>0] as $rank=>$stats)hcCheck(H::soloStats($rank)===$stats,'Faixa individual '.$rank);
    hcCheck(H::teamReward(1,100)===['booster'=>30,'statPoints'=>36],'Fórmula equipa, primeiro');
    hcCheck(H::teamReward(100,100)===['booster'=>1,'statPoints'=>2],'Fórmula equipa, centésimo');
    foreach(['2026-10-07 12:00','2026-10-08 20:59:59','2026-10-08 21:00','2026-10-25 12:00','2026-03-29 12:00'] as $time) {
        $now=(new DateTimeImmutable($time,new DateTimeZone('Europe/Lisbon')))->getTimestamp();$p=H::period($now);
        hcCheck($p['ts_start']<=$now&&$p['ts_end']>$now,'Período inclui '.$time);
        hcCheck((new DateTimeImmutable('@'.$p['ts_end']))->setTimezone(new DateTimeZone('Europe/Lisbon'))->format('N H:i')==='4 21:00','Fim quinta 21:00 local');
        hcCheck(H::period($p['ts_end'])['ts_start']===$p['ts_end'],'Continuidade do calendário');
    }
    echo "PASS offline: catálogo, exclusão, pontos diretos, prémios e calendário com mudança de hora.\n";
    if(!in_array('--mysql',$argv,true))exit(0);
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo();hcCheck(H::available(),'Executar migrate_herocon.php primeiro');
    $name=null;foreach($argv as $arg)if(str_starts_with($arg,'--hero='))$name=substr($arg,7);
    $hero=$name?Db::row('SELECT id,user_id,name FROM `character` WHERE name=?',[$name]):Db::row('SELECT id,user_id,name FROM `character` ORDER BY id LIMIT 1');
    hcCheck($hero!==null,'Herói não encontrado');$cid=(int)$hero['id'];
    echo 'Herói: '.$hero['name'].". Preparação temporária de HeroCon/equipa/recursos; tudo revertido. Não jogar com este herói durante o teste.\n";
    $pdo->beginTransaction();Db::row('SELECT id FROM herocon_control WHERE id=1 FOR UPDATE');
    $guild=Db::row('SELECT * FROM `guild` ORDER BY id LIMIT 1');hcCheck($guild!==null,'É necessária uma equipa existente para clonar em rollback');
    unset($guild['id']);$guild['name']='HC teste '.bin2hex(random_bytes(4));
    $cols=array_keys($guild);Db::exec('INSERT INTO `guild` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($guild));$gid=(int)$pdo->lastInsertId();
    Db::exec('UPDATE `character` SET guild_id=?,level=100 WHERE id=?',[$gid,$cid]);
    $cycle=H::sync();$tid=(int)$cycle['id'];
    Db::exec('DELETE FROM herocon_rewards WHERE character_id=?',[$cid]);
    // Um cenário independente por tema: os produtores só podem contar objetivos definidos.
    foreach(H::themes() as $identifier) {
        Db::exec('DELETE FROM herocon_contributions WHERE cycle_id=?',[$tid]);Db::exec('DELETE FROM herocon_rank_times WHERE cycle_id=?',[$tid]);
        Db::exec('UPDATE herocon_cycles SET identifier=? WHERE id=?',[$identifier,$tid]);
        $definition=H::constants()['guild_competitions'][$identifier];$expected=0;
        foreach($definition['objectives'] as $o){H::record($cid,(int)$o['type'],400,(string)$o['reference']);$expected+=400*(int)$o['multiplier'];}
        $p=H::contribution($tid,$cid,$gid);hcCheck($p===H::points($expected),'Pontuação '.$identifier);
        $before=$p;foreach([5,15,22,25] as $disabledType)H::record($cid,$disabledType,999999);hcCheck(H::contribution($tid,$cid,$gid)===$before,'Missões especiais ignoradas');
        $data=H::attach(['character'=>['id'=>$cid]],$cid);hcCheck($data['guild_competition_data']['identifier']===$identifier,'Estado atual');
        hcCheck(H::leaderboard($cid,true,[])['leaderboard_solo_guild_competitions'][0]['r']===1,'Posição individual r e rank');
    }

    // Produtores reais: compras só contam a parte paga; início de missão conta energia uma vez.
    $pdo->exec('SAVEPOINT herocon_producers');
    Db::exec('DELETE FROM herocon_contributions WHERE cycle_id=?',[$tid]);
    Db::exec('UPDATE herocon_cycles SET identifier=? WHERE id=?',['default_guild_competition_stat_points_bought',$tid]);
    Db::exec('UPDATE `character` SET stat_points_available=2,game_currency=100000000 WHERE id=?',[$cid]);
    Character::load($cid)->improveStat(1,5);
    hcCheck(H::contribution($tid,$cid,$gid)['score']===3,'Compra real só soma 3 pontos pagos');
    Db::exec('UPDATE herocon_cycles SET identifier=? WHERE id=?',['default_guild_competition_time_quest',$tid]);
    Db::exec('DELETE FROM herocon_contributions WHERE cycle_id=?',[$tid]);
    Db::exec('UPDATE `character` SET active_quest_id=0,active_worldboss_attack_id=0,quest_energy=1000,current_quest_stage=1 WHERE id=?',[$cid]);
    $player=Character::load($cid);$player->ensureQuestPool(1);
    $quest=Db::row('SELECT * FROM quests WHERE character_id=? AND type=1 AND status=1 LIMIT 1',[$cid]);hcCheck($quest!==null,'Oferta de tempo real');
    Db::exec('UPDATE quests SET energy_cost=20 WHERE id=?',[(int)$quest['id']]);
    $player->startQuest((int)$quest['id']);hcCheck(H::contribution($tid,$cid,$gid)['score']===20,'Energia de missão de tempo real');
    Character::load($cid)->startQuest((int)$quest['id']);hcCheck(H::contribution($tid,$cid,$gid)['score']===20,'Retry do início não duplica energia');
    // Cancelar não devolve pontuação já contada ao gastar energia (política local).
    $pdo->exec('ROLLBACK TO SAVEPOINT herocon_producers');$pdo->exec('RELEASE SAVEPOINT herocon_producers');
    echo "PASS produtores reais: compras com pontos gratuitos e início/retry de missão de tempo.\n";
    // Fecho e entrega, duas classificações e preview persistente.
    Db::exec('DELETE FROM herocon_contributions WHERE cycle_id=?',[$tid]);Db::exec('DELETE FROM herocon_rank_times WHERE cycle_id=?',[$tid]);
    Db::exec('UPDATE herocon_cycles SET identifier=? WHERE id=?',['default_guild_competition_sell_items',$tid]);
    H::sold($cid,['quality'=>3,'ts_availability_start'=>15]);hcCheck(H::contribution($tid,$cid,$gid)['score']===12,'Épico mais bónus dourado aditivo');
    H::record($cid,2,3000);
    $season=Season::ensureProgress($cid);$seasonBefore=(int)$season['season_points'];
    $p=H::period(GameTime::now());
    $oldStart=$p['ts_start']-7*86400;
    while(Db::value('SELECT id FROM herocon_cycles WHERE ts_start=? AND id<>?',[$oldStart,$tid]))$oldStart-=7*86400;
    Db::exec('UPDATE herocon_cycles SET ts_start=?,ts_end=? WHERE id=?',[$oldStart,GameTime::now()-1,$tid]);
    H::sync();hcCheck((int)Db::value('SELECT COUNT(*) FROM herocon_rewards WHERE cycle_id=? AND character_id=?',[$tid,$cid])===2,'Dois direitos de prémio');
    H::sync();hcCheck((int)Db::value('SELECT COUNT(*) FROM herocon_rewards WHERE cycle_id=? AND character_id=?',[$tid,$cid])===2,'Fecho idempotente');
    $preview=H::reward($cid,6);hcCheck(is_string($preview['rewards'])&&is_string(json_decode($preview['rewards'],true)['reward']),'Strings JSON aninhadas');
    $reward=json_decode(json_decode($preview['rewards'],true)['reward'],true);hcCheck(!empty($reward['item']),'Caixa primeiro lugar');
    hcCheck(H::reward($cid,6)===$preview,'Preview estável');
    hcReject(fn()=>H::assertItemsAvailable($cid,[$reward['item']]),'errInventoryInvalidItem');

    $pdo->exec('SAVEPOINT herocon_box_use');
    Db::exec('UPDATE inventory SET bag_item1_id=0 WHERE character_id=?',[$cid]);
    H::reward($cid,6,true);
    $useItem=require dirname(__DIR__,2).'/server-laravel/actions/inventoryOps.php';
    Db::exec('UPDATE inventory SET bag_item2_id=0 WHERE character_id=?',[$cid]);
    $pdo->exec('SAVEPOINT herocon_box_full');
    $sets=[];foreach(range(1,18) as $slot)$sets[]='bag_item'.$slot.'_id='.( $slot===1 ? (int)$reward['item'] : 1 );
    Db::exec('UPDATE inventory SET '.implode(',',$sets).' WHERE character_id=?',[$cid]);
    hcReject(fn()=>\HeroZero\HeroConBox::open($cid,(int)$reward['item']),'errInventoryNoEmptySlot');
    hcCheck((int)Db::value('SELECT COUNT(*) FROM items WHERE id=?',[$reward['item']])===1,'Mochila cheia conserva caixa');
    $pdo->exec('ROLLBACK TO SAVEPOINT herocon_box_full');$pdo->exec('RELEASE SAVEPOINT herocon_box_full');
    $opened=\HeroZero\Router::dispatch('useInventoryItem',['user_id'=>(int)$hero['user_id'],'action'=>'useInventoryItem','item_id'=>$reward['item']]);
    hcCheck((int)Db::value('SELECT COUNT(*) FROM items WHERE id=? AND character_id=?',[$reward['item'],$cid])===0,'Caixa consumida após entrega');
    hcCheck(count($opened['items'])===2&&str_contains($opened['items'][0]['identifier'],'solo_herocon')&&str_contains($opened['items'][1]['identifier'],'solo_herocon'),'Duas peças no início da resposta');
    $kyberFlat=\HeroZero\Hideout::kyberneticBonuses($cid)['item_flat'];
    foreach(array_slice($opened['items'],0,2) as $piece) {
        hcCheck((int)$piece['ts_availability_start']===15&&(int)$piece['ts_availability_end']===1,'Caixa grande: borda dourada e espaço gratuito');
        hcCheck((int)$piece['quality']===3&&(int)$piece['required_level']>=96&&(int)$piece['required_level']<=100,'Qualidade épica e nível da loja');
        $level=(int)$piece['required_level'];
        $budget=(int)ceil(($level*1.6+2+$level**2*0.004)*2.3)+$kyberFlat;
        $sum=0;foreach(['stat_stamina','stat_strength','stat_critical_rating','stat_dodge_rating'] as $stat)$sum+=(int)$piece[$stat];
        hcCheck($sum===$budget,'Budget de atributos da loja com bónus do esconderijo');
    }
    hcReject(fn()=>\HeroZero\HeroConBox::open($cid,(int)$reward['item']),'errInventoryInvalidItem');
    $bases=array_map(static fn($row)=>preg_replace('/-effect-.*$/','',$row['identifier']),array_slice($opened['items'],0,2));
    hcCheck($bases[0]!==$bases[1],'Caixa grande dá duas peças distintas enquanto faltam peças');
    $small=Character::load($cid)->createSpecialItem('surprise_box_solo_herocon_small_1',10);
    $sets=[];foreach(range(1,18) as $slot)$sets[]='bag_item'.$slot.'_id='.( $slot===1 ? $small : 1 );
    Db::exec('UPDATE inventory SET '.implode(',',$sets).' WHERE character_id=?',[$cid]);
    $smallResponse=\HeroZero\Router::dispatch('useInventoryItem',['user_id'=>(int)$hero['user_id'],'action'=>'useInventoryItem','item_id'=>$small]);
    hcCheck(!isset($smallResponse['_herocon_box_reward_ids']),'Marcador interno removido da resposta');
    $one=$smallResponse['items'];
    hcCheck((int)$one[0]['ts_availability_start']===15&&(int)$one[0]['ts_availability_end']===1,'Caixa pequena: borda dourada e espaço gratuito');
    hcCheck(count($one)===1,'Caixa pequena ocupa o espaço da própria caixa com mochila cheia');
    hcCheck(!in_array(preg_replace('/-effect-.*$/','',$one[0]['identifier']),$bases,true),'Coleção persiste mesmo sem peças na mochila');
    $pdo->exec('ROLLBACK TO SAVEPOINT herocon_box_use');$pdo->exec('RELEASE SAVEPOINT herocon_box_use');
    $sets=[];foreach(range(1,18) as $i)$sets[]='bag_item'.$i.'_id=1';Db::exec('UPDATE inventory SET '.implode(',',$sets).' WHERE character_id=?',[$cid]);
    $before=(int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid]);
    hcReject(fn()=>H::reward($cid,6,true),'errInventoryNoEmptySlot');
    hcCheck((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])===$before,'Mochila cheia reverte atributos');
    H::reward($cid,6,true,true);
    $expectedSeason=(int)Db::value('SELECT season_points FROM herocon_rewards WHERE cycle_id=? AND character_id=? AND kind=6',[$tid,$cid]);
    hcCheck((int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid])===$seasonBefore+$expectedSeason,'Pontos da temporada por localização, entregues uma vez');
    hcCheck((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])===$before+10,'Descarte preserva pontos');
    hcReject(fn()=>H::reward($cid,6,true,true),'errClaimSoloGuildCompetitionTournamentRewardsNoRewards');
    hcCheck((int)Db::value('SELECT season_points FROM season_progress WHERE character_id=?',[$cid])===$seasonBefore+$expectedSeason,'Duplicado não entrega temporada de novo');
    H::reward($cid,5,true);hcReject(fn()=>H::reward($cid,5,true),'errClaimGuildCompetitionTournamentRewardsNoRewards');
    hcCheck(H::boost($gid)>1,'Booster equipa aplicado');
    H::viewed($cid);$goals=Character::goalStatsFor($cid);H::viewed($cid);hcCheck(Character::goalStatsFor($cid)===$goals,'Visto repetido não duplica feito');hcCheck(H::attach(['character'=>['id'=>$cid]],$cid)['character']['new_guild_competition_tournament']===false,'Marcação visto');
    $active=H::sync();
    Db::exec('UPDATE herocon_cycles SET identifier=? WHERE id=?',['default_guild_competition_daily_herobook_completed',(int)$active['id']]);
    $replacement=H::sync();
    hcCheck((int)$replacement['id']!==(int)$active['id']&&in_array($replacement['identifier'],H::themes(),true),'Tema retirado substituído por tema ativo');
    hcCheck((int)Db::value('SELECT finished FROM herocon_cycles WHERE id=?',[(int)$active['id']])===1,'Ciclo retirado preservado e fechado');
    echo "PASS MySQL: 13 temas, exclusão, classificação, fecho idempotente, previews, mochila cheia, descarte, resgate único, booster e estado.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");$failed=true;} finally {if($pdo&&$pdo->inTransaction()){$pdo->rollBack();echo "ROLLBACK: preparação e entregas de teste revertidas.\n";}}

if($failed)exit(1);
