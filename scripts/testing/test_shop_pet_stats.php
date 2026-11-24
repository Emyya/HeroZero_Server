<?php
declare(strict_types=1);
// Compras reais sob uma transação exterior com rollback; não deixa preparações persistentes.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Character,Db,GameError};
function petCheck(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
    echo "OK: $message\n";
}
try {
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $name='skel';foreach(array_slice($argv,1) as $arg) {
        if(str_starts_with($arg,'--hero='))$name=substr($arg,7);
        else throw new InvalidArgumentException('Uso: test_shop_pet_stats.php --hero=Nome');
    }
    $hero=Db::row('SELECT id,level FROM `character` WHERE name=?',[$name]);
    if(!$hero)throw new RuntimeException('Herói não encontrado');
    $cid=(int)$hero['id'];$level=(int)$hero['level'];$pdo=Db::pdo();$pdo->beginTransaction();
    try {
        $generate=new ReflectionMethod(Character::class,'generateShopSidekick');
        $insert=new ReflectionMethod(Character::class,'insertItemFromTemplate');
        foreach([26=>1,6=>3] as $roll=>$quality) {
            Db::exec('UPDATE `character` SET level=? WHERE id=?',[$level,$cid]);
            $char=Character::load($cid);
            $offer=$generate->invoke($char,$roll);
            $mult=$quality===3?2.5:1.2;
            foreach(['stamina'=>0.28,'strength'=>0.43,'critical_rating'=>0.18,'dodge_rating'=>0.11] as $stat=>$ratio) {
                $mid=(int)round($level*$mult*$ratio);$lo=max(1,(int)round($mid*0.85));$hi=max($lo,(int)round($mid*1.15));
                petCheck($offer['stat_'.$stat]>=$lo && $offer['stat_'.$stat]<=$hi,'Oferta sorteada dentro de ±15%: qualidade '.$quality.', '.$stat);
            }
            // Preço zero só na fixture: o teste isola atributos, sem exigir saldo real.
            $offer['buy_price']=0;
            $itemId=$insert->invoke($char,$offer);
            Db::exec('UPDATE inventory SET shop_item1_id=? WHERE character_id=?',[$itemId,$cid]);
            $preview=Db::row('SELECT * FROM items WHERE id=?',[$itemId]);
            // O herói sobe de nível depois da oferta: a compra deve conservar o preview.
            Db::exec('UPDATE `character` SET level=? WHERE id=?',[$level+5,$cid]);
            $before=(int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid]);
            Character::load($cid)->buyShopItem($itemId,0);
            $pet=Db::row('SELECT * FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
            petCheck((int)Db::value('SELECT COUNT(*) FROM sidekicks WHERE character_id=?',[$cid])===$before+1
                && !Db::row('SELECT id FROM items WHERE id=?',[$itemId]),'Compra entrega um pet e consome a oferta: qualidade '.$quality);
            foreach(['stamina','strength','critical_rating','dodge_rating'] as $stat)
                petCheck((int)$pet['stat_base_'.$stat]===(int)$preview['stat_'.$stat]
                    && (int)$pet['stat_'.$stat]===(int)$preview['stat_'.$stat],
                    'Pet entregue preserva preview apesar de subir nível: '.$stat);
            try {
                Character::load($cid)->buyShopItem($itemId,0);
                throw new RuntimeException('Compra repetida não foi recusada');
            } catch(GameError $e) {
                petCheck($e->getMessage()==='errInventoryInvalidItem','Compra repetida não gera novo pet');
            }
        }
        Db::exec('UPDATE `character` SET level=? WHERE id=?',[$level,$cid]);
        $char=Character::load($cid);$offer=$generate->invoke($char,26);$offer['buy_price']=0;
        foreach(['stamina','strength','critical_rating','dodge_rating'] as $stat)$offer['stat_'.$stat]=0;
        $itemId=$insert->invoke($char,$offer);
        Db::exec('UPDATE inventory SET shop_item1_id=? WHERE character_id=?',[$itemId,$cid]);
        $char->buyShopItem($itemId,0);
        $pet=Db::row('SELECT * FROM sidekicks WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
        petCheck((int)$pet['stat_base_stamina']>0 && (int)$pet['stat_base_strength']>0,
            'Oferta antiga sem atributos mantém compatibilidade');
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
    petCheck((int)Db::value('SELECT level FROM `character` WHERE id=?',[$cid])===$level,'Rollback restaura o nível do herói');
    echo "PASS: geração, preview e compra de pets MySQL com rollback.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
