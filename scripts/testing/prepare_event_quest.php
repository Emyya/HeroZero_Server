<?php
declare(strict_types=1);
// Preparação persistente explícita; guarda backup do progresso antes de o avançar.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventQuests,StoryDungeon,GameTime};
try {
    $name=$argv[1]??'';$mode=$argv[2]??'--show';$objective=null;
    foreach(array_slice($argv,3) as $a)if(str_starts_with($a,'--objective='))$objective=substr($a,12);else throw new InvalidArgumentException('Opção inválida.');
    if($name===''||!in_array($mode,['--show','--accept','--ready'],true))throw new InvalidArgumentException("Uso: prepare_event_quest.php \"Herói\" [--show | --accept | --ready] [--objective=Identificador]\n--show só consulta. --accept aceita o convite atual. --ready coloca objetivos não resgatados na meta, sem entregar prémios; guarda backup. Não dá donuts nem muda nível/calendário.");
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $heroes=Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]);
    if(count($heroes)!==1)throw new InvalidArgumentException('Herói não encontrado ou ambíguo.');$cid=(int)$heroes[0]['id'];
    if($mode==='--accept') {
        $current=EventQuests::current()??throw new RuntimeException('Sem evento ativo. Agenda-o com start_event_quest.php.');
        EventQuests::assign($cid,$current['identifier']);echo "Convite aceite. Progresso persistente criado; nenhum prémio entregue.\n";
    }
    if($mode==='--ready') {
        StoryDungeon::transaction($cid,static function()use($cid,$objective):void {
            $row=Db::row('SELECT * FROM event_quest_progress WHERE character_id=? AND claimed=0 ORDER BY id LIMIT 1 FOR UPDATE',[$cid])??throw new RuntimeException('O herói não tem evento atribuído. Usa --accept.');
            if((int)$row['status']!==1||(int)$row['ts_end']<=GameTime::now()||!(int)Db::value('SELECT enabled FROM event_quest_occurrences WHERE id=?',[(int)$row['occurrence_id']]))throw new RuntimeException('Evento já concluído, cancelado ou expirado.');
            $d=EventQuests::definition($row['identifier']);
            if($objective!==null&&!isset($d['objectives'][$objective]))throw new InvalidArgumentException('Objetivo não pertence ao evento. Usa --show para listar.');
            $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$changes=0;
            foreach($d['objectives'] as $oid=>$o)if(($objective===null||$oid===$objective)&&(int)$p['values'][$o['index']]<(int)$o['value']){$p['values'][$o['index']]=(int)$o['value'];$changes++;}
            if(!$changes)throw new RuntimeException('Nenhum objetivo precisa de avanço; os já resgatados são preservados.');
            $dir=dirname(__DIR__,2).'/scripts/testing/backups/event_quests';if(!is_dir($dir)&&!mkdir($dir,0770,true))throw new RuntimeException('Não foi possível criar diretório de backup.');
            $file=$dir.'/hero_'.$cid.'_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4)).'.json';
            if(file_put_contents($file,json_encode(['game_time'=>GameTime::now(),'progress'=>$row],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Falha ao guardar backup.');
            Db::exec('UPDATE event_quest_progress SET payload=? WHERE id=? AND character_id=?',[json_encode($p,JSON_THROW_ON_ERROR),(int)$row['id'],$cid]);
            echo "Backup: $file\n$changes objetivos ficaram prontos para resgate. Alteração persistente; nenhum prémio creditado.\n";
        });
    }
    // Consulta sem attach: --show não altera nem normaliza o estado.
    $row=Db::row('SELECT * FROM event_quest_progress WHERE character_id=? AND claimed=0 ORDER BY id LIMIT 1',[$cid]);
    if(!$row){$e=EventQuests::current();echo $e?'Convite atual: '.$e['identifier'].". Usa --accept.\n":"Sem evento ativo/atribuído.\n";exit;}
    echo "Herói: $name; evento {$row['identifier']}; estado {$row['status']}; fim ".gmdate('Y-m-d H:i:s',(int)$row['ts_end'])." UTC.\n";
    $p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
    foreach(EventQuests::definition($row['identifier'])['objectives'] as $oid=>$o) {
        $v=(int)$p['values'][$o['index']];$state=$v>$o['value']?'resgatado':($v===$o['value']?'pronto':'em progresso');
        echo "$oid: ".min($v,(int)$o['value']).'/'.$o['value']." — $state\n";
    }
    echo "Recarrega o jogo após preparação. Recolhe cada objetivo e depois escolhe o prémio final.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
