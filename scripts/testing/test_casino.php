<?php
declare(strict_types=1);
// Offline por defeito; --db [herói] testa MySQL com rollback, sem ativação persistente.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Casino,Db,GameTime,GameError,Live,Character,UserVouchers,Sidekick};
function checkCasino(bool $ok,string $label):void {if(!$ok) throw new RuntimeException($label);echo "OK: $label\n";}
function rejectsCasino(callable $fn,string $contains):void {
    try {$fn();} catch(Throwable $e) {checkCasino(str_contains($e->getMessage(),$contains),'rejeita '.$contains);return;}
    throw new RuntimeException('Aceitou operação: '.$contains);
}
try {
 $catalogs=Casino::catalogs();$ref=Casino::reference();
 checkCasino(count($catalogs)===34,'34 catálogos');
 foreach($catalogs as $name=>$catalog) {
  foreach(['items'=>[1,2,3,4,5,6,7,8],'sidekicks'=>[9]] as $key=>$types) foreach($catalog[$key] as $id)
   checkCasino(isset($ref['item_templates'][$id]) && in_array((int)$ref['item_templates'][$id]['type'],$types,true),"$name: $id válido");
  foreach($catalog['optical'] as $id) if(!isset($ref['optical_changes'][$id])) throw new RuntimeException("Aparência desconhecida $id");
  $weights=Casino::weights($name);checkCasino(array_sum($weights)>0,"$name: pool positivo");
  foreach($weights as $type=>$weight) checkCasino($weight>0 && isset(Casino::TYPES[$type]),'tipo e peso válidos');
 }
 checkCasino(Casino::choose([1=>2,2=>3],2)===1 && Casino::choose([1=>2,2=>3],3)===2,'fronteira do sorteio ponderado');
 checkCasino(Casino::numericReward(1,20,3,'great_rewards')['coins']===12000,'prémios locais fixados');
 $boosters=Casino::baseRewardVariants(3);
 checkCasino(count($boosters)===6,'seis energéticos: três tipos de 50% com 3 e 14 dias');
 foreach(['booster_quest3','booster_stats3','booster_work3'] as $id) foreach([3,14] as $days)
  checkCasino(in_array(['booster'=>['booster_id'=>$id,'days'=>$days]],$boosters,true),"catálogo inclui $id por $days dias");
 checkCasino(Casino::baseRewardVariants(7)===[['quest_energy'=>10],['quest_energy'=>60]],'catálogo inclui 10 e 60 energias');
 checkCasino(Casino::baseRewardVariants(8)===[['training_sessions'=>4],['training_sessions'=>40]],'catálogo inclui 4 e 40 unidades');
 foreach($catalogs as $name=>$catalog) foreach([3,7,8] as $type) foreach([1,2,3] as $quality) {
  $reward=Casino::numericReward($type,100,$quality,$name);
  checkCasino(in_array($reward,Casino::baseRewardVariants($type,$name),true),"$name: variantes base não multiplicadas pelo evento");
  $value=$type===3 ? $reward['booster']['days'] : reset($reward);
  $expected=$quality===3 ? [3=>14,7=>($name==='no_cash_and_xp'?100:60),8=>40][$type] : [3=>3,7=>($name==='no_cash_and_xp'?20:10),8=>4][$type];
  checkCasino($value===$expected,"$name: qualidade $quality corresponde ao valor $expected");
 }
 $wire=Casino::clientRewards(['{"stat_points":2}','{"statPoints":20}','{"booster":{"booster_id":"booster_quest3","days":14}}']);
 checkCasino(json_decode($wire[0],true)===['statPoints'=>2] && json_decode($wire[1],true)===['statPoints'=>20],'previews novos e antigos usam statPoints sem perder quantidades');
 checkCasino(json_decode($wire[2],true)['booster']['days']===14,'normalização preserva energético de 14 dias');
 $captured=Casino::capturedNoCashRewards();
 checkCasino(count(array_merge(...array_column($captured,'rewards')))===25,'no_cash_and_xp: 25 entradas capturadas');
 checkCasino(array_keys(Casino::weights('no_cash_and_xp'))===[2,3,4,5,7,8],'no_cash_and_xp: apenas categorias capturadas');
 foreach([1,2,3] as $quality) {
  checkCasino(Casino::numericReward(5,12,$quality,'no_cash_and_xp')['statPoints']===($quality===3?20:2),'habilidades oficiais por qualidade');
 }
 if(!in_array('--db',$argv,true)) {echo "PASS: casino offline. Integração: --db [herói], com rollback.\n";exit;}
 $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
 $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $pdo=Db::pdo();$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
 $name=$argv[array_search('--db',$argv,true)+1] ?? null;
 $hero=$name ? Db::row('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]) : Db::row('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
 if(!$hero) throw new RuntimeException('Herói não encontrado.');
 $cid=(int)$hero['id'];$uid=(int)$hero['user_id'];
 $props=[];foreach(['offset','epoch','cachedUptime','cachedAt'] as $key) {$p=new ReflectionProperty(GameTime::class,$key);$props[$key]=[$p,$p->getValue()];}
 $setTime=static function(int $now)use($props):void {
  $props['cachedAt'][0]->setValue(null,null);$uptime=GameTime::uptime();
  $props['offset'][0]->setValue(null,0);$props['epoch'][0]->setValue(null,$now-$uptime);
 };
 $pdo->beginTransaction();
 try {
  $now=2000000000;$setTime($now);
  Db::exec('UPDATE casino_events SET enabled=0');
  Db::exec('UPDATE `character` SET level=100,casino_state=NULL,unused_resources=?,used_resources=?,slotmachine_spin_count=0,ts_last_slotmachine_refill=? WHERE id=?',['{}','{}',$now,$cid]);
  Db::exec('UPDATE `user` SET premium_currency=100 WHERE id=?',[$uid]);
  checkCasino(Casino::current()===null && Casino::hub()===[],'fechado sem agendamento');
  rejectsCasino(fn()=>Casino::spin($cid,1),'errInvalidFunction');
  $id=Casino::schedule('normal',1,3,$now+86400);
  checkCasino(Casino::current()===null,'agendamento não abre antes do início');
  rejectsCasino(fn()=>Casino::schedule('go_crazy',2,3,$now+2*86400),'Já existe');
  $setTime($now+86400);$event=Casino::current();
  checkCasino((int)$event['id']===$id && (int)$event['ts_end']===$now+4*86400,'abre exatamente no início por três dias');
  checkCasino(count(Casino::hub())===1,'evento no hub');
  Db::exec('UPDATE `character` SET unused_resources=? WHERE id=?',['{"2":10,"4":100}',$cid]);
  $active=Casino::spin($cid,5);
  checkCasino(count($active['prizes'])===5 && count($active['rewards'])===5,'multigiro gera cinco prémios');
  checkCasino((int)json_decode(Db::value('SELECT unused_resources FROM `character` WHERE id=?',[$cid]),true)[2]===5,'giros grátis têm prioridade');
  checkCasino((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===100,'não debita donuts com recursos');
  rejectsCasino(fn()=>Casino::spin($cid,1),'HasActiveSpin');
  Casino::apply($cid,true);rejectsCasino(fn()=>Casino::apply($cid,false),'HasNoActiveSpin');
  Db::exec('UPDATE `character` SET unused_resources=? WHERE id=?',['{"2":2,"4":100}',$cid]);
  Casino::spin($cid,10);Casino::apply($cid,true);
  $resources=json_decode(Db::value('SELECT unused_resources FROM `character` WHERE id=?',[$cid]),true);
  checkCasino(($resources[2] ?? 0)===2 && !isset($resources[4]),'lote usa dez jetons por giro sem misturar fontes');
  Db::exec('UPDATE `character` SET unused_resources=? WHERE id=?',['{}',$cid]);
  Casino::spin($cid,1);Casino::apply($cid,true);
  checkCasino((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===99,'debita um donut');
  rejectsCasino(fn()=>Casino::spin($cid,2),'InvalidParameter');
  checkCasino(Casino::constants()['casino_max_daily_spins']===0,'cliente recebe limite ilimitado');
  Db::exec('UPDATE `character` SET slotmachine_spin_count=99999 WHERE id=?',[$cid]);
  Casino::spin($cid,5);Casino::apply($cid,true);
  Casino::spin($cid,10);Casino::apply($cid,true);
  checkCasino((int)Db::value('SELECT slotmachine_spin_count FROM `character` WHERE id=?',[$cid])===100014,'lotes de cinco/dez ultrapassam 99999 giros');
  $setTime($now+2*86400);Casino::syncDaily($cid);
  checkCasino((int)Db::value('SELECT slotmachine_spin_count FROM `character` WHERE id=?',[$cid])===0,'reset diário no relógio interno');
  Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);
  rejectsCasino(fn()=>Casino::spin($cid,1),'NotEnough');
  checkCasino(Casino::state($cid)['active']===null && (int)Db::value('SELECT slotmachine_spin_count FROM `character` WHERE id=?',[$cid])===0,'falha sem saldo não deixa giro ou contagem');
  Db::exec('UPDATE `user` SET premium_currency=100 WHERE id=?',[$uid]);
  Db::exec('UPDATE `character` SET level=11 WHERE id=?',[$cid]);rejectsCasino(fn()=>Casino::spin($cid,1),'InvalidFunction');
  Db::exec('UPDATE `character` SET level=100 WHERE id=?',[$cid]);
  // Gera cada classe por método real, mantendo aleatoriedade apenas na seleção do identificador.
  $prizeMethod=new ReflectionMethod(Casino::class,'prize');
  $save=static function(array $prizes,array $event)use($cid):void {
   $active=['event'=>$event,'slots'=>[1,1,1],'reward_type'=>$prizes[0]['type'],'reward_quality'=>3,'amount'=>count($prizes),'prizes'=>$prizes,'rewards'=>array_map(fn($p)=>json_encode($p['reward']),$prizes)];
   Db::exec('UPDATE `character` SET casino_state=? WHERE id=?',[json_encode(['active'=>$active]),$cid]);
  };
  for($slot=1;$slot<=18;$slot++) Db::exec('UPDATE inventory SET `bag_item'.$slot.'_id`=0 WHERE character_id=?',[$cid]);
  $coins=(int)Db::value('SELECT game_currency FROM `character` WHERE id=?',[$cid]);
  $stats=(int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid]);
  $vouchers=count(UserVouchers::data($cid));$pets=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
  $prizes=[];foreach(array_keys(Casino::TYPES) as $type) {
   $ev=$event;$ev['variant']=$type===4?'praise_the_sidekicks':($type===11?'optical_changes':'normal');
   $prizes[]=$prizeMethod->invoke(null,$cid,Db::row('SELECT * FROM `character` WHERE id=?',[$cid]),$ev,$type,3);
  }
  $save($prizes,$event);
  checkCasino($prizes[2]['reward']['booster']['days']===14 && $prizes[6]['reward']['quest_energy']===60
   && $prizes[7]['reward']['training_sessions']===40,'prémios reais de qualidade 3 têm o valor máximo');
  foreach([$prizes[1],$prizes[3]] as $preview) {
   $row=Db::row('SELECT type,ts_availability_start,ts_availability_end FROM items WHERE id=?',[$preview['reward']['item']]);
   checkCasino((int)$row['ts_availability_end']===((int)$row['type']===8?0:1),'preview respeita espaço livre/compatibilidade');
   if((int)$row['type']!==9) checkCasino((int)$row['ts_availability_start']===15,'preview de equipamento tem borda warforged');
  }
  $inventoryHandler=require dirname(__DIR__,2).'/server-laravel/actions/inventoryOps.php';
  rejectsCasino(fn()=>$inventoryHandler(['user_id'=>$uid,'action'=>'moveInventoryItem','item_id'=>$prizes[1]['reward']['item'],'target_slot'=>9]),'InvalidItem');
  $setTime($now+4*86400);
  checkCasino(Casino::current()===null,'fecha exatamente no fim, inclusive saltos de dias');
  rejectsCasino(fn()=>Casino::spin($cid,1),'InvalidFunction');
  $data=Casino::attach(['character'=>[],'current_optical_changes'=>['unlocked_options'=>'{}']],$cid);
  checkCasino($data['casino_identifier']===Casino::eventIdentifier($event) && $data['character']['current_slotmachine_spin']===1,'pendente retém máquina/evento após expirar');
  Casino::apply($cid,false);
  checkCasino((int)Db::value('SELECT game_currency FROM `character` WHERE id=?',[$cid])===$coins+30000,'moedas resgatadas');
  checkCasino((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])>=$stats+9,'atributos resgatados');
  checkCasino(count(UserVouchers::data($cid))===$vouchers+5,'energia/treino/esconderijo/booster em cinco cupões');
  checkCasino((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$pets+1,'pet criado no resgate');
  checkCasino((int)Db::value('SELECT item_improvement_id FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid])===1,'pet resgatado mantém espaço de modificação livre');
  $equipment=Db::row('SELECT * FROM items WHERE id=?',[$prizes[1]['reward']['item']]);
  checkCasino((int)$equipment['ts_availability_end']===((int)$equipment['type']===8?0:1),'equipamento resgatado mantém espaço compatível');
  $heroObject=Character::load($cid);
  if((int)$equipment['type']<=7) {
   $gem=$heroObject->createImprovementItem((int)$equipment['type'],3,['stat_strength'=>10]);
   Db::exec('UPDATE inventory SET bag_item3_id=? WHERE character_id=?',[$gem,$cid]);
   HeroZero\ItemImprovement::useItem($cid,$gem,(int)$equipment['id'],false);
   checkCasino((int)Db::value('SELECT ts_availability_end FROM items WHERE id=?',[$equipment['id']])===-$gem,'espaço gratuito de equipamento aceita gema real');
  }
  $petId=(int)Db::value('SELECT id FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
  $gem=$heroObject->createImprovementItem(9,3,['stat_strength'=>10]);
  Db::exec('UPDATE inventory SET bag_item4_id=? WHERE character_id=?',[$gem,$cid]);
  HeroZero\ItemImprovement::useItem($cid,$gem,$petId,false,true);
  checkCasino((int)Db::value('SELECT item_improvement_id FROM sidekicks WHERE id=?',[$petId])===-$gem,'espaço gratuito do pet aceita gema real');
  checkCasino((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id=?',[$cid])===(int)$prizes[1]['reward']['item'],'equipamento colocado na mochila');
  checkCasino((int)Db::value('SELECT bag_item2_id FROM inventory WHERE character_id=?',[$cid])===(int)$prizes[11]['reward']['item'],'modificação colocada na mochila');
  $data=Casino::attach(['character'=>[],'current_optical_changes'=>['unlocked_options'=>'{}']],$cid);
  $optical=$prizes[10]['identifier'];$category=(string)$ref['optical_changes'][$optical]['type'];
  checkCasino(in_array((int)$optical,json_decode($data['current_optical_changes']['unlocked_options'],true)[$category],true),'aparência desbloqueada no protocolo');
  rejectsCasino(fn()=>Casino::apply($cid,false),'HasNoActiveSpin');
  Casino::room($cid,true);checkCasino(count(Casino::wins($cid))>=8,'histórico de oito classes de prémios');Casino::room($cid,false);
  checkCasino(!Db::row('SELECT * FROM casino_rooms WHERE character_id=?',[$cid]),'sai da sala');
  // Falha a meio do resgate reverte moedas/cupões e preserva o resultado.
  $char=Db::row('SELECT * FROM `character` WHERE id=?',[$cid]);
  $p=$prizeMethod->invoke(null,$cid,$char,$event,2,3);$coin=$prizeMethod->invoke(null,$cid,$char,$event,1,3);
  $save([$coin,$p],$event);$balance=(int)$char['game_currency'];
  for($slot=1;$slot<=18;$slot++) Db::exec('UPDATE inventory SET `bag_item'.$slot.'_id`=1 WHERE character_id=?',[$cid]);
  rejectsCasino(fn()=>Casino::apply($cid,false),'NoEmptySlot');
  checkCasino((int)Db::value('SELECT game_currency FROM `character` WHERE id=?',[$cid])===$balance && Casino::state($cid)['active']!==null,'resgate inteiro atómico com mochila cheia');
  Casino::apply($cid,true);checkCasino(!Db::row('SELECT id FROM items WHERE id=?',[$p['reward']['item']]),'descartar elimina preview sem crédito');
  // Resgata todas as dez variantes e utiliza os cupões reais, sem alterar saldos persistentes.
  $prizes=[];foreach([3,7,8] as $type) foreach(Casino::baseRewardVariants($type) as $reward)
   $prizes[]=['type'=>$type,'quality'=>1,'identifier'=>null,'reward'=>$reward];
  $beforeVoucher=(int)Db::value('SELECT COALESCE(MAX(id),0) FROM vouchers');
  $save($prizes,$event);Casino::apply($cid,false);
  $newVouchers=Db::rows('SELECT code,rewards FROM vouchers WHERE id>? AND user_id=? ORDER BY id',[$beforeVoucher,$uid]);
  checkCasino(count($newVouchers)===10,'entrega as dez variantes em dez cupões');
  foreach($prizes as $prize) {
   $r=$prize['reward'];
   if(isset($r['booster'])) {$kind=['booster_quest3'=>'quest_booster','booster_stats3'=>'stats_booster','booster_work3'=>'work_booster'][$r['booster']['booster_id']];$expected=[$kind=>['type'=>50,'duration'=>$r['booster']['days']]];}
   else $expected=$r;
   checkCasino(in_array($expected,array_map(fn($v)=>json_decode($v['rewards'],true),$newVouchers),true),'cupão conserva tipo/quantidade/duração do resultado');
  }
  Db::exec('UPDATE `character` SET quest_energy=0,training_count=0,ts_active_quest_boost_expires=0,ts_active_stats_boost_expires=0,ts_active_work_boost_expires=0 WHERE id=?',[$cid]);
  UserVouchers::redeem($cid,array_column($newVouchers,'code'));
  $char=Db::row('SELECT * FROM `character` WHERE id=?',[$cid]);
  checkCasino((int)$char['quest_energy']===70 && (int)$char['training_count']===44,'cupões creditam 10+60 energias e 4+40 unidades');
  foreach(['quest','stats','work'] as $kind) {
   checkCasino($char['active_'.$kind.'_booster_id']==='booster_'.$kind.'3','energético de 50% ativado: '.$kind);
   checkCasino(abs((int)$char['ts_active_'.$kind.'_boost_expires']-GameTime::now()-17*86400)<=1,'durações de 3 e 14 dias creditadas: '.$kind);
  }
  // Todas as novas variantes são entregues pelo resgate real, sem alterar giros pagos antigos.
  $prizes=[];
  foreach([3,5,7,8] as $type) foreach(Casino::baseRewardVariants($type,'no_cash_and_xp') as $reward)
   $prizes[]=['type'=>$type,'quality'=>1,'identifier'=>null,'reward'=>$reward,'item_snapshot'=>null];
  $voucherStart=(int)Db::value('SELECT COALESCE(MAX(id),0) FROM vouchers');
  $before=(int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid]);
  $state=Casino::state($cid);$state['active']=['event'=>['variant'=>'no_cash_and_xp'],'prizes'=>$prizes];
  Db::exec('UPDATE `character` SET casino_state=? WHERE id=?',[json_encode($state),$cid]);
  Casino::apply($cid,false);
  checkCasino((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])===$before+22,'no_cash_and_xp: entrega 2+20 habilidades');
  $delivered=Db::rows('SELECT code,rewards,status FROM vouchers WHERE id>? AND user_id=? ORDER BY id',[$voucherStart,$uid]);
  checkCasino(count($delivered)===14,'no_cash_and_xp: dez energéticos e quatro cupões de energia/treino');
  checkCasino(count(array_filter($delivered,fn($v)=>(int)$v['status']===1))===14,'novos cupões ativos');
  foreach($delivered as $entry) {
   $v=Db::row('SELECT status,uses_current FROM vouchers WHERE code=?',[$entry['code']]);
   checkCasino((int)$v['status']===1 && (int)$v['uses_current']===0,'novo cupão pronto a usar');
  }
  UserVouchers::redeem($cid,array_column($delivered,'code'));
  checkCasino((int)Db::value('SELECT quest_energy FROM `character` WHERE id=?',[$cid])===190,'no_cash_and_xp: cupões somam 20+100 às 70 energias anteriores');
  foreach(['quest','stats','work','hideout','league'] as $kind)
   checkCasino((int)Db::value('SELECT ts_active_'.$kind.'_boost_expires FROM `character` WHERE id=?',[$cid])>GameTime::now(),'no_cash_and_xp: energético '.$kind.' utilizável');

  $petPrize=(new ReflectionMethod(Casino::class,'prize'))->invoke(null,$cid,Db::row('SELECT * FROM `character` WHERE id=?',[$cid]),['variant'=>'no_cash_and_xp'],4,3);
  checkCasino((int)$petPrize['item_snapshot']['type']===9,'no_cash_and_xp: acompanhante aleatório tem preview válido');
  $petCount=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
  $state=Casino::state($cid);$state['active']=['event'=>['variant'=>'no_cash_and_xp'],'prizes'=>[$petPrize]];
  Db::exec('UPDATE `character` SET casino_state=? WHERE id=?',[json_encode($state),$cid]);
  Casino::apply($cid,false);
  checkCasino((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$petCount+1,'no_cash_and_xp: acompanhante aleatório entregue na coleção');
  $pet=Db::row('SELECT * FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
  foreach(Sidekick::official()['fixed_skills'][$pet['identifier']] ?? [] as $stage=>$skill)
   checkCasino((int)$pet['stage'.$stage.'_skill_id']===(int)$skill,'casino: habilidade especial conservada no resgate');


  // Todos os eventos e máquinas: pools, constantes e contratos sem ativação persistente.
  $setTime($now+5*86400);
  foreach($catalogs as $variant=>$pool) foreach(Casino::MACHINES as $machine=>$type) {
   Db::exec('UPDATE `character` SET unused_resources=?,slotmachine_spin_count=0 WHERE id=?',['{"2":1000}',$cid]);
   $testId=Casino::schedule($variant,$type,1);$current=Casino::current();$constants=Casino::constants($current);
   checkCasino((int)$constants['casino_machine_type']===$type && count(Casino::sections($cid))===($variant==='no_cash_and_xp'?2:1),"$variant/$machine: contrato completo");
   $entries=array_merge(...array_column(Casino::sections($cid),'rewards'));$weights=Casino::weights($variant);
   foreach([3=>8,7=>3,8=>4] as $symbol=>$generic) {
    $shown=array_values(array_filter($entries,fn($entry)=>$entry['genericRewardType']===$generic));
    checkCasino(count($shown)===(isset($weights[$symbol])?count(Casino::baseRewardVariants($symbol,$variant)):0),"$variant/$machine: lista todas as variantes de $symbol");
   }
   $spin=Casino::spin($cid,1);checkCasino($spin['event']['machine_type']===$current['machine_type'] && count($spin['rewards'])===1,"$variant/$machine: giro real");
   if(in_array($spin['reward_type'],[3,7,8],true)) {
    $reward=json_decode($spin['rewards'][0],true);$symbol=$spin['reward_type'];
    $value=$symbol===3?$reward['booster']['days']:reset($reward);
    $triple=count(array_unique($spin['slots']))===1;
    checkCasino($value===($triple?[3=>14,7=>($variant==='no_cash_and_xp'?100:60),8=>40][$symbol]:[3=>3,7=>($variant==='no_cash_and_xp'?20:10),8=>4][$symbol]),'símbolos do giro real correspondem ao prémio');
   }
   foreach($spin['prizes'] as $prize) if(isset($prize['reward']['item'])) {
    $row=Db::row('SELECT type,ts_availability_start,ts_availability_end FROM items WHERE id=?',[$prize['reward']['item']]);$itemType=(int)$row['type'];
    if($itemType>=1 && $itemType<=8) checkCasino((int)$row['ts_availability_start']===15,'casino: equipamento +15');
    if(($itemType>=1 && $itemType<=7) || $itemType===9) checkCasino((int)$row['ts_availability_end']===1,'casino: espaço livre');
   }
   Casino::apply($cid,true);
   Casino::cancel($testId);
  }
  // Boot, estado normal e handler real partilham calendário.
  $id=Casino::schedule('go_crazy',2,3);
  $state=Live::accountState($uid);checkCasino($state['casino_identifier']==='casino_'.$id.'_go_crazy' && $state['constants']['casino_machine_type']===2,'estado normal sincronizado');
  $user=Db::row('SELECT * FROM `user` WHERE id=?',[$uid]);$login=require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
  $boot=$login(['user_id'=>$uid,'existing_user_id'=>$uid,'existing_session_id'=>$user['session_id'] ?? $user['user_session_id'] ?? '']);
  checkCasino($boot['casino_identifier']===$state['casino_identifier'] && $boot['constants']['casino_event_end_timestamp']===$state['constants']['casino_event_end_timestamp'],'login sincronizado');
  $handler=require dirname(__DIR__,2).'/server-laravel/actions/casino.php';
  rejectsCasino(fn()=>$handler(['user_id'=>$uid,'action'=>'spinCasinoMachine','multi_spin'=>[]]),'InvalidParameter');
  $response=$handler(['user_id'=>$uid,'action'=>'getCasinoPossibleRewards']);checkCasino(count($response['sections'])===1,'handler real fornece catálogo');
  Casino::spin($cid,1);Casino::cancel($id);checkCasino(Casino::pending($cid)['event']['id']===$id,'cancelar mantém resultado pago');Casino::apply($cid,true);
  checkCasino(Casino::current()===null,'teste termina fechado');
  echo "PASS: casino MySQL, rollback; relógio persistente intacto.\n";
 } finally {
  if($pdo->inTransaction()) $pdo->rollBack();
  foreach($props as [$prop,$old]) $prop->setValue(null,$old);
 }
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
