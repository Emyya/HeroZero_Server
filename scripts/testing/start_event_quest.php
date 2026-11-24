<?php
declare(strict_types=1);
// Agendamento persistente explícito; sem argumentos apenas lista templates.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{EventQuests,GameTime,Db,Missiles};
try {
    $args=array_slice($argv,1);$command=array_shift($args)??'--list';$days=7;$start=null;$hero='';
    foreach($args as $a) {
        if(str_starts_with($a,'--days=')&&ctype_digit(substr($a,7)))$days=(int)substr($a,7);
        elseif(str_starts_with($a,'--start='))$start=substr($a,8);
        elseif(str_starts_with($a,'--hero='))$hero=substr($a,7);
        else throw new InvalidArgumentException('Opção desconhecida: '.$a);
    }
    $texts=json_decode(gzuncompress(file_get_contents(dirname(__DIR__,2).'/server-laravel/public/assets/i18n/pt_BR/text.data')),true,512,JSON_THROW_ON_ERROR);
    if(in_array($command,['--list','--help'],true)) {
        echo "Eventos de coleção — 35 templates (nomes do jogo):\n";
        foreach(EventQuests::reference()['event_quests'] as $id=>$d)echo $id.' — '.($texts['event_quest/'.$id.'/name']??$id)."\n";
        echo "\nUso:\n  php scripts/testing/start_event_quest.php halloween --days=7\n  php scripts/testing/start_event_quest.php carnival_event --start=\"2027-02-01 00:00:00\" --days=7\n  php scripts/testing/start_event_quest.php easter --days=7 --hero=\"Nome\"\n  php scripts/testing/start_event_quest.php --calendar\n  php scripts/testing/start_event_quest.php --cancel=ID\nDatas --start são UTC e referem-se ao relógio GameTime. Um evento de coleção de cada vez.\nSem --hero, os heróis elegíveis recebem o convite. Com --hero, aceita-o também nessa conta.\nAgendar/cancelar altera a BD; listar não altera dados. Executar migrate_event_quests.php antes do primeiro agendamento.\n";exit;
    }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if($command==='--calendar') {
        echo 'Relógio do jogo (UTC): '.gmdate('Y-m-d H:i:s',GameTime::now())."\n";
        foreach(Db::rows('SELECT * FROM event_quest_occurrences ORDER BY ts_start,id') as $e)echo '#'.$e['id'].' '.$e['identifier'].' '.gmdate('Y-m-d H:i:s',(int)$e['ts_start']).' → '.gmdate('Y-m-d H:i:s',(int)$e['ts_end']).' '.((int)$e['enabled']?'habilitado':'cancelado')."\n";
        exit;
    }
    if(str_starts_with($command,'--cancel=')&&ctype_digit(substr($command,9))) {
        EventQuests::cancel((int)substr($command,9));echo "Ocorrência cancelada. Objetivos prontos continuam resgatáveis; o progresso guardado é preservado.\n";exit;
    }
    $alias=['natal'=>'xmas_event_1','natal2'=>'xmas_event_2','carnaval'=>'carnival_event','pascoa'=>'easter','páscoa'=>'easter','olimpiadas'=>'olympia_2016_rio','inverno'=>'winterolympics_event'];
    $identifier=$alias[$command]??$command;EventQuests::definition($identifier);
    if($days<1||$days>365)throw new InvalidArgumentException('Duração deve estar entre 1 e 365 dias.');
    $ts=GameTime::now();
    if($start!==null) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$start)throw new InvalidArgumentException('Data inválida; usar AAAA-MM-DD HH:MM:SS em UTC.');
        $ts=$date->getTimestamp();if($ts<GameTime::now()-1)throw new InvalidArgumentException('O início é anterior ao relógio do jogo.');
    }
    $cid=null;
    if($hero!=='') {
        $rows=Db::rows('SELECT c.id,c.level FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]);
        if(count($rows)!==1)throw new InvalidArgumentException('Herói não encontrado ou ambíguo.');
        if((int)$rows[0]['level']<(int)EventQuests::reference()['event_quest_min_level'])throw new InvalidArgumentException('O herói precisa de nível 5.');
        if($ts>GameTime::now())throw new InvalidArgumentException('--hero só aceita eventos que começam agora.');
        $cid=(int)$rows[0]['id'];
    }
    $eid=Missiles::transaction($cid?[$cid]:[],static function()use($identifier,$ts,$days,$cid):int {
        $eid=EventQuests::schedule($identifier,$ts,$ts+$days*86400);
        if($cid!==null)EventQuests::assign($cid,$identifier);
        return $eid;
    });
    echo "Guardado: ocorrência #$eid, $identifier, ".gmdate('Y-m-d H:i:s',$ts).' → '.gmdate('Y-m-d H:i:s',$ts+$days*86400)." UTC (fim exclusivo).\n";
    if($cid!==null)echo "Evento aceite por $hero. Não foram acrescentados donuts nem alterado o nível.\n";
    echo "Recarrega o jogo para receber o estado atualizado.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
