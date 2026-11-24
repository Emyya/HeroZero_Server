<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Missiles,Db,Fight,Character,GameError};
function check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "OK: $label\n";}
function reject(callable $fn,string $label): void {try{$fn();}catch(GameError $e){echo "OK: $label\n";return;}throw new RuntimeException($label);}
check(Missiles::damage(10)===39 && Missiles::damage(335)===1307,'dano com arredondamento JavaScript');
check(Missiles::damage(100,['level'=>20,'stage1_skill_id'=>0,'stage2_skill_id'=>10,'stage3_skill_id'=>0])===429,'bónus passivo de dano do pet pelo efeito 104');
$tpl=Missiles::template('missiles_headache_pills',25);
check($tpl['type']===8 && $tpl['charges']===100 && $tpl['stat_strength']===0 && $tpl['stat_weapon_damage']===0,'pacote de munição distinto de arma');
$base=['profile'=>'a','hitpoints'=>100000,'damage_normal'=>1,'criticalrating'=>1,'dodgerating'=>1];
$other=$base;$other['profile']='b';
$a=$base;$a['missiles']=[['id'=>1,'index'=>1,'charges'=>1,'damage'=>39],['id'=>2,'index'=>2,'charges'=>2,'damage'=>78]];
$fight=new Fight($a,$other);$fight->fight();$rounds=$fight->rounds();
$used=array_values(array_filter($rounds,static fn($r)=>($r['a']??'')==='a' && isset($r['m'])));
check(array_column($used,'m')===[1,2,2] && $fight->missileUsage('a')===[1=>1,2=>2],'consumo limitado e índices da aparência original');
foreach($used as $r) if(($r['r']??0)!==1) check($r['v']>=30,'dano de arremesso incluído no golpe');
$plain=new Fight($base,$other);$plain->fight();check($plain->missileUsage('a')===[],'sem munição mantém combate normal');
$name=$argv[1]??null;
$cid=$name!==null?(int)Db::value('SELECT id FROM `character` WHERE name = ?',[$name]):(int)Db::value('SELECT i.character_id FROM inventory i JOIN `character` c ON c.id = i.character_id JOIN `user` u ON u.id = c.user_id LIMIT 1');
if(!$cid)throw new RuntimeException('Herói inexistente; indica o nome.');
$pdo=Db::pdo();
Illuminate\Support\Facades\DB::connection()->setPdo($pdo);
Illuminate\Support\Facades\DB::connection()->setReadPdo($pdo);
$pdo->beginTransaction();
try {
 $charBefore=Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]);$uid=(int)$charBefore['user_id'];
 $invBefore=Db::row('SELECT * FROM inventory WHERE character_id = ?',[$cid]);
 Db::exec('UPDATE `character` SET level = 100 WHERE id = ?',[$cid]);
 $sets=['missiles_item_id = 0'];for($i=1;$i<=4;$i++)$sets[]="missiles{$i}_item_id = -1";for($i=1;$i<=18;$i++)$sets[]="bag_item{$i}_id = 0";
 Db::exec('UPDATE inventory SET '.implode(',',$sets).' WHERE character_id = ?',[$cid]);
 $id=Missiles::give($cid,'missiles_headache_pills');$reserve=Missiles::give($cid,'missiles_table_tennis_balls');
 check((int)Db::value('SELECT type FROM items WHERE id = ?',[$id])===8,'entrega cria tipo 8 na mochila');
 reject(fn()=>Missiles::operate($cid,'moveAmmoBeltItem',['item_id'=>$reserve,'target_slot'=>1,'equip'=>true]),'vaga bloqueada rejeitada');
 Db::exec('UPDATE `character` SET level = 24 WHERE id = ?',[$cid]);
 reject(fn()=>Missiles::operate($cid,'upgradeAmmoBelt',[]),'cinto exige nível 25');
 Db::exec('UPDATE `character` SET level = 100 WHERE id = ?',[$cid]);
 reject(fn()=>Missiles::operate($cid,'moveInventoryItem',['item_id'=>2147483647,'target_slot'=>8]),'ID sem propriedade rejeitado');

 Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?',[$uid]);
 Missiles::operate($cid,'upgradeAmmoBelt',[]);check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===90,'desbloqueio cobra 10 donuts');
 Missiles::operate($cid,'moveAmmoBeltItem',['item_id'=>$reserve,'target_slot'=>1,'equip'=>true]);
 check((int)Db::value('SELECT missiles1_item_id FROM inventory WHERE character_id = ?',[$cid])===$reserve,'equip=true envia para o cinto');
 Missiles::operate($cid,'moveAmmoBeltItem',['item_id'=>$reserve,'target_slot'=>10,'equip'=>false]);
 check((int)Db::value('SELECT bag_item2_id FROM inventory WHERE character_id = ?',[$cid])===$reserve,'equip=false retira do cinto para mochila');
 Missiles::operate($cid,'moveAmmoBeltItem',['item_id'=>$reserve,'target_slot'=>1,'equip'=>true]);
 Missiles::operate($cid,'moveInventoryItem',['item_id'=>$id,'target_slot'=>8]);
 Db::exec('UPDATE items SET charges = 1 WHERE id = ?',[$id]);
 $snapshot=Missiles::snapshot($cid);check($snapshot[0]['id']===$id && $snapshot[1]['id']===$reserve,'snapshot equipado e reserva em ordem');
 $appearance=Character::load($cid)->battleAppearance();check($appearance['missiles']==='missiles_headache_pills' && $appearance['missiles1']==='missiles_table_tennis_balls','aparência conserva todos os identifiers');
 Missiles::consume($cid,[$id=>1,$reserve=>2]);
 check(!Db::value('SELECT id FROM items WHERE id = ?',[$id]),'pacote esgotado eliminado');
 check((int)Db::value('SELECT missiles_item_id FROM inventory WHERE character_id = ?',[$cid])===$reserve && (int)Db::value('SELECT charges FROM items WHERE id = ?',[$reserve])===98,'auto-recarga e conservação das cargas restantes');
 reject(fn()=>Missiles::transaction([$cid],fn()=>Missiles::consume($cid,[$reserve=>1,2147483647=>1])),'falha parcial de consumo reverte operação inteira');
 check((int)Db::value('SELECT charges FROM items WHERE id = ?',[$reserve])===98,'savepoint preserva cargas anteriores à falha');

 reject(fn()=>Missiles::operate($cid,'moveInventoryItem',['item_id'=>$reserve,'target_slot'=>6]),'mísseis não equipam como arma normal');
 Db::exec('UPDATE `user` SET premium_currency = 0 WHERE id = ?',[$uid]);
 reject(fn()=>Missiles::operate($cid,'upgradeAmmoBelt',[]),'saldo insuficiente reverte desbloqueio');
 check((int)Db::value('SELECT missiles2_item_id FROM inventory WHERE character_id = ?',[$cid])===-1,'posição continua bloqueada');
 // Compra direta: um item real de loja e destino 101 escolhido pelo cliente.
 $shopId=Missiles::give($cid,'missiles_headache_pills');
 Db::exec('UPDATE inventory SET bag_item1_id = 0, shop_item1_id = ? WHERE character_id = ?',[$shopId,$cid]);
 Character::load($cid)->buyShopItem($shopId,101);
 check((int)Db::value('SELECT missiles1_item_id FROM inventory WHERE character_id = ?',[$cid])===$shopId,'compra direta para vaga do cinto');
 $season=new ReflectionMethod(HeroZero\Season::class,'giveSeasonItem');$season->invoke(null,$cid,'missiles_season_gods_1');
 check((int)Db::value("SELECT type FROM items WHERE character_id = ? AND identifier = 'missiles_season_gods_1' ORDER BY id DESC LIMIT 1",[$cid])===8,'prémio da temporada gera munição');
 // Duelo real: criação, consumo e retoma na mesma transação exterior.
 $opponentId=(int)Db::value('SELECT c.id FROM `character` c JOIN `user` u ON u.id = c.user_id WHERE c.id <> ? LIMIT 1',[$cid]);
 if (!$opponentId) throw new RuntimeException('O teste de duelo precisa de duas contas existentes.');
 Db::exec('UPDATE `character` SET stat_base_stamina = 100000, stat_base_strength = 100000, stat_base_critical_rating = 1, stat_base_dodge_rating = 1 WHERE id IN (?,?)',[$cid,$opponentId]);
 Db::exec('UPDATE `character` SET active_duel_id = 0, duel_stamina = 100, max_duel_stamina = 100 WHERE id = ?',[$cid]);
 $defenderMissile=Missiles::give($opponentId,'missiles_headache_pills');
 Db::exec('UPDATE inventory SET missiles_item_id = ? WHERE character_id = ?',[$defenderMissile,$opponentId]);
 $duelHandler=require dirname(__DIR__,2).'/server-laravel/actions/startDuel.php';
 $response=$duelHandler(['user_id'=>$uid,'character_id'=>$opponentId]);
 check((int)Db::value('SELECT charges FROM items WHERE id = ?',[$defenderMissile])===100,'defensor de duelo não consome munição');
 $battleRounds=json_decode($response['battle']['rounds'],true);
 $usage=[];
 foreach($battleRounds['rounds'] as $round) if(($round['a']??'')==='a' && isset($round['m'])) {
     $itemId=$round['m']===1?$reserve:$shopId;
     $usage[$itemId]=($usage[$itemId]??0)+1;
 }
 check($usage!==[],'duelo persiste rondas com mísseis');
 foreach($usage as $itemId=>$count) {
     $initial=$itemId===$reserve?98:100;
     check((int)Db::value('SELECT charges FROM items WHERE id = ?',[$itemId])===max(0,$initial-$count),'duelo debita cargas usadas');
 }
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 $resumed=$duelHandler(['user_id'=>$uid,'character_id'=>$opponentId]);
 check($resumed['duel']['id']===$response['duel']['id'] && Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'retoma não repete consumo');
 check(in_array($reserve,array_map('intval',array_column($response['items'],'id')),true),'resposta de duelo inclui objetos reais após consumo');
 Db::exec('UPDATE `character` SET active_league_fight_id = 0, league_group_id = 100000, league_stamina = 100, max_league_stamina = 100, league_fight_count = 0, honor = 100000, league_opponents = ? WHERE id = ?',[json_encode([$opponentId]),$cid]);
 $leagueHandler=require dirname(__DIR__,2).'/server-laravel/actions/leagueMissed.php';
 $leagueResponse=$leagueHandler(['action'=>'startLeagueFight','user_id'=>$uid,'character_id'=>$opponentId]);
 check((int)Db::value('SELECT charges FROM items WHERE id = ?',[$defenderMissile])===100,'defensor de liga não consome munição');
 $leagueRounds=json_decode($leagueResponse['battle']['rounds'],true);
 check(count(array_filter($leagueRounds['rounds'],static fn($r)=>($r['a']??'')==='a' && isset($r['m'])))>0,'liga persiste rondas com munição');
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 $leagueResumed=$leagueHandler(['action'=>'startLeagueFight','user_id'=>$uid,'character_id'=>$opponentId]);
 check($leagueResumed['league_fight']['id']===$leagueResponse['league_fight']['id'] && Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'retoma de liga não repete consumo');
 $questId=(int)Db::value('SELECT id FROM quests WHERE character_id = ? LIMIT 1',[$cid]);
 if (!$questId) throw new RuntimeException('O herói precisa de uma missão para o teste.');
 Db::exec('UPDATE quests SET type = 2, status = 2, ts_complete = 0, fight_difficulty = 1, fight_battle_id = 0 WHERE id = ?',[$questId]);
 Db::exec('UPDATE `character` SET active_quest_id = ? WHERE id = ?',[$questId,$cid]);
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 $mission=Character::load($cid)->checkActiveQuestComplete();
 check(Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'missão de combate não consome munição');
 check((int)$mission['fight_battle_id']>0,'missão de combate grava batalha');
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 $missionAgain=Character::load($cid)->checkActiveQuestComplete();
 check($missionAgain['fight_battle_id']===$mission['fight_battle_id'] && Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'missão concluída não gasta munição novamente');
 Db::exec('UPDATE `character` SET training_energy = 100, max_training_energy = 100, ts_last_training_energy_change = ? WHERE id = ?',[HeroZero\GameTime::now(),$cid]);
 Db::exec('INSERT INTO training (character_id,status,stat_type,ts_creation,ts_complete,ts_end,iterations,used_resources,training_quest_id) VALUES (?,2,21,?,0,?,0,0,0)',[$cid,HeroZero\GameTime::now(),HeroZero\GameTime::now()+3600]);
 $trainingChar=Character::load($cid);
 $fightQuest=array_values(array_filter($trainingChar->trainingQuestsData(),static fn($q)=>(int)$q['type']===2))[0];
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 $trainingResult=$trainingChar->startTrainingQuest((int)$fightQuest['id']);
 check(Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'treino de combate não consome munição');
 check((int)$trainingResult['quest']['fight_battle_id']>0,'treino de combate grava batalha');
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 reject(fn()=>Character::load($cid)->startTrainingQuest((int)$fightQuest['id']),'treino ativo rejeita início duplicado');
 check(Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'pedido duplicado de treino não consome');
 Db::exec('UPDATE `character` SET level = 100, story_dungeon_state = ?, active_quest_id = 0, active_duel_id = 0, active_worldboss_attack_id = 0 WHERE id = ?',[json_encode(['active'=>null,'completed'=>['1_1','1_2','1_3','1_4']]),$cid]);
 HeroZero\StoryDungeon::start($cid,1,5);
 $chargesBefore=Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId]);
 HeroZero\StoryDungeon::finish($cid,1,5,false);
 check(Db::rows('SELECT id, charges FROM items WHERE id IN (?,?)',[$reserve,$shopId])===$chargesBefore,'missão especial não consome munição');
 $story=HeroZero\StoryDungeon::ensure($cid);
 check((int)$story['active']['battle_id']>0,'dungeon de história grava batalha sem débito de munição');
 $pdo->rollBack();check(Db::row('SELECT * FROM inventory WHERE character_id = ?',[$cid])===$invBefore,'rollback restaura inventário real');
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo "PASS: offline e integração MySQL; alterações revertidas.\n";
