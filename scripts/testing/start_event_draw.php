<?php
declare(strict_types=1);
// Calendário global persistente. --hero apenas aceita também nessa conta.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventDraws as T,GameTime,Missiles};
try {
    $args=array_slice($argv,1);$command=array_shift($args)??'--list';$days=7;$start=null;$hero='';
    foreach($args as $a) {
        if(str_starts_with($a,'--days=')&&ctype_digit(substr($a,7)))$days=(int)substr($a,7);
        elseif(str_starts_with($a,'--start='))$start=substr($a,8);
        elseif(str_starts_with($a,'--hero='))$hero=substr($a,7);
        else throw new InvalidArgumentException('Opção inválida: '.$a);
    }
    if(in_array($command,['--list','--help'],true)) {
        foreach(T::reference()['event_draw_pools'] as $id=>$d)echo "$id\n";
        echo "Uso: start_event_draw.php revenge_draw_event [--days=7] [--start=\"AAAA-MM-DD HH:MM:SS\"] [--hero=\"Nome\"]\n--calendar consulta GameTime/calendário. --cancel=ID cancela globalmente sem apagar prémios pendentes.\nDatas UTC, duração 1–365 dias. Evento global para nível >=5. --hero só aceita também nessa conta.\nExecutar migrate_event_draws.php primeiro. Agendar/cancelar altera a BD. Recarregar o cliente após mudar edição.\n";exit;
    }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if($command==='--calendar') {
        echo 'GameTime UTC: '.gmdate('Y-m-d H:i:s',GameTime::now())."\n";
        foreach(Db::rows('SELECT * FROM event_draw_occurrences ORDER BY ts_start,id') as $e)echo '#'.$e['id'].' '.$e['identifier'].' '.gmdate('Y-m-d H:i:s',(int)$e['ts_start']).' → '.gmdate('Y-m-d H:i:s',(int)$e['ts_end']).' '.((int)$e['enabled']?'habilitado':'cancelado')."\n";exit;
    }
    if(str_starts_with($command,'--cancel=')&&ctype_digit(substr($command,9))){T::cancel((int)substr($command,9));echo "Cancelado globalmente. Prémios desbloqueados continuam resgatáveis.\n";exit;}
    T::definition($command);if($days<1||$days>365)throw new InvalidArgumentException('Duração inválida.');$ts=GameTime::now();
    if($start!==null) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$start||$date->getTimestamp()<GameTime::now()-1)throw new InvalidArgumentException('Data UTC inválida/anterior a GameTime.');$ts=$date->getTimestamp();
    }
    $cid=null;
    if($hero!=='') {
        $rows=Db::rows('SELECT c.id,c.level FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]);
        if(count($rows)!==1||$rows[0]['level']<5)throw new InvalidArgumentException('Herói inexistente/ambíguo ou abaixo de nível 5.');
        if($ts>GameTime::now())throw new InvalidArgumentException('--hero exige início imediato.');$cid=(int)$rows[0]['id'];
    }
    $id=Missiles::transaction($cid?[$cid]:[],static function()use($command,$ts,$days,$cid):int {
        $id=T::schedule($command,$ts,$ts+$days*86400);if($cid!==null)T::assign($cid,$command);return $id;
    });
    echo "Ocorrência global #$id criada: $command, ".gmdate('Y-m-d H:i:s',$ts).' → '.gmdate('Y-m-d H:i:s',$ts+$days*86400)." UTC.\n";
    if($cid!==null)echo "Aceite também por $hero.\n";echo "Recarrega o jogo. Nenhum prémio/saldo/nível foi acrescentado.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
