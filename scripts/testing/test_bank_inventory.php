<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
class_exists(HeroZero\Protocol::class);
use HeroZero\BankInventory;
use HeroZero\Db;
function check(bool $ok,string $label): void {if(!$ok) throw new RuntimeException($label);echo "OK: $label\n";}
$name=$argv[1]??null;
$cid=$name!==null ? (int)Db::value('SELECT id FROM `character` WHERE name = ?',[$name]) : (int)Db::value('SELECT character_id FROM bank_inventory WHERE bank_item1_id > 0 LIMIT 1');
if(!$cid) throw new RuntimeException('Herói inexistente ou nenhum cofre com itens. Indica o nome do herói.');
$pdo=Db::pdo();$pdo->beginTransaction();
try {
 $before=BankInventory::data($cid);$id=0;$original=null;
 for($i=1;$i<=450;$i++) if($before['bank_item'.$i.'_id']>0) {$id=$before['bank_item'.$i.'_id'];$original='bank_item'.$i.'_id';break;}
 if(!$id) throw new RuntimeException('O herói precisa de um item no cofre.');
 check(count($before)===454,'cofre contém 450 posições e metadados');
 BankInventory::operate($cid,'moveBankInventoryItem',['item_id'=>$id,'bank_index'=>0,'target_slot_index'=>17]);
 check(BankInventory::data($cid)['bank_item18_id']===$id,'destino explícito e troca no cofre');
 BankInventory::operate($cid,'removeBankItemToInventory',['item_id'=>$id,'target_slot'=>26]);
 check((int)Db::value('SELECT bag_item18_id FROM inventory WHERE character_id = ?',[$cid])===$id,'retirada para posição escolhida');
 BankInventory::operate($cid,'addInventoryItemToBank',['item_id'=>$id,'bank_index'=>0,'target_slot_index'=>16]);
 check(BankInventory::data($cid)['bank_item17_id']===$id,'depósito para posição escolhida');
 BankInventory::operate($cid,'lockBankItem',['item_id'=>$id]);
 try {BankInventory::operate($cid,'sellBankInventoryItem',['item_id'=>$id]);throw new RuntimeException('Venda permitida');} catch(HeroZero\GameError $e) {check((bool)Db::value('SELECT id FROM items WHERE id = ?',[$id]),'venda bloqueada preserva item');}
 try {BankInventory::operate($cid,'moveBankInventoryItem',['item_id'=>$id,'bank_index'=>25,'target_slot_index'=>0]);throw new RuntimeException('Destino inválido permitido');} catch(HeroZero\GameError $e) {check(BankInventory::data($cid)['bank_item17_id']===$id,'destino inválido não altera posições');}
 $uid=(int)Db::value('SELECT user_id FROM `character` WHERE id = ?',[$cid]);
 Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?',[$uid]);
 $oldIndex=BankInventory::data($cid)['max_bank_index'];
 BankInventory::operate($cid,'upgradeBankInventory',[]);
 check(BankInventory::data($cid)['max_bank_index']===$oldIndex+1,'expansão desbloqueia uma página');
 check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?',[$uid])===100-($oldIndex===0?25:50),'expansão cobra custo das constantes');
 Db::exec('UPDATE `user` SET premium_currency = 0 WHERE id = ?',[$uid]);
 try {BankInventory::operate($cid,'upgradeBankInventory',[]);throw new RuntimeException('Saldo insuficiente permitido');} catch(HeroZero\GameError $e) {check(BankInventory::data($cid)['max_bank_index']===$oldIndex+1,'saldo insuficiente não desbloqueia página');}
 $state=HeroZero\Live::accountState($uid);
 check($state['bank_inventory']===BankInventory::data($cid),'resposta normal inclui cofre persistido');
 check(in_array($id,array_column($state['items'],'id')),'resposta inclui objeto referenciado pelo cofre');
 $login=require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
 $session=(string)Db::value('SELECT session_id FROM `user` WHERE id = ?',[$uid]);
 $boot=$login(['user_id'=>$uid,'user_session_id'=>$session]);
 check($boot['bank_inventory']===BankInventory::data($cid),'autoLoginUser inclui cofre persistido');
 $pdo->rollBack();check(BankInventory::data($cid)===$before,'rollback restaura cofre original');
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
