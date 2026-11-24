<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Worldboss,Db,Character,GameTime,GameError,Live,Router};
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: $message\n"; }
function rejects(callable $fn,string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage()===$error,$error); return; }
    throw new RuntimeException('Esperado: '.$error);
}
$pdo=null;
try {
    check(Worldboss::attackRewards(1)===['coins'=>10,'xp'=>5] && Worldboss::attackRewards(100)===['coins'=>1000,'xp'=>500], 'Moedas e XP por combate reduzidos para metade');
    $heroStats=['level'=>20,'strength'=>1000,'criticalrating'=>2000,'dodgerating'=>3000,'damage_normal'=>9000];
    $factors=['npc_strength_percent'=>95,'npc_critical_percent'=>110,'npc_dodge_percent'=>120];
    $npc=Worldboss::npcProfile($heroStats,$factors,12345);
    check($npc['strength']===950 && $npc['criticalrating']===2200 && $npc['dodgerating']===3600,
        'Fatores independentes 95–120% sobre os atributos do herói, sem somar arma');
    $next=Worldboss::npcProfile($heroStats,$factors,5432);
    check($npc['hitpoints']===12345 && $npc['stamina']===1235 && $next['hitpoints']===5432
        && $next['stamina']===544 && $next['strength']===$npc['strength'] && $next['criticalrating']===$npc['criticalrating'],
        'Vida atual exata, condição derivada da vida e fatores estáveis entre combates');
    foreach ([50,300,1000] as $level) foreach ([1=>1.0,2=>1.7,3=>2.3] as $quality=>$mult) {
        for ($sample=0;$sample<20;$sample++) {
            $stats=Character::shopItemStats($level,$quality,17);
            $count=count(array_filter($stats));
            if (array_sum($stats)!==(int)ceil(($level*1.6+2+$level**2*0.004)*$mult)+17
                || $count<$quality || $count>$quality+1)
                throw new RuntimeException('Budget/distribution de loja incompatível');
        }
        check(true,"Budget da loja e distribuição: nível $level, qualidade $quality, bónus 17");
    }
    $c=Worldboss::constants();
    check(count($c['worldboss_event_templates'])===39,'39 templates oficiais');
    foreach ($c['worldboss_event_templates'] as $id=>$t) {
        $npc=$t['npc_identifier']; $rewards=Worldboss::defaultRewards($id);
        check(isset($c['npcs'][$npc]) && (int)$c['npcs'][$npc]['type']===2
            && is_file(dirname(__DIR__,2).'/server-laravel/public/assets/worldboss_npcs/'.$npc.'.webp')
            && isset($c['item_templates'][$rewards['rank']],$c['item_templates'][$rewards['pool']]),'NPC, imagem e prémios válidos: '.$id);
    }
    check(Worldboss::canMultiAttack(10,100) && !Worldboss::canMultiAttack(9,100) && !Worldboss::canMultiAttack(10,101),'Limiar inclusivo e arredondado a 10%');
    check(Worldboss::duration(10)===300 && Worldboss::duration(10,50)===180 && Worldboss::duration(1)===60,'Duração do cliente: nível, boosters e arredondamento');
    check(Worldboss::poolReward(1,10)===['quest_energy'=>30,'training_sessions'=>8]
        && Worldboss::poolReward(10,10)===['quest_energy'=>10,'training_sessions'=>2], 'Escalões locais de participação e tipos de recompensa');
    foreach (['primal_season_worldboss','utopia_season_worldboss','dystopia_season_worldboss'] as $id) {
        $r=Worldboss::defaultRewards($id);
        check((int)$c['item_templates'][$r['rank']]['type']!==9 && in_array((int)$c['item_templates'][$r['pool']]['type'],[1,2,3,4,5],true),'Equipamento sem pet garantido da temporada: '.$id);
    }
    $normalPets=Worldboss::petPool('default_event_stage1');
    check(count($normalPets)>0,'Pool normal de pets não vazio');
    foreach ($normalPets as $id) check(!str_contains($id,'season') && (int)$c['item_templates'][$id]['quality']<4,'Pet normal elegível: '.$id);
    foreach (['xmas_event_stage2','easter_event_stage2','halloween_event_stage2'] as $id) {
        check(count(Worldboss::petPool($id))>0,'Pool temático não vazio: '.$id);
        check(Worldboss::rewardPlan($id,1,20,false,true,50)['pet']!==null
            && Worldboss::rewardPlan($id,1,20,false,true,51)['pet']===null,'Chance temática: limite 50/51: '.$id);
    }
    foreach (['primal_season_worldboss','utopia_season_worldboss','dystopia_season_worldboss'] as $id)
        check(Worldboss::rewardPlan($id,11,20,true,true,1)['pet']===null,'Golpe final fora do top10 sem pet de passe: '.$id);
    $p=Worldboss::rewardPlan('default_event_stage1',11,20,false,true,1);
    check(!$p['eligible'] && $p['pet']===null && $p['extra']['quest_energy']===0,'Fora top10: só moedas/XP');
    $p=Worldboss::rewardPlan('default_event_stage1',11,20,true,true,50);
    check($p['eligible'] && $p['top'] && $p['pet']!==null && $p['extra']['quest_energy']>0,'Golpe final fora top10: equipamento/cupões/chance de pet');
    check(!Worldboss::rewardPlan('default_event_stage1',1,20,true,false,1)['eligible'],'Fuga sem equipamento, cupões ou pet');
    check(count(Worldboss::equipmentPool('default_event_stage1',true))===5
        && count(Worldboss::equipmentPool('default_event_stage1',false))===5,'Cinco peças Ironman e cinco Anfíbio');
    foreach (['primal_season_worldboss','utopia_season_worldboss','dystopia_season_worldboss'] as $id) {
        $pool=Worldboss::equipmentPool($id,true);
        check(count($pool)===5 && $pool===Worldboss::equipmentPool($id,false),'Cinco peças temáticas para ambas as faixas: '.$id);
    }
    if (in_array('--offline',$argv,true)) {echo "PASS: offline.\n";exit(0);}
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $hero=null;
    foreach (array_slice($argv,1) as $arg) {
        if (str_starts_with($arg,'--hero=')) $hero=substr($arg,7);
        else throw new InvalidArgumentException('Uso: test_worldboss.php [--offline | --hero="Nome"]');
    }
    $pdo=Db::pdo();
    $char=$hero!==null ? Db::row('SELECT * FROM `character` WHERE name = ?',[$hero]) : Db::row('SELECT * FROM `character` ORDER BY level DESC LIMIT 1');
    if (!$char) throw new RuntimeException('Sem herói disponível.');
    $cid=(int)$char['id'];$uid=(int)$char['user_id'];
    echo "MySQL: {$char['name']}; todas as alterações de jogo com rollback.\n";
    $pdo->beginTransaction();
    $before=Db::row('SELECT * FROM `character` WHERE id = ? FOR UPDATE',[$cid]);
    Db::exec('UPDATE `character` SET active_quest_id = 0, active_duel_id = 0, active_league_fight_id = 0, active_worldboss_attack_id = 0, active_dungeon_quest_id = 0,
        worldboss_event_id = 0, worldboss_event_attack_count = 0, max_quest_stage = 14, ts_active_multitasking_boost_expires = 0 WHERE id = ?',[$cid]);
    Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?',[$uid]);
    $eid=Worldboss::create('default_event_stage1',1,100000000,3600);
    Worldboss::action($cid,'assignWorldbossEvent',$eid);
    Db::exec('UPDATE `character` SET active_quest_id = 999999 WHERE id = ?',[$cid]);
    rejects(fn()=>Worldboss::action($cid,'startWorldbossAttack',$eid),'errStartWorldbossAttackActiveQuestFound');
    Db::exec('UPDATE `character` SET active_quest_id = 0 WHERE id = ?',[$cid]);
    rejects(fn()=>Worldboss::action($cid,'startWorldbossAttack',$eid,2),'errRequestInvalidParameter');
    Db::exec('UPDATE worldboss_event SET npc_hitpoints_current = 9999999 WHERE id = ?',[$eid]);
    rejects(fn()=>Worldboss::action($cid,'startWorldbossAttack',$eid,3),'errCreateWorldbossEventHitpointsToLow_9999999');
    Worldboss::action($cid,'startWorldbossAttack',$eid);
    $a=Worldboss::attach(['character'=>['id'=>$cid]],$cid)['worldboss_attack'];
    check($a['status']===1 && $a['iterations']===1 && $a['duration']>=60,'Ataque normal permitido abaixo de 10%, estado para login');
    rejects(fn()=>Worldboss::action($cid,'checkForWorldbossAttackComplete'),'errFinishNotYetCompleted');
    rejects(fn()=>Worldboss::action($cid,'startWorldbossAttack',$eid),'errStartWorldbossAttackActiveWorldbossAttackFound');
    Worldboss::action($cid,'abortWorldbossAttack');
    check((int)Db::value('SELECT active_worldboss_attack_id FROM `character` WHERE id = ?',[$cid])===0
        && (int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===100,'Cancelar liberta o herói e não cobra');
    Db::exec('UPDATE worldboss_event SET npc_hitpoints_current = npc_hitpoints_total WHERE id = ?',[$eid]);
    Worldboss::action($cid,'startWorldbossAttack',$eid,3);
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===99,'Ataque triplo custa 1 donut');
    $q=Db::row('SELECT id FROM quests WHERE character_id = ? AND status = 1 LIMIT 1',[$cid]);
    if ($q) rejects(fn()=>Character::load($cid)->startQuest((int)$q['id']),'errStartQuestActiveWorldbossAttackFound');
    $data=Worldboss::action($cid,'instantFinishWorldbossAttack');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===93,'Conclusão instantânea tripla custa 6 donuts');
    check($data['worldboss_attack']['status']===3 && count($data['battles'])===3 && $data['worldboss_attack']['total_damage']>0,'Três combates e dano persistido');
    $ids=array_column($data['battles'],'id');
    Worldboss::action($cid,'instantFinishWorldbossAttack');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===93
        && Worldboss::action($cid,'checkForWorldbossAttackComplete')['worldboss_attack']['battle_ids']===json_encode($ids),'Retry não cobra nem repete combate');
    Worldboss::action($cid,'finishWorldbossAttack',$eid);
    check((int)Db::value('SELECT attack_count FROM worldboss_event WHERE id = ?',[$eid])===3,'Contagem global de ataques');
    rejects(fn()=>Worldboss::action($cid,'finishWorldbossAttack',$eid),'errFinishInvalidWorldbossAttackStatus');
    // Intervenção determinística entre iterações; tudo fica na transação exterior de rollback.
    Db::exec('UPDATE `character` SET worldboss_event_id = 0 WHERE id = ?',[$cid]);
    $between=Worldboss::create('default_event_stage1',1,100000000,3600);
    Worldboss::action($cid,'startWorldbossAttack',$between,3);
    $partial=Db::row('SELECT * FROM worldboss_attack WHERE id = (SELECT active_worldboss_attack_id FROM `character` WHERE id = ?)',[$cid]);
    $event=Db::row('SELECT * FROM worldboss_event WHERE id = ?',[$between]);
    foreach (['npc_strength_percent','npc_critical_percent','npc_dodge_percent'] as $field)
        check((int)$event[$field]>=95 && (int)$event[$field]<=120,'Fator persistido no evento: '.$field);
    $oneFight=new ReflectionMethod(Worldboss::class,'fight');
    $oneFight->invoke(null,Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]),$partial,$event);
    $partial=Db::row('SELECT * FROM worldboss_attack WHERE id = ?',[$partial['id']]);
    check((int)$partial['status']===1 && count(json_decode($partial['battle_ids'],true))===1,
        'Uma iteração persiste o combate e deixa as restantes por resolver');
    rejects(fn()=>Worldboss::action($cid,'abortWorldbossAttack'),'errFinishInvalidWorldbossAttackStatus');
    // Simular dano confirmado de outro herói antes do segundo combate.
    Db::exec('UPDATE worldboss_event SET npc_hitpoints_current = 1234567 WHERE id = ?',[$between]);
    $balance=(int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid]);
    $resumed=Worldboss::action($cid,'instantFinishWorldbossAttack');
    $second=json_decode($resumed['battles'][1]['profile_b_stats'],true);
    check($second['hitpoints']===1234567,'Segundo combate relê a vida global após intervenção externa');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===$balance,
        'Retomar o triplo não volta a cobrar conclusão');
    $stored=Db::row('SELECT * FROM worldboss_event WHERE id = ?',[$between]);
    foreach (['npc_strength_percent','npc_critical_percent','npc_dodge_percent'] as $field)
        check($stored[$field]===$event[$field],'Fator não muda entre combates: '.$field);
    Worldboss::action($cid,'finishWorldbossAttack',$between);
    Db::exec('UPDATE `character` SET worldboss_event_id = 0 WHERE id = ?',[$cid]);
    $interrupted=Worldboss::create('default_event_stage1',1,100000000,3600);
    Worldboss::action($cid,'startWorldbossAttack',$interrupted,3);
    $partial=Db::row('SELECT * FROM worldboss_attack WHERE id = (SELECT active_worldboss_attack_id FROM `character` WHERE id = ?)',[$cid]);
    $oneFight->invoke(null,Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]),$partial,Db::row('SELECT * FROM worldboss_event WHERE id = ?',[$interrupted]));
    Db::exec("UPDATE worldboss_event SET npc_hitpoints_current = 0, status = 4, winning_attacker_name = 'Outro herói' WHERE id = ?",[$interrupted]);
    $stopped=Worldboss::action($cid,'instantFinishWorldbossAttack');
    check(count($stopped['battles'])===1 && $stopped['worldboss_attack']['iterations']===1
        && Db::value('SELECT winning_attacker_name FROM worldboss_event WHERE id = ?',[$interrupted])==='Outro herói',
        'Morte entre combates preserva o primeiro, cancela restantes e não substitui golpe final');
    Worldboss::action($cid,'finishWorldbossAttack',$interrupted);
    // Derrota global e pet: último golpe determinístico com 1 HP, sem depender do RNG.
    Db::exec('UPDATE `character` SET worldboss_event_id = 0 WHERE id = ?',[$cid]);
    $season=Worldboss::create('primal_season_worldboss',1,1,3600);
    Worldboss::action($cid,'startWorldbossAttack',$season);
    $data=Worldboss::action($cid,'instantFinishWorldbossAttack');
    // A esquiva pode impedir a vitória rara; repetir até derrotar sem alterar os danos.
    for ($i=0;$i<10 && (int)Db::value('SELECT npc_hitpoints_current FROM worldboss_event WHERE id = ?',[$season])>0;$i++) {
        Worldboss::action($cid,'finishWorldbossAttack',$season);
        Worldboss::action($cid,'startWorldbossAttack',$season);
        $data=Worldboss::action($cid,'instantFinishWorldbossAttack');
    }
    check((int)Db::value('SELECT status FROM worldboss_event WHERE id = ?',[$season])===4,'Golpe final encerra evento');
    $pending=Worldboss::attach(['character'=>['id'=>$cid]],$cid);
    $pendingEvent=array_values(array_filter($pending['worldboss_events'],fn($e)=>$e['id']===$season))[0];
    check($pendingEvent['status']===2 && $pending['worldboss_rewards']===[],'Evento pendente enquanto o ataque não for fechado');
    Worldboss::action($cid,'finishWorldbossAttack',$season);
    $state=Worldboss::attach(['character'=>['id'=>$cid],'items'=>[]],$cid);
    $r=$state['worldboss_rewards'][0];
    check($r['sidekick_item_id']===0 && $r['item_id']>0 && count($state['items'])===1,'Preview do equipamento da temporada, sem pet');
    $previewIds=array_column($state['items'],'id');
    $state2=Worldboss::attach(['character'=>['id'=>$cid],'items'=>[]],$cid);
    check(array_column($state2['items'],'id')===$previewIds,'Repetir leitura não gera novos objetos');
    $inv=Db::row('SELECT * FROM inventory WHERE character_id = ?',[$cid]);
    if (!$inv) throw new RuntimeException('Herói precisa de inventário para o teste.');
    $sets=[];for($i=1;$i<=18;$i++)$sets[]='bag_item'.$i.'_id = 999999';
    Db::exec('UPDATE inventory SET '.implode(',',$sets).' WHERE character_id = ?',[$cid]);
    $xp=(int)Db::value('SELECT xp FROM `character` WHERE id = ?',[$cid]);
    rejects(fn()=>Worldboss::action($cid,'claimWorldbossEventRewards',$season),'errInventoryNoEmptySlot');
    check((int)Db::value('SELECT xp FROM `character` WHERE id = ?',[$cid])===$xp && (int)Db::value('SELECT claimed FROM worldboss_reward WHERE id = ?',[$r['id']])===0,'Mochila cheia não entrega prémios parciais');
    Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?',[$cid]);
    $equipment=Db::row('SELECT * FROM items WHERE id=?',[$r['item_id']]);
    $heroLevel=(int)Db::value('SELECT level FROM `character` WHERE id=?',[$cid]);
    $req=(int)$equipment['required_level'];
    $sum=0;$count=0;
    foreach (['stamina','strength','critical_rating','dodge_rating'] as $stat) {
        $value=(int)$equipment['stat_'.$stat];$sum+=$value;if ($value>0)$count++;
    }
    $kyber=\HeroZero\Hideout::kyberneticBonuses($cid)['item_flat'];
    check($req>=max(1,$heroLevel-4) && $req<=$heroLevel && $count>=3 && $count<=4
        && $sum===(int)ceil(($req*1.6+2+$req**2*0.004)*2.3)+$kyber,
        'Equipamento do vilão usa nível, multiplicador épico e budget da loja');
    $pets=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id = ?',[$cid]);
    $vouchers=(int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id = ?',[$uid]);
    Worldboss::action($cid,'claimWorldbossEventRewards',$season);
    check((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id = ?',[$cid])===$pets
        && (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?',[$cid])===(int)$r['item_id'],'Objeto entregue sem pet no vilão associado ao passe');
    check((int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id = ?',[$uid])===$vouchers+2,'Energia e treino entregues como cupões');
    check((int)Db::value('SELECT xp FROM `character` WHERE id = ?',[$cid])===$xp+(int)$r['xp'],'XP entregue no resgate');
    rejects(fn()=>Worldboss::action($cid,'claimWorldbossEventRewards',$season),'errClaimWorldbossEventRewardsInvalidStatus');
    // Fixture determinística de pet normal: verificar entrega e retry sem depender de sorteio.
    $petEvent=Worldboss::create('default_event_stage1',1,1,3600);
    Worldboss::action($cid,'assignWorldbossEvent',$petEvent);
    Db::exec('UPDATE worldboss_event SET status=4,npc_hitpoints_current=0 WHERE id=?',[$petEvent]);
    $petId=$normalPets[0];
    $makeItem=new ReflectionMethod(Worldboss::class,'makeItem');
    $preview=$makeItem->invoke(null,$cid,$petId);
    $petPreview=Db::row('SELECT * FROM items WHERE id=?',[$preview]);
    $petQuality=(int)$petPreview['quality'];
    $heroLevel=(int)Db::value('SELECT level FROM `character` WHERE id=?',[$cid]);
    $mult=match($petQuality){4=>4.2,3=>2.5,default=>1.2};
    foreach (['stamina'=>0.28,'strength'=>0.43,'critical_rating'=>0.18,'dodge_rating'=>0.11] as $stat=>$ratio) {
        $mid=(int)round($heroLevel*$mult*$ratio);$lo=max(1,(int)round($mid*0.85));$hi=max($lo,(int)round($mid*1.15));
        check((int)$petPreview['stat_'.$stat]>=$lo && (int)$petPreview['stat_'.$stat]<=$hi,
            'Pet do vilão usa scaling da loja e variação de 15%: '.$stat);
    }
    Db::exec('INSERT INTO worldboss_reward (worldboss_event_id,character_id,game_currency,xp,item_id,sidekick_item_id,quest_energy,training_sessions,rewards,claimed)
        VALUES (?,?,0,0,0,?,0,0,?,0)',[$petEvent,$cid,$preview,'{}']);
    $petsBefore=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
    $state=Worldboss::attach(['character'=>['id'=>$cid],'items'=>[]],$cid);
    check($state['items'][0]['id']===$preview,'Preview persistido de pet incluído na resposta');
    Worldboss::action($cid,'claimWorldbossEventRewards',$petEvent);
    check((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$petsBefore+1
        && !Db::row('SELECT id FROM items WHERE id=?',[$preview]),'Pet normal entregue e preview consumido');
    $delivered=Db::row('SELECT * FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
    foreach (['stamina','strength','critical_rating','dodge_rating'] as $stat)
        check((int)$delivered['stat_base_'.$stat]===(int)$petPreview['stat_'.$stat],
            'Resgate preserva atributo mostrado no preview: '.$stat);
    rejects(fn()=>Worldboss::action($cid,'claimWorldbossEventRewards',$petEvent),'errClaimWorldbossEventRewardsInvalidStatus');
    // Timeout durante ataque: libertar sem cobrar o skip e sem batalhas.
    $expired=Worldboss::create('default_event_stage2',2,100000,3600);
    Worldboss::action($cid,'startWorldbossAttack',$expired);
    Db::exec('UPDATE worldboss_event SET ts_end = ? WHERE id = ?',[GameTime::now(),$expired]);
    $premium=(int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid]);
    Worldboss::action($cid,'instantFinishWorldbossAttack');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===$premium
        && (int)Db::value('SELECT active_worldboss_attack_id FROM `character` WHERE id = ?',[$cid])===0,'Evento expirado liberta ataque sem custo');
    Worldboss::action($cid,'claimWorldbossEventRewards',$expired,1,true);
    $normal=Worldboss::create('default_event_stage1',1,100000,3600);
    Worldboss::action($cid,'startWorldbossAttack',$normal);
    Db::exec('UPDATE worldboss_attack SET ts_complete = ? WHERE id = (SELECT active_worldboss_attack_id FROM `character` WHERE id = ?)',[GameTime::now(),$cid]);
    Worldboss::action($cid,'checkForWorldbossAttackComplete');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===$premium,'Conclusão por tempo não cobra donuts');
    $boot=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','user_id'=>$uid,'user_session_id'=>(string)Db::value('SELECT session_id FROM `user` WHERE id = ?',[$uid])]);
    check(is_array($boot) && ($boot['worldboss_attack']['status'] ?? 0)===3 && count($boot['battles'] ?? [])>0,'Login contém ataque concluído e batalhas');
    $live=Live::accountState($uid);
    check(($live['worldboss_attack']['status'] ?? 0)===3 && count($live['battles'] ?? [])>0,'Estado normal contém ataque concluído e batalhas');
    $response=Router::dispatch('finishWorldbossAttack',['action'=>'finishWorldbossAttack','user_id'=>$uid,'worldboss_event_id'=>$normal]);
    check($response['character']['active_worldboss_attack_id']===0 && $response['worldboss_attack']['status']===3
        && count($response['battles'])>0,'Handler real fecha ataque e preserva resposta de combate');
    $second=Db::row('SELECT * FROM `character` WHERE id <> ? ORDER BY level DESC LIMIT 1',[$cid]);
    if ($second) {
        $cid2=(int)$second['id']; $uid2=(int)$second['user_id'];
        Db::exec('UPDATE `character` SET active_quest_id = 0, active_duel_id = 0, active_league_fight_id = 0,
            active_worldboss_attack_id = 0, active_dungeon_quest_id = 0, worldboss_event_id = 0,
            max_quest_stage = 14 WHERE id IN (?, ?)',[$cid,$cid2]);
        Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?',[$uid2]);
        $shared=Worldboss::create('utopia_season_worldboss',1,100000000,3600);
        Worldboss::action($cid,'startWorldbossAttack',$shared);
        Worldboss::action($cid,'instantFinishWorldbossAttack');
        Worldboss::action($cid,'finishWorldbossAttack',$shared);
        $hp1=(int)Db::value('SELECT npc_hitpoints_current FROM worldboss_event WHERE id = ?',[$shared]);
        Worldboss::action($cid2,'startWorldbossAttack',$shared);
        Worldboss::action($cid2,'instantFinishWorldbossAttack');
        Worldboss::action($cid2,'finishWorldbossAttack',$shared);
        $hp2=(int)Db::value('SELECT npc_hitpoints_current FROM worldboss_event WHERE id = ?',[$shared]);
        check($hp2<$hp1 && $hp1<100000000 && (int)Db::value('SELECT attack_count FROM worldboss_event WHERE id = ?',[$shared])===2,
            'Dois heróis partilham vida e contagem de ataques');
        Db::exec('UPDATE worldboss_event SET npc_hitpoints_current = 1 WHERE id = ?',[$shared]);
        for($i=0;$i<10 && (int)Db::value('SELECT status FROM worldboss_event WHERE id = ?',[$shared])===1;$i++) {
            Worldboss::action($cid2,'startWorldbossAttack',$shared);
            Worldboss::action($cid2,'instantFinishWorldbossAttack');
            Worldboss::action($cid2,'finishWorldbossAttack',$shared);
        }
        $event=Db::row('SELECT * FROM worldboss_event WHERE id = ?',[$shared]);
        check((int)$event['status']===4 && (int)$event['top_attacker_character_id']===$cid2
            && $event['winning_attacker_name']===$second['name'],'Golpe final pertence a quem leva a vida a zero, mesmo sendo líder');
        $rankState=Worldboss::attach(['character'=>['id'=>$cid]],$cid);
        $rankEvent=array_values(array_filter($rankState['worldboss_events'],fn($e)=>$e['id']===$shared))[0];
        check($rankEvent['ranking']===2 && $rankState['worldboss_rewards'][0]['sidekick_item_id']===0,
            'Penúltimo atacante mantém ranking e não recebe pet pelo golpe final');
        rejects(fn()=>Worldboss::action($cid,'claimWorldbossEventRewards',$normal),'errClaimWorldbossEventRewardsInvalidStatus');
    } else echo "SKIP: sem segundo herói para verificar vida partilhada.\n";
    check($pdo->inTransaction(),'Savepoints preservam transação de teste');
    $pdo->rollBack();
    check(Db::row('SELECT * FROM `character` WHERE id = ?',[$cid])===$before,'Rollback restaura personagem');
    check(!Db::row('SELECT id FROM worldboss_event WHERE id = ?',[$normal]),'Nenhum vilão de teste persistido');
    echo "PASS: integração MySQL com rollback.\n";
} catch(Throwable $e) {
    if($pdo && $pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$e->getMessage()."\n");exit(1);
}
