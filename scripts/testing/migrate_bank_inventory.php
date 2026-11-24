<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo=HeroZero\Db::pdo();
if ($pdo->inTransaction()) throw new RuntimeException('Executar fora de transações de jogo.');
$pdo->exec('CREATE TABLE IF NOT EXISTS herozero_bank_migration (id int PRIMARY KEY)');
if ($pdo->query('SELECT id FROM herozero_bank_migration WHERE id = 1')->fetchColumn() !== false) {
    echo "Migração já aplicada.\n";
    exit(0);
}
// Backup persistente também marca a conversão: repetir não reconverte índices.
$pdo->exec('CREATE TABLE IF NOT EXISTS bank_inventory_legacy_backup LIKE bank_inventory');
$columns=$pdo->query('SHOW COLUMNS FROM bank_inventory')->fetchAll(PDO::FETCH_COLUMN);
$alter=[];
for($i=1;$i<=450;$i++) {
 $field='bank_item'.$i.'_id';
 $alter[]=in_array($field,$columns,true) ? "MODIFY `$field` int NOT NULL DEFAULT 0" : "ADD `$field` int NOT NULL DEFAULT 0";
}
if(!in_array('locked_item_ids',$columns,true)) $alter[]="ADD locked_item_ids text NOT NULL DEFAULT ('[]')";
$pdo->exec('ALTER TABLE bank_inventory '.implode(', ',$alter));
$pdo->beginTransaction();
try {
 $rows=$pdo->query('SELECT * FROM bank_inventory FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
 $backupColumns=$pdo->query('SHOW COLUMNS FROM bank_inventory_legacy_backup')->fetchAll(PDO::FETCH_COLUMN);
 foreach($rows as $row) {
  $check=$pdo->prepare('SELECT id FROM bank_inventory_legacy_backup WHERE id = ?');$check->execute([$row['id']]);
  if($check->fetchColumn()!==false) continue;
  $insert=$pdo->prepare('INSERT INTO bank_inventory_legacy_backup (`'.implode('`,`',$backupColumns).'`) VALUES ('.implode(',',array_fill(0,count($backupColumns),'?')).')');
  $insert->execute(array_map(static fn($k)=>$row[$k],$backupColumns));
  // O handler antigo criava 9 posições e expandia +9. Converter capacidade
  // em páginas de 18, arredondando para cima; nunca esconder itens existentes.
  $capacity=max(1,(int)$row['max_bank_index']);
  for($i=1;$i<=450;$i++) if((int)$row['bank_item'.$i.'_id']>0) $capacity=max($capacity,$i);
  $index=min(24,(int)ceil($capacity/18)-1);
  $update=$pdo->prepare('UPDATE bank_inventory SET max_bank_index = ? WHERE id = ?');$update->execute([$index,$row['id']]);
 }
 $pdo->exec('INSERT INTO herozero_bank_migration (id) VALUES (1)');
 $pdo->commit();echo "Migração concluída; valores antigos preservados em bank_inventory_legacy_backup.\n";
} catch(Throwable $e) {$pdo->rollBack();throw $e;}
