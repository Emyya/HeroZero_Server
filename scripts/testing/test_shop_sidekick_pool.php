<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Character,Sidekick,Db};
function checkPool(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
    echo "OK: $message\n";
}
try {
    $normal=array_merge(Character::normalShopSidekickPool(1),Character::normalShopSidekickPool(3));
    checkPool(count($normal)===28 && count(Character::normalShopSidekickPool(1))===1,'28 pets de época normal: 27 épicos e 1 comum');
    checkPool(Character::normalShopSidekickPool(4)===[],'Nenhum lendário com origem no brechó normal');
    foreach(['sidekick_peacock','sidekick_dog1','sidekick_caterpillar1','sidekick_butterfly1','sidekick_squirrel',
        'sidekick_robot1','sidekick_chameleon1','sidekick_snowman','sidekick_ld_xmas','sidekick_worldcup_tiger',
        'sidekick_season_beetle','sidekick_progress_offer_1'] as $id)
        checkPool(!in_array($id,$normal,true),'Pet fora do pool normal: '.$id);
    if(in_array('--offline',$argv,true)){echo "PASS: pool offline.\n";exit(0);}
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $name='skel';foreach(array_slice($argv,1)as $arg) {
        if(str_starts_with($arg,'--hero='))$name=substr($arg,7);
        else throw new InvalidArgumentException('Uso: test_shop_sidekick_pool.php --offline | --hero=Nome');
    }
    $row=Db::row('SELECT id FROM `character` WHERE name=?',[$name]);
    if(!$row)throw new RuntimeException('Herói não encontrado');
    $cid=(int)$row['id'];$pdo=Db::pdo();$pdo->beginTransaction();
    try {
        $char=Character::load($cid);
        $generate=new ReflectionMethod(Character::class,'generateShopSidekick');
        $counts=[1=>0,3=>0];
        for($roll=1;$roll<=1000;$roll++) {
            $offer=$generate->invoke($char,$roll);
            if($offer===null) {
                if($roll>=6 && $roll<=35)throw new RuntimeException('Faixa normal deixou de gerar pet');
                continue;
            }
            if($roll<=5 || $roll>35 || !in_array($offer['identifier'],$normal,true))throw new RuntimeException('Pet incompatível na geração');
            $quality=(int)Sidekick::official()['templates'][$offer['identifier']]['quality'];
            if($quality!==$offer['quality'])throw new RuntimeException('Qualidade alterada');
            $counts[$quality]++;
        }
        checkPool($counts===[1=>10,3=>20],'Todos os 1000 resultados do sorteio: 1% comuns, 2% épicos, lendários dão lugar a equipamento');
        $sports=new ReflectionMethod(Character::class,'generateShopSportsSidekick');
        checkPool($sports->invoke($char)===null,'Pool desportivo não gera pets de época especial');
        for($shop=1;$shop<=2;$shop++)for($sample=0;$sample<10;$sample++) {
            $char->refreshShopItems($shop,false);
            $inventory=Db::row('SELECT * FROM inventory WHERE character_id=?',[$cid]);
            for($slot=1;$slot<=9;$slot++) {
                $id=(int)$inventory[($shop===1?'shop_item':'shop2_item').$slot.'_id'];
                $item=Db::row('SELECT identifier,type FROM items WHERE id=? AND character_id=?',[$id,$cid]);
                if(!$item)throw new RuntimeException('Slot sem objeto');
                if((int)$item['type']===9 && ($shop===2 || !in_array($item['identifier'],$normal,true)))
                    throw new RuntimeException('Refresh persistiu pet fora da época normal');
            }
        }
        checkPool(true,'Refresh real das duas lojas: só pets normais na loja 1 e sem pets temáticos na loja 2');
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
    echo "PASS: geração e refresh MySQL com rollback.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
