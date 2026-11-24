<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db,Missiles,GameError};
$name=null;$apply=false;
foreach(array_slice($argv,1) as $arg) {if($arg==='--apply')$apply=true;else $name=$arg;}
$cids=$name!==null?[(int)Db::value('SELECT id FROM `character` WHERE name = ?',[$name])]:[];
if($name!==null && !$cids[0])throw new RuntimeException('Herói inexistente.');
$rows=Db::rows("SELECT * FROM items WHERE identifier LIKE 'missiles\\_%' AND type <> 8".($name!==null?' AND character_id = ?':''),$cids);
$rows=array_values(array_filter($rows,static fn($r)=>isset(Missiles::constants()['templates'][$r['identifier']])));
echo count($rows)." objetos antigos elegíveis. Efeito: converter para tipo 8, 100 cargas e atributos de munição; devolver gemas aplicadas à mochila e retirar de equipamento incompatível. IDs conservados.\n";
if(!$apply) {echo "Pré-visualização; executar com --apply para aplicar.\n";exit(0);}
if(Db::pdo()->inTransaction())throw new RuntimeException('Executar fora de transações de jogo.');
// Backups persistentes antes da reparação; DDL fora da transação.
Db::exec('CREATE TABLE IF NOT EXISTS missiles_items_backup LIKE items');
Db::exec('CREATE TABLE IF NOT EXISTS missiles_inventory_backup LIKE inventory');
Missiles::transaction(array_column($rows,'character_id'),static function() use($rows): void {
 foreach($rows as $old) {
  $id=(int)$old['id'];$cid=(int)$old['character_id'];
  $item=Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE',[$id,$cid]);
  if($item===null || (int)$item['type']===8)continue;
  Db::exec('INSERT IGNORE INTO missiles_items_backup SELECT * FROM items WHERE id = ?',[$id]);
  Db::exec('INSERT IGNORE INTO missiles_inventory_backup SELECT * FROM inventory WHERE character_id = ?',[$cid]);
  $inv=Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE',[$cid]) ?? [];
  $putInBag=static function(int $itemId) use(&$inv,$cid): void {
   for($i=1;$i<=18;$i++) {
    $field='bag_item'.$i.'_id';
    if(array_key_exists($field,$inv) && (int)$inv[$field]===0) {
     Db::exec("UPDATE inventory SET `$field` = ? WHERE character_id = ?",[$itemId,$cid]);$inv[$field]=$itemId;return;
    }
   }
   throw new GameError('errInventoryNoEmptySlot');
  };
  if((int)$item['ts_availability_end']<0) {
   $gem=-(int)$item['ts_availability_end'];
   if(!Db::value('SELECT id FROM items WHERE id = ? AND character_id = ? AND type = 12',[$gem,$cid]))throw new RuntimeException('Associação de gema inválida; reparação revertida.');
   $putInBag($gem);
  }
  foreach(['mask_item_id','cape_item_id','suit_item_id','belt_item_id','boots_item_id','weapon_item_id','gadget_item_id'] as $field) if((int)($inv[$field]??0)===$id) {
   $putInBag($id);Db::exec("UPDATE inventory SET `$field` = 0 WHERE character_id = ?",[$cid]);
  }
  $tpl=Missiles::template($item['identifier'],(int)$item['item_level'],(int)$item['buy_price'],(int)$item['sell_price']);
  Db::exec('UPDATE items SET type = 8, charges = ?, required_level = ?, ts_availability_start = 0, ts_availability_end = 0, stat_stamina = 0, stat_strength = 0, stat_critical_rating = 0, stat_dodge_rating = 0, stat_weapon_damage = 0 WHERE id = ? AND character_id = ?',[$tpl['charges'],$tpl['required_level'],$id,$cid]);
 }
});
echo "Reparação concluída com backup; repetir não repõe cargas em objetos já convertidos.\n";
