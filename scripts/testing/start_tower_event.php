<?php
declare(strict_types=1);
// Calendário global persistente. --hero apenas aceita também nessa conta.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,TowerEvents as T,GameTime,Missiles};
try {
    $args=array_slice($argv,1);$command=array_shift($args)??'--list';$days=7;$start=null;$hero='';$dryRun=false;
    foreach($args as $a) {
        if($a==='--dry-run')$dryRun=true;
        elseif(str_starts_with($a,'--days=')&&ctype_digit(substr($a,7)))$days=(int)substr($a,7);
        elseif(str_starts_with($a,'--start='))$start=substr($a,8);
        elseif(str_starts_with($a,'--hero='))$hero=substr($a,7);
        else throw new InvalidArgumentException('Opção inválida: '.$a);
    }
    if(in_array($command,['--list','--help'],true)) {
        $names=['test_towerevent_2020'=>'Teste','earth_towerevent'=>'Terra','bounties_of_seven_seas_towerevent'=>'Sete Mares','sci_fi_week_towerevent'=>'Sci-Fi','wasteland_towerevent'=>'Ermos','zombies_towerevent'=>'Zumbis'];
        foreach(T::reference()['tower_events'] as $id=>$d)echo "$id — ".$names[$id]." (torre ".$d['tower_id'].")\n";
        echo "Uso: start_tower_event.php earth_towerevent [--days=7] [--start=\"AAAA-MM-DD HH:MM:SS\"] [--hero=\"Nome\"] [--dry-run]\n--dry-run valida e mostra o agendamento sem gravar.\n--calendar consulta GameTime/calendário. --cancel=ID cancela globalmente sem apagar prémios pendentes.\nDatas UTC, duração 1–365 dias. Evento global para nível >=5. --hero só aceita também nessa conta.\nExecutar migrate_tower_events.php primeiro. Agendar/cancelar altera a BD. Recarregar o cliente após mudar edição.\n";exit;
    }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if($command==='--calendar') {
        echo 'GameTime UTC: '.gmdate('Y-m-d H:i:s',GameTime::now())."\n";
        foreach(Db::rows('SELECT * FROM tower_event_occurrences ORDER BY ts_start,id') as $e)echo '#'.$e['id'].' '.$e['identifier'].' '.gmdate('Y-m-d H:i:s',(int)$e['ts_start']).' → '.gmdate('Y-m-d H:i:s',(int)$e['ts_end']).' '.((int)$e['enabled']?'habilitado':'cancelado')."\n";exit;
    }
    if(str_starts_with($command,'--cancel=')&&ctype_digit(substr($command,9))){
        $cancelId=(int)substr($command,9);
        if($dryRun){
            if(!Db::row('SELECT id FROM tower_event_occurrences WHERE id=?',[$cancelId]))throw new InvalidArgumentException('Ocorrência inexistente.');
            echo "SIMULAÇÃO: cancelar globalmente #$cancelId. Nenhuma alteração gravada.\n";exit;
        }
        T::cancel($cancelId);echo "Cancelado globalmente. Prémios desbloqueados continuam resgatáveis.\n";exit;}
    T::definition($command);if($days<1||$days>365)throw new InvalidArgumentException('Duração inválida.');$ts=GameTime::now();
    if($start!==null) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$start||$date->getTimestamp()<GameTime::now()-1)throw new InvalidArgumentException('Data UTC inválida/anterior a GameTime.');$ts=$date->getTimestamp();
    }
    if(!T::installed())throw new RuntimeException('Executa primeiro: php scripts/testing/migrate_tower_events.php');
    $cid=null;
    if($hero!=='') {
        $rows=Db::rows('SELECT c.id,c.level FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]);
        if(count($rows)!==1||$rows[0]['level']<5)throw new InvalidArgumentException('Herói inexistente/ambíguo ou abaixo de nível 5.');
        if($ts>GameTime::now())throw new InvalidArgumentException('--hero exige início imediato.');$cid=(int)$rows[0]['id'];
    }
    if($dryRun) {
        if(Db::row('SELECT id FROM tower_event_occurrences WHERE enabled=1 AND ts_start<? AND ts_end>? LIMIT 1',[$ts+$days*86400,$ts]))throw new InvalidArgumentException('Já existe uma academia nesse intervalo.');
        if($cid!==null&&Db::row('SELECT id FROM tower_event_progress WHERE character_id=? AND closed=0 LIMIT 1',[$cid]))throw new InvalidArgumentException('O herói já tem uma participação aberta; termina os resgates e atualiza o jogo primeiro.');
        echo "SIMULAÇÃO: academia global $command, ".gmdate('Y-m-d H:i:s',$ts).' → '.gmdate('Y-m-d H:i:s',$ts+$days*86400)." UTC de GameTime. Nenhuma alteração gravada.\n";
        if($cid!==null)echo "Aceitação prevista para $hero.\n";
        exit;
    }
    $id=Missiles::transaction($cid?[$cid]:[],static function()use($command,$ts,$days,$cid):int {
        $id=T::schedule($command,$ts,$ts+$days*86400);if($cid!==null)T::assign($cid,$command);return $id;
    });
    echo "Ocorrência global #$id criada: $command, ".gmdate('Y-m-d H:i:s',$ts).' → '.gmdate('Y-m-d H:i:s',$ts+$days*86400)." UTC.\n";
    if($cid!==null)echo "Aceite também por $hero.\n";echo "Recarrega o jogo. Nenhum prémio/saldo/nível foi acrescentado.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
