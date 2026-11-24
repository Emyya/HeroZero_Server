<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventTreasures as T,StoryDungeon,GameTime};
try {
    $name=$argv[1]??'';$mode=$argv[2]??'--show';
    if($name===''||!in_array($mode,['--show','--accept','--shovels','--unlock'],true)||count($argv)>3)throw new InvalidArgumentException('Uso: prepare_event_treasure.php "Herói" [--show|--accept|--shovels|--unlock]. --shovels prepara 1000 pás; --unlock termina a espera dos quintais criados. Alterações persistentes com backup; não entrega prémios nem donuts.');
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $heroes=Db::rows('SELECT c.id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]);if(count($heroes)!==1)throw new InvalidArgumentException('Herói inexistente/ambíguo.');$cid=(int)$heroes[0]['id'];
    if($mode==='--accept'){T::assign($cid,(T::current()??throw new RuntimeException('Sem evento global ativo.'))['identifier']);echo "Convite aceite, sem prémios.\n";}
    if(in_array($mode,['--shovels','--unlock'],true))StoryDungeon::transaction($cid,static function()use($cid,$mode):void {
        $r=Db::row('SELECT * FROM event_treasure_progress WHERE character_id=? AND closed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid])??throw new RuntimeException('Aceitar primeiro.');
        $e=Db::row('SELECT * FROM event_treasure_occurrences WHERE id=?',[(int)$r['occurrence_id']]);
        if(!$e||!$e['enabled']||$e['ts_end']<=GameTime::now())throw new RuntimeException('Evento expirado/cancelado.');
        $p=json_decode($r['payload'],true,512,JSON_THROW_ON_ERROR);
        $dir=__DIR__.'/backups/event_treasures';if(!is_dir($dir)&&!mkdir($dir,0770,true))throw new RuntimeException('Falha ao criar diretório.');
        $file=$dir.'/hero_'.$cid.'_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
        if(file_put_contents($file,json_encode(['game_time'=>GameTime::now(),'progress'=>$r],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Falha no backup.');
        if($mode==='--shovels')$p['shovels']=max(1000,$p['shovels']);else foreach($p['levels'] as &$b)$b['info']['ts_unlock']=min($b['info']['ts_unlock'],GameTime::now());
        Db::exec('UPDATE event_treasure_progress SET payload=? WHERE id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)$r['id']]);echo "Backup: $file\nPreparação persistente aplicada.\n";
    });
    $p=T::state($cid);if(!$p['id']){echo 'Sem participação; convite global: '.(T::current()['identifier']??'nenhum')."\n";exit;}
    echo "Herói: $name; {$p['identifier']}; quintal {$p['level']}; {$p['tokens']} toupeiras; {$p['shovels']} pás; ".count($p['rewards']).' prémios desbloqueados, '.count($p['claimed'])." resgatados.\nRecarregar após preparação.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
