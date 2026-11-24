<?php
declare(strict_types=1);
// Integração MySQL com rollback; relógio apenas em memória.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db,Hideout,GameTime,UserVouchers,Live};
function verifyHideout(bool $ok,string $text):void {if(!$ok)throw new RuntimeException($text);echo "OK: $text\n";}
try {
 $name=$argv[1] ?? 'skel';$hero=Db::row('SELECT id,user_id FROM `character` WHERE name=?',[$name]);if(!$hero)throw new RuntimeException('Herói não encontrado.');
 $cid=(int)$hero['id'];$uid=(int)$hero['user_id'];$pdo=Db::pdo();$conn=Illuminate\Support\Facades\DB::connection();$conn->setPdo($pdo);$conn->setReadPdo($pdo);
 $props=[];foreach(['offset','epoch','cachedUptime','cachedAt'] as $key){$p=new ReflectionProperty(GameTime::class,$key);$props[$key]=[$p,$p->getValue()];}
 $time=static function(int $now)use($props):void {$props['cachedAt'][0]->setValue(null,null);$uptime=GameTime::uptime();$props['offset'][0]->setValue(null,0);$props['epoch'][0]->setValue(null,$now-$uptime);};
 $pdo->beginTransaction();
 try {
  $now=2000000000;$time($now);$h=Hideout::rowFor($cid);if(!$h)throw new RuntimeException('Herói sem esconderijo.');$rooms=Hideout::roomsFor($cid);
  Db::exec('UPDATE `character` SET active_hideout_booster_id=?,ts_active_hideout_boost_started=0,ts_active_hideout_boost_expires=0 WHERE id=?',['',$cid]);
  foreach(['glue_production','stone_production'] as $kind) {
   $room=array_values(array_filter($rooms,fn($r)=>$r['identifier']===$kind))[0] ?? null;if(!$room)throw new RuntimeException('Falta sala '.$kind);
   $room['current_resource_amount']=0;$room['ts_last_resource_change']=$now;$room['status']=Hideout::STATUS_PRODUCING;
   $base=Hideout::autoRoomProducedAmount($room,$now+600,$h,$rooms);
   foreach([25,50] as $pct){Db::exec('UPDATE `character` SET active_hideout_booster_id=?,ts_active_hideout_boost_started=?,ts_active_hideout_boost_expires=? WHERE id=?',[$pct===25?'booster_hideout1':'booster_hideout2',$now,$now+600,$cid]);$boost=Hideout::autoRoomProducedAmount($room,$now+600,$h,$rooms);verifyHideout(abs($boost-$base*(1+$pct/100))<=2,"$kind: +$pct% e capacidade preservada");}
   $after=Hideout::autoRoomProducedAmount($room,$now+1200,$h,$rooms);
   Db::exec('UPDATE `character` SET active_hideout_booster_id=? WHERE id=?',['',$cid]);$normal=Hideout::autoRoomProducedAmount($room,$now+1200,$h,$rooms);
   verifyHideout(abs($after-($normal+$base*.5))<=2,"$kind: só primeiros dez minutos têm bónus após expiração");
   Hideout::saveRoom($room);
  }
  $voucherStart=(int)Db::value('SELECT COALESCE(MAX(id),0) FROM vouchers');UserVouchers::create($cid,'hideout_booster',['type'=>50,'duration'=>3]);
  $code=Db::value('SELECT code FROM vouchers WHERE id>? AND user_id=?',[$voucherStart,$uid]);$time($now+600);
  $oldRooms=Hideout::roomsFor($cid);$expected=[];foreach($oldRooms as $r)if(in_array($r['identifier'],['glue_production','stone_production'],true)&&$r['status']==Hideout::STATUS_PRODUCING)$expected[$r['id']]=Hideout::autoRoomProducedAmount($r,$now+600,$h,$oldRooms);
  UserVouchers::redeem($cid,[$code]);foreach(Hideout::roomsFor($cid) as $r)if(isset($expected[$r['id']]))verifyHideout((int)$r['current_resource_amount']===$expected[$r['id']] && (int)$r['ts_last_resource_change']===$now+600,'ativação guarda produção anterior sem bónus retroativo');
  $state=Live::accountState($uid);foreach($state['hideout_rooms'] as $r)if(in_array($r['identifier'],['glue_production','stone_production'],true)){$w=json_decode($r['additional_value_2'],true)['production_booster'];verifyHideout($w['amount']===50 && $w['start']===$now+600 && $w['end']===$now+600+3*86400,'estado normal envia janela de produção ao cliente');}
  $time($now+1200);$room=array_values(array_filter(Hideout::roomsFor($cid),fn($r)=>$r['identifier']==='glue_production'))[0];
  $gain=Hideout::autoRoomProducedAmount($room,$now+1200,$h,Hideout::roomsFor($cid));
  Db::exec('UPDATE hideout SET current_resource_glue=0 WHERE character_id=?',[$cid]);
  $collect=require dirname(__DIR__,2).'/server-laravel/actions/hideoutExtra.php';
  $collect(['user_id'=>$uid,'action'=>'collectHideoutRoomActivityResult','hideoutRoomId'=>$room['id']]);
  $actual=(int)Db::value('SELECT current_resource_glue FROM hideout WHERE character_id=?',[$cid]);
  verifyHideout($actual===$gain,'recolha real credita a produção com bónus (esperado '.$gain.', recebido '.$actual.')');
  $collect(['user_id'=>$uid,'action'=>'collectHideoutRoomActivityResult','hideoutRoomId'=>$room['id']]);
  verifyHideout((int)Db::value('SELECT current_resource_glue FROM hideout WHERE character_id=?',[$cid])===$gain,'repetir recolha no mesmo instante não duplica recursos');
  $time($now+1320);$all=Hideout::roomsFor($cid);$totalGlue=0;
  foreach($all as $r)if($r['identifier']==='glue_production' && $r['status']==Hideout::STATUS_PRODUCING)$totalGlue+=Hideout::autoRoomProducedAmount($r,$now+1320,$h,$all);
  Db::exec('UPDATE hideout SET current_resource_glue=0 WHERE character_id=?',[$cid]);
  $collect(['user_id'=>$uid,'action'=>'collectAllHideoutRoomResources']);
  verifyHideout((int)Db::value('SELECT current_resource_glue FROM hideout WHERE character_id=?',[$cid])===$totalGlue,'recolher tudo inclui os recursos guardados antes da ativação');
  $before=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);Db::exec('UPDATE `user` SET premium_currency=100 WHERE id=?',[$uid]);$handler=require dirname(__DIR__,2).'/server-laravel/actions/boosterExtra.php';$handler(['user_id'=>$uid,'action'=>'buyHideoutBooster','id'=>'booster_hideout2','duration'=>604800]);verifyHideout((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===90,'compra real debita dez donuts e renova energético');
  $expiry=(int)Db::value('SELECT ts_active_hideout_boost_expires FROM `character` WHERE id=?',[$cid]);
  Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);
  try {$handler(['user_id'=>$uid,'action'=>'buyHideoutBooster','id'=>'booster_hideout2','duration'=>604800]);throw new RuntimeException('Aceitou compra sem saldo.');}
  catch(HeroZero\GameError $e){verifyHideout(str_contains($e->getMessage(),'NotEnough'),'compra sem saldo rejeitada');}
  verifyHideout((int)Db::value('SELECT ts_active_hideout_boost_expires FROM `character` WHERE id=?',[$cid])===$expiry,'falha na compra não altera a janela do energético');
  echo "PASS: produção, ativação por cupão, expiração, protocolo e compra; rollback.\n";
 }finally{if($pdo->inTransaction())$pdo->rollBack();foreach($props as [$p,$value])$p->setValue(null,$value);}
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
