<?php
declare(strict_types=1);
// Cria um vilão compatível com o objetivo do evento atribuído ao herói.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventQuests,Worldboss,StoryDungeon,GameTime};
try {
    $name=$argv[1]??'';$hp=100000;$seconds=3600;
    if($name===''||$name==='--help') {
        echo "Uso: start_event_quest_worldboss.php \"Herói\" [--hp=100000] [--seconds=3600]\nCria persistentemente um vilão que conta no evento de coleção já aceite e associa o herói.\nNão mata o vilão nem entrega prémios; usa o combate normal do jogo. Referência vazia usa default_event_stage2.\nNão substitui vilões/prémios pendentes. Para testes rápidos podes usar --hp=1.\n";exit;
    }
    foreach(array_slice($argv,2) as $a) {
        if(str_starts_with($a,'--hp=')&&ctype_digit(substr($a,5)))$hp=(int)substr($a,5);
        elseif(str_starts_with($a,'--seconds=')&&ctype_digit(substr($a,10)))$seconds=(int)substr($a,10);
        else throw new InvalidArgumentException('Opção inválida.');
    }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $heroes=Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]);
    if(count($heroes)!==1)throw new RuntimeException('Herói não encontrado ou ambíguo.');$cid=(int)$heroes[0]['id'];
    $result=StoryDungeon::transaction($cid,static function(array $char)use($cid,$hp,$seconds):array {
        $event=Db::row('SELECT * FROM event_quest_progress WHERE character_id=? AND claimed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid])??throw new RuntimeException('Aceita primeiro um evento de coleção.');
        if((int)$event['status']!==1||(int)$event['ts_end']<=GameTime::now()||!(int)Db::value('SELECT enabled FROM event_quest_occurrences WHERE id=?',[(int)$event['occurrence_id']]))throw new RuntimeException('Evento já concluído, expirado ou cancelado.');
        $p=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$identifier=null;
        foreach(EventQuests::definition($event['identifier'])['objectives'] as $o)if((int)$o['type']===EventQuests::WORLDBOSS&&(int)$p['values'][$o['index']]<(int)$o['value']){$identifier=$o['reference']?:'default_event_stage2';break;}
        if($identifier===null)throw new RuntimeException('Não há objetivo de vilão por completar neste evento.');
        $stage=max(1,min(14,(int)$char['current_quest_stage']));
        $eid=Worldboss::create($identifier,$stage,$hp,$seconds,1,max(999,(int)$char['level']));
        Worldboss::action($cid,'assignWorldbossEvent',$eid);
        return [$eid,$identifier,$stage];
    });
    echo "Vilão #{$result[0]} / {$result[1]}, zona {$result[2]}, $hp de vida, $seconds segundos. Associado a $name.\nAlteração persistente. Recarrega, combate e recolhe o prémio do vilão para avançar o objetivo sazonal.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
