<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{LocalPayments as P,Db,GameError,GameTime,Season,Router,Character,EventTreasures,Hideout,Casino};
function payCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function payReject(callable $fn,string $error):void {try{$fn();}catch(GameError $e){payCheck($e->getMessage()===$error,'Erro inesperado: '.$e->getMessage());return;}throw new RuntimeException('Devia falhar: '.$error);}
function payParams(array $offer,string $group,int $uid=0):array {
    $p=['user_id'=>$uid,'action'=>$group==='consumable_offers'?'initConsumableOfferPayment':'initPayment',
        'payment_method'=>$offer['payment_method'],'amount'=>(string)$offer['price'],'currency'=>$offer['currency'],'locale'=>$offer['locale'],
        'is_gift'=>'false','purchase_request_id'=>bin2hex(random_bytes(16))];
    if($group==='consumable_offers')$p['offer_identifier']=$offer['consumable_offer_id'];
    elseif($group==='special_offers')$p['special_offer_key']=$offer['special_offer_id'];
    return $p;
}
function payCheckAttributes(int $receiptId,int $cid,int $heroLevel):void {
    $receipt=Db::row('SELECT rewards FROM local_payments WHERE id=?',[$receiptId]);
    $reward=json_decode($receipt['rewards'],true);
    $flat=Hideout::kyberneticBonuses($cid)['item_flat'];
    foreach($reward['items'] ?? [] as $id) {
        $item=Db::row('SELECT * FROM items WHERE id=? AND character_id=?',[$id,$cid]);
        payCheck((int)$item['quality']===3 && (int)$item['ts_availability_start']===15,'Equipamento épico com borda');
        if((int)$item['type']>7)continue; // Mísseis não suportam modificação.
        payCheck((int)$item['ts_availability_end']===1,'Espaço de equipamento livre');
        $level=(int)$item['required_level'];payCheck($level>=max(1,$heroLevel-4)&&$level<=$heroLevel,'Nível gerado pela loja: '.$level.' para herói '.$heroLevel);
        $stats=[];foreach(['stamina','strength','critical_rating','dodge_rating'] as $stat)$stats[]=(int)$item['stat_'.$stat];
        $budget=(int)ceil(($level*1.6+2+$level**2*0.004)*2.3)+$flat;
        payCheck(array_sum($stats)===$budget,'Orçamento épico da loja com bónus do esconderijo');
        payCheck(count(array_filter($stats))>=3,'Distribuição épica em 3 ou 4 atributos');
    }
    foreach($reward['sidekicks'] ?? [] as $id) {
        $pet=Db::row('SELECT * FROM sidekicks WHERE id=? AND character_id=?',[$id,$cid]);
        payCheck((int)$pet['item_improvement_id']===1,'Espaço do pet livre');
        $mult=match((int)$pet['quality']) {4=>4.2,3=>2.5,default=>1.2};
        foreach(['stamina'=>0.28,'strength'=>0.43,'critical_rating'=>0.18,'dodge_rating'=>0.11] as $stat=>$ratio) {
            $mid=(int)round($heroLevel*$mult*$ratio);$base=(int)$pet['stat_base_'.$stat];
            payCheck($base>=max(1,(int)round($mid*0.85)) && $base<=max(1,(int)round($mid*1.15)),'Base do pet segue a loja ±15%');
            payCheck((int)$pet['level']===1 && (int)$pet['stat_'.$stat]===$base,'Pet entregue ao nível 1 com atributos das bases');
        }
    }
}
$pdo=null;$failed=false;
try {
    $catalog=P::catalog();$seen=[];
    foreach($catalog['consumable_offers'] as $offer) {
        $p=payParams($offer,'consumable_offers');$r=P::resolve($p);payCheck($r['key']===$offer['consumable_offer_id'],'Resolver catálogo');
        foreach($offer['display_info']['item_identifiers'] as $id)payCheck(isset(P::templates()[$id]),'Template '.$id);
        $seen[$r['key']]=true;
    }
    payCheck(count($seen)===72,'72 ofertas únicas');
    $p=['action'=>'initConsumableOfferPayment','payment_method'=>'1','amount'=>'207.90','currency'=>'BRL','locale'=>'pt_BR','offer_identifier'=>'full_speedserver_int2_offer_with_sidekick'];
    payCheck(count(P::resolve($p)['offer']['display_info']['item_identifiers'])===6,'Pacote HAR tem 5 peças + pet');
    payReject(fn()=>P::resolve(array_replace($p,['offer_identifier'=>'inventado'])),'errInitPaymentInvalidSpecialOffer');
    payReject(fn()=>P::resolve(array_replace($p,['amount'=>'0'])),'errInitPaymentInvalidSpecialOffer');
    payCheck(P::resolve(payParams($catalog['normal_offers'][0],'normal_offers'))['offer']['content']['premium_amount']===30,'Donuts normais sem premium_amount');
    echo "PASS offline: 72 ofertas, templates, pacote HAR, donuts normais e validação de catálogo.\n";
    if(!in_array('--mysql',$argv,true))exit(0);
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo();$name=null;foreach($argv as $arg)if(str_starts_with($arg,'--hero='))$name=substr($arg,7);
    $hero=$name?Db::row('SELECT id,user_id,name FROM `character` WHERE name=?',[$name]):Db::row('SELECT id,user_id,name FROM `character` ORDER BY id LIMIT 1');
    payCheck($hero!==null,'Herói inexistente');$cid=(int)$hero['id'];$uid=(int)$hero['user_id'];
    echo 'Herói: '.$hero['name'].". Preparação temporária de nível, saldos, mochila e recibos; rollback no fim. Não jogar com este herói durante o teste.\n";
    $pdo->beginTransaction();Db::row('SELECT id FROM `character` WHERE id=? FOR UPDATE',[$cid]);
    Db::exec('DELETE FROM local_payments WHERE user_id=?',[$uid]);
    Db::exec('UPDATE `character` SET level=100,xp=?,game_currency=0 WHERE id=?',[Character::xpForLevel(100),$cid]);Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);
    $clear=[];for($i=1;$i<=18;$i++)$clear[]='bag_item'.$i.'_id=0';$clearSql='UPDATE inventory SET '.implode(',',$clear).' WHERE character_id=?';Db::exec($clearSql,[$cid]);
    $offer=P::resolve($p)['offer'];$p=payParams($offer,'consumable_offers',$uid);
    $data=Router::dispatch($p['action'],$p);payCheck($data['last_payment_confirmed']===true,'Handler compra');
    $receipt=Db::row('SELECT * FROM local_payments WHERE id=?',[$data['payment_id']]);$rewards=json_decode($receipt['rewards'],true);
    payCheck(count($rewards['items'])===5&&count($rewards['sidekicks'])===1,'Entrega exata pacote HAR');
    $itemIndex=array_column($data['items'],null,'id');$petIndex=array_column($data['sidekicks'],null,'id');
    foreach($rewards['items'] as $id)payCheck(isset($itemIndex[$id])&&(int)$itemIndex[$id]['ts_availability_start']===15&&(int)$itemIndex[$id]['ts_availability_end']===1,'Coleções + borda + espaço');
    payCheck(isset($petIndex[$rewards['sidekicks'][0]])&&(int)$petIndex[$rewards['sidekicks'][0]]['item_improvement_id']===1&&$petIndex[$rewards['sidekicks'][0]]['identifier']==='sidekick_speedserver_int2','Pet incluído na resposta');
    payCheck((int)$data['user']['premium_currency']===0&&(int)$data['character']['game_currency']===0,'Sem débito');
    $id=(int)$data['payment_id'];payCheckAttributes($id,$cid,100);payCheck(P::purchase($p)===$id,'Retry devolve mesmo recibo');
    payCheck((int)Db::value('SELECT COUNT(*) FROM local_payments WHERE user_id=?',[$uid])===1,'Sem duplicação');
    payCheck(P::confirmed($uid,$id)&&!P::confirmed($uid+99999,$id)&&!P::confirmed($uid,0),'Estado de pagamento valida propriedade');
    $status=Router::dispatch('getLastPaymentStatus',['action'=>'getLastPaymentStatus','user_id'=>$uid,'payment_id'=>$id]);payCheck($status['last_payment_confirmed']===true,'Polling confirmado');
    payCheck(!isset($status['hideout']) && !isset($status['season_progress']) && str_contains($status['local_payment_message'],$offer['display_info']['name']),'Confirmação de equipamento sem falso robô/passe');
    $before=(int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?',[$cid]);
    $p['purchase_request_id']=bin2hex(random_bytes(16));Db::exec('UPDATE inventory SET '.implode(',',array_map(static fn($i)=>'bag_item'.$i.'_id='.$rewards['items'][0],range(1,18))).' WHERE character_id=?',[$cid]);
    payReject(fn()=>P::purchase($p),'errInventoryNoEmptySlot');
    payCheck((int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?',[$cid])===$before,'Mochila cheia não entrega parcialmente');
    payCheck((int)Db::value('SELECT COUNT(*) FROM local_payments WHERE user_id=?',[$uid])===1,'Mochila cheia não grava recibo');Db::exec($clearSql,[$cid]);
    $counts=0;
    foreach(P::catalog($cid)['consumable_offers'] as $o) {
        if((int)$o['payment_method']!==1)continue;
        $r=P::purchase(payParams($o,'consumable_offers',$uid));payCheck(P::confirmed($uid,$r),'Recibo oferta '.$o['consumable_offer_id']);$counts++;payCheckAttributes($r,$cid,Character::load($cid)->level());Db::exec($clearSql,[$cid]);
    }
    payCheck($counts===72,'Compra dos 72 consumíveis');
    $donuts=payParams($catalog['normal_offers'][0],'normal_offers',$uid);$donutId=P::purchase($donuts);payCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===30,'Credita 30 donuts sem saldo');
    P::purchase($donuts);payCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===30,'Retry donuts sem duplicação');
    $donuts['purchase_request_id']=bin2hex(random_bytes(16));P::purchase($donuts);payCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===60,'Nova compra igual é permitida');
    $donuts['premium_amount']=999999;payReject(fn()=>P::purchase(array_replace($donuts,['purchase_request_id'=>bin2hex(random_bytes(16))])),'errInitPaymentInvalidSpecialOffer');
    $normalStatus=P::paymentStatus($uid,$donutId);
    payCheck(!isset($normalStatus['hideout']) && str_contains($normalStatus['local_payment_message'],'30 donuts'),'Mensagem de donuts correta');
    // Calendário temporário do casino, com rollback; pacote oculto e compra recusada fora da janela.
    Db::row('SELECT id FROM casino_control WHERE id=1 FOR UPDATE');
    Db::exec('UPDATE casino_events SET enabled=0');
    $chipOffer=null;$poolOffer=null;
    foreach($catalog['special_offers'] as $o) {
        if((int)$o['payment_method']!==1)continue;
        if((int)($o['content']['slotmachine_jeton_amount'] ?? 0)>0)$chipOffer=$o;
        if(str_starts_with($o['special_offer_id'],'pool_party_season_set_sale_'))$poolOffer=$o;
    }
    payCheck($chipOffer!==null && $poolOffer!==null,'Fixtures de fichas e piscina');
    $chipParams=payParams($chipOffer,'special_offers',$uid);
    $hasChips=static fn()=>array_filter(P::catalog($cid)['special_offers'],static fn($o)=>(int)($o['content']['slotmachine_jeton_amount'] ?? 0)>0);
    payCheck(!$hasChips(),'Casino fechado: fichas ocultas');
    payReject(fn()=>P::purchase($chipParams),'errInitPaymentInvalidSpecialOffer');
    payReject(fn()=>P::purchase(payParams($poolOffer,'special_offers',$uid)),'errInitPaymentInvalidSpecialOffer');
    payCheck(!array_filter(P::catalog($cid)['special_offers'],static fn($o)=>str_starts_with($o['special_offer_id'],'pool_party_season_set_sale_')),'Caixas antigas de piscina ocultas');
    $casinoId=Casino::schedule('normal',1,1,GameTime::now()+3600);
    payCheck(!$hasChips(),'Casino futuro: fichas ocultas');
    Db::exec('UPDATE casino_events SET ts_start=?,ts_end=? WHERE id=?',[GameTime::now()-1,GameTime::now()+3600,$casinoId]);
    payCheck((bool)$hasChips(),'Casino ativo: fichas visíveis');
    // Um esconderijo recém-desbloqueado começa com um robô, inclusive em retry.
    Db::exec('DELETE FROM hideout WHERE character_id=?',[$cid]);
    $unlock=Router::dispatch('unlockHideout',['action'=>'unlockHideout','user_id'=>$uid]);
    payCheck((int)$unlock['hideout']['max_worker_count']===1 && (int)$unlock['hideout']['idle_worker_count']===1,'Esconderijo inicial com um robô');
    $unlock=Router::dispatch('unlockHideout',['action'=>'unlockHideout','user_id'=>$uid]);
    payCheck((int)$unlock['hideout']['max_worker_count']===1,'Retry não dá outro robô');
    $workerOffer=null;foreach(P::catalog($cid)['special_offers'] as $o)if($o['special_offer_id']==='hideout_worker' && (int)$o['payment_method']===1)$workerOffer=$o;
    payCheck($workerOffer!==null,'Produto do segundo robô anunciado');
    $shop=Router::dispatch('getStandalonePaymentOffers',['action'=>'getStandalonePaymentOffers','user_id'=>$uid]);
    $shopWorkers=array_values(array_filter($shop['special_offers'],static fn($o)=>$o['special_offer_id']==='hideout_worker' && (int)$o['payment_method']===1));
    payCheck(count($shopWorkers)===1,'Segundo robô incluído no catálogo da loja');
    payCheck($shopWorkers[0]['price_string']==='34,90 R$' && (float)$shopWorkers[0]['price']===34.90,'Preço visual do robô, sem Grátis');
    payCheck($shopWorkers[0]['display_info']['show_buy_button']===true && $shopWorkers[0]['display_info']['background']==='hideout_worker.webp','Cartão da loja com botão e imagem corretos');
    Db::exec('UPDATE hideout SET idle_worker_count=0 WHERE character_id=?',[$cid]);
    $beforeBalance=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);
    $wp=payParams($workerOffer,'special_offers',$uid);
    $worker=Router::dispatch('initPayment',$wp);
    payCheck((int)$worker['hideout']['max_worker_count']===2 && (int)$worker['hideout']['idle_worker_count']===1,'Compra acrescenta um robô livre preservando o ocupado');
    payCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$beforeBalance,'Segundo robô sem débito');
    payCheck(P::purchase($wp)===(int)$worker['payment_id'],'Retry do robô idempotente');
    $workerStatus=P::paymentStatus($uid,(int)$worker['payment_id']);
    payCheck(isset($workerStatus['hideout']) && !isset($workerStatus['local_payment_message']) && !isset($workerStatus['season_progress']),'Robô usa confirmação/notificação nativas');
    $afterShop=Router::dispatch('getStandalonePaymentOffers',['action'=>'getStandalonePaymentOffers','user_id'=>$uid]);
    payCheck(!array_filter($afterShop['special_offers'],static fn($o)=>$o['special_offer_id']==='hideout_worker'),'Oferta do robô retirada da loja depois da compra');
    payReject(fn()=>P::purchase(array_replace($wp,['purchase_request_id'=>bin2hex(random_bytes(16))])),'errInitPaymentInvalidSpecialOffer');
    // A faixa premium exige compra, mesmo com pontos ou desbloqueio individual antigo.
    $progress=Season::ensureProgress($cid);
    Db::exec("UPDATE season_progress SET premium_unlocked=0,season_points=100000,claimed_rewards='[]',unlocked_rewards='[]' WHERE character_id=?",[$cid]);
    $free=null;$premiumReward=null;
    foreach(Season::rewardCatalog($progress['identifier']) as $r) {
        if(json_decode($r['rewards'],true)['reward_type']!==3)continue;
        if($r['premium'] && !$premiumReward)$premiumReward=$r;
        elseif(!$r['premium'] && !$free)$free=$r;
    }
    payCheck($free!==null && $premiumReward!==null,'Recompensas numéricas para testar acesso');
    $states=array_column(Season::rewardsForCharacter($cid),null,'id');
    payCheck($states[$free['id']]['status']===1 && $states[$premiumReward['id']]['status']===0,'Free disponível e premium bloqueado');
    payReject(fn()=>Season::claimReward($cid,$premiumReward['id'],false),'errClaimSeasonRewardPremiumRequired');
    payReject(fn()=>Season::claimReward($cid,$premiumReward['id'],true),'errClaimSeasonRewardPremiumRequired');
    payReject(fn()=>Season::unlockReward($cid,$premiumReward['id']),'errClaimSeasonRewardPremiumRequired');
    Db::exec('UPDATE season_progress SET unlocked_rewards=? WHERE character_id=?',[json_encode([$premiumReward['id']]),$cid]);
    payReject(fn()=>Season::claimReward($cid,$premiumReward['id'],false),'errClaimSeasonRewardPremiumRequired');
    Season::claimReward($cid,$free['id'],false);
    $pass=null;foreach(P::catalog($cid)['special_offers'] as $o)if(str_ends_with($o['special_offer_id'],'_season_package') && (int)$o['payment_method']===1)$pass=$o;
    payCheck($pass!==null,'Passe anunciado');
    $pp=payParams($pass,'special_offers',$uid);$bought=Router::dispatch('initPayment',$pp);
    payCheck($bought['season_progress']['premium_unlocked']===true,'Compra ativa passe');
    $passStatus=P::paymentStatus($uid,(int)$bought['payment_id']);
    payCheck(!isset($passStatus['hideout']) && isset($passStatus['season_progress']) && str_contains($passStatus['local_payment_message'],'passe da temporada'),'Confirmação correta do passe');
    payCheck(array_column($bought['season_rewards'],null,'id')[$premiumReward['id']]['status']===1,'Resposta da compra desbloqueia premium');
    Season::claimReward($cid,$premiumReward['id'],false);
    payReject(fn()=>Season::claimReward($cid,$premiumReward['id'],false),'errClaimSeasonRewardAlreadyClaimed');
    foreach(P::catalog($cid)['special_offers'] as $o) {
        if((int)$o['payment_method']!==1)continue;
        $q=payParams($o,'special_offers',$uid);$pay=P::purchase($q);payCheckAttributes($pay,$cid,Character::load($cid)->level());
        payCheck(P::purchase($q)===$pay,'Retry especial '.$o['special_offer_id']);
        if(str_ends_with($o['special_offer_id'],'_season_package'))payCheck((int)Db::value('SELECT premium_unlocked FROM season_progress WHERE character_id=?',[$cid])===1,'Passe atual desbloqueado');
        if($o['special_offer_id']==='starter_package')payReject(fn()=>P::purchase(array_replace($q,['purchase_request_id'=>bin2hex(random_bytes(16))])),'errInitPaymentInvalidSpecialOffer');
        Db::exec($clearSql,[$cid]);
    }
        $chipReceipt=Db::row('SELECT id FROM local_payments WHERE user_id=? AND offer_key=? ORDER BY id DESC LIMIT 1',[$uid,$chipOffer['special_offer_id']]);
    $chipStatus=P::paymentStatus($uid,(int)$chipReceipt['id']);
    payCheck(!isset($chipStatus['hideout']) && !isset($chipStatus['season_progress']) && str_contains($chipStatus['local_payment_message'],$chipOffer['display_info']['name']),'Confirmação de fichas sem falso robô');
    Db::exec('UPDATE casino_events SET ts_end=? WHERE id=?',[GameTime::now(),$casinoId]);
    payCheck(!$hasChips(),'Casino no fim exclusivo: fichas ocultas');
    payReject(fn()=>P::purchase(array_replace($chipParams,['purchase_request_id'=>bin2hex(random_bytes(16))])),'errInitPaymentInvalidSpecialOffer');
    $chip=json_decode((string)Db::value('SELECT unused_resources FROM `character` WHERE id=?',[$cid]),true);payCheck(($chip[4]??0)>=2500,'Fichas creditadas no recurso 4');
    // Ocorrência e participação temporárias: verificar as pás pelo fluxo real.
    Db::row('SELECT id FROM event_treasure_control WHERE id=1 FOR UPDATE');
    Db::exec('UPDATE event_treasure_occurrences SET enabled=0');
    Db::exec('DELETE FROM event_treasure_progress WHERE character_id=?',[$cid]);
    $treasureIdentifier=array_key_first(EventTreasures::reference()['event_treasures']);
    EventTreasures::schedule($treasureIdentifier,GameTime::now()-1,GameTime::now()+86400);
    EventTreasures::assign($cid,$treasureIdentifier);
    $shovelOffer=null;
    foreach(P::catalog($cid)['special_offers'] as $o) {
        if((int)$o['payment_method']!==1)continue;
        foreach($o['display_info']['detail_page_rewards']['general'] ?? [] as $reward)if((int)$reward['type']===45)$shovelOffer=$o;
    }
    payCheck($shovelOffer!==null,'Pacote de pás anunciado para participação ativa');
    $shovelParams=payParams($shovelOffer,'special_offers',$uid);
    $shovelResponse=Router::dispatch('initPayment',$shovelParams);
    payCheck(EventTreasures::state($cid)['shovels']===120,'120 pás entregues');
    P::purchase($shovelParams);payCheck(EventTreasures::state($cid)['shovels']===120,'Retry não duplica pás');
    $login=Router::dispatch('autoLoginUser',['action'=>'autoLoginUser','user_id'=>$uid,'user_session_id'=>(string)Db::value('SELECT session_id FROM `user` WHERE id=?',[$uid])]);
    payCheck($login['season_progress']['premium_unlocked']===true && (int)$login['hideout']['max_worker_count']===2,'Passe e segundo robô persistem no login');
    payCheck(isset(array_column($login['sidekicks'],null,'id')[$rewards['sidekicks'][0]]),'Pet persiste no login');
    payCheck((int)array_column($login['sidekicks'],null,'id')[$rewards['sidekicks'][0]]['item_improvement_id']===1,'Espaço do pet preservado no login');
    foreach($rewards['items'] as $itemId)payCheck(isset(array_column($login['items'],null,'id')[$itemId]),'Item persiste no login');
    Db::exec('UPDATE `character` SET level=1 WHERE id=?',[$cid]);
    $petOffer=null;foreach($catalog['consumable_offers'] as $o)if($o['consumable_offer_id']==='sidekick_speedserver_int2_offer'&&(int)$o['payment_method']===1)$petOffer=$o;
    payReject(fn()=>P::purchase(payParams($petOffer,'consumable_offers',$uid)),'errUseItemItemReqLevel');
    echo "PASS MySQL: Router/coleções, pacote HAR, 72 ofertas, saldos, retry/recibos, mochila cheia, limite, fichas, passe, pás, login, nível, espaços, fórmulas, acesso premium e segundo robô; rollback.\n";
} catch(Throwable $e) {$failed=true;fwrite(STDERR,'FAIL: '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n");}
finally {if($pdo&&$pdo->inTransaction())$pdo->rollBack();}
if($failed)exit(1);
