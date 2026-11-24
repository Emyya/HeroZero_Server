<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
use HeroZero\{Db,StoryDungeon};
try {
    $name=$argv[1]??'';$mode=$argv[2]??'--show';
    if($name===''||!in_array($mode,['--show','--apply'],true)||count($argv)>3)
        throw new InvalidArgumentException('Uso: repair_event_missiles.php "Herói" [--show|--apply]. --apply guarda backup e remove atributos indevidos dos mísseis dos eventos.');
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $heroes=Db::rows('SELECT id FROM `character` WHERE name=?',[$name]);
    if(count($heroes)!==1)throw new InvalidArgumentException('Herói inexistente/ambíguo.');
    $cid=(int)$heroes[0]['id'];
    StoryDungeon::transaction($cid,static function()use($cid,$mode):void {
        $ids=[];
        foreach(['event_draw_progress','event_treasure_progress'] as $table)
            foreach(Db::rows("SELECT payload FROM $table WHERE character_id=? FOR UPDATE",[$cid]) as $row)
                foreach(json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR)['rewards']??[] as $reward)
                    if(!empty($reward['item']))$ids[(int)$reward['item']]=true;
        $bad=[];
        foreach(array_keys($ids) as $id) {
            $item=Db::row('SELECT id,stat_stamina,stat_strength,stat_critical_rating,stat_dodge_rating,stat_weapon_damage FROM items WHERE id=? AND character_id=? AND type=8 FOR UPDATE',[$id,$cid]);
            if($item&&array_filter(array_diff_key($item,['id'=>true]),static fn($v)=>(int)$v!==0))$bad[]=$item;
        }
        echo count($bad)." mísseis de eventos com atributos indevidos.\n";
        if($mode==='--show'||!$bad)return;
        $path=sys_get_temp_dir().'/hz-event-missiles-'.$cid.'-'.bin2hex(random_bytes(8)).'.json';
        $file=fopen($path,'x');if(!$file)throw new RuntimeException('Não foi possível guardar backup.');
        chmod($path,0600);
        $json=json_encode(['character_id'=>$cid,'items'=>$bad],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        try {if(fwrite($file,$json)!==strlen($json))throw new RuntimeException('Backup incompleto; reparação cancelada.');}finally{fclose($file);}
        foreach($bad as $item)Db::exec('UPDATE items SET stat_stamina=0,stat_strength=0,stat_critical_rating=0,stat_dodge_rating=0,stat_weapon_damage=0 WHERE id=? AND character_id=? AND type=8',[(int)$item['id'],$cid]);
        echo "Corrigidos; nível e cargas preservados. Backup: $path\n";
    });
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
