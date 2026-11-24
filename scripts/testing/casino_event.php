<?php
declare(strict_types=1);
// Operações explícitas. Não altera o relógio nem ativa eventos ao instalar.
if (PHP_SAPI !== 'cli') exit('Apenas CLI.');
$usage = "Uso:\n"
    ."  php scripts/testing/casino_event.php catalogo\n"
    ."  php scripts/testing/casino_event.php listar\n"
    ."  php scripts/testing/casino_event.php ligar EVENTO DIAS [slots|roleta|spine] [--em-dias=N]\n"
    ."  php scripts/testing/casino_event.php desligar [ID]\n"
    ."Exemplos (persistentes): ligar normal 3 roleta; ligar go_crazy 3 spine --em-dias=5\n"
    ."DIAS: 1–365 períodos de 86400 segundos do GameTime. Sem agendamento, fica fechado.\n";
if (($argv[1] ?? '--help')==='--help') {echo $usage;exit(0);}
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
use HeroZero\{Casino,Db,GameTime,Live};
try {
    $command=$argv[1];
    if ($command==='catalogo' && count($argv)===2) {
        foreach (Casino::catalogs() as $name=>$pool) echo $name.' — '.count($pool['items']).' itens, '.count($pool['sidekicks']).' pets, '.count($pool['optical'])." aparências\n";
        echo "Máquinas: slots (1), roleta/roda da sorte (2), spine (3).\nPools capturados; probabilidades/quantidades são balanceamento local.\n";exit(0);
    }
    // Validar argumentos antes de bootstrap/BD.
    if ($command==='ligar') {
        if (count($argv)<4 || count($argv)>6 || !isset(Casino::catalogs()[$argv[2]])
            || !ctype_digit($argv[3]) || (int)$argv[3]<1 || (int)$argv[3]>365) throw new InvalidArgumentException($usage);
        $machine='slots';$delay=0;$seenMachine=false;$seenDelay=false;
        foreach (array_slice($argv,4) as $option) {
            if (isset(Casino::MACHINES[$option]) && !$seenMachine) {$machine=$option;$seenMachine=true;}
            elseif (preg_match('/^--em-dias=([0-9]{1,3})$/D',$option,$m) && (int)$m[1]<=365 && !$seenDelay) {$delay=(int)$m[1];$seenDelay=true;}
            else throw new InvalidArgumentException($usage);
        }
    } elseif ($command==='desligar') {
        if (count($argv)>3 || (isset($argv[2]) && (!ctype_digit($argv[2]) || (int)$argv[2]<1))) throw new InvalidArgumentException($usage);
    } elseif ($command!=='listar' || count($argv)!==2) throw new InvalidArgumentException($usage);
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $format=static fn($ts)=>(new DateTimeImmutable('@'.$ts))->setTimezone(Live::gameTimezone())->format('d/m/Y H:i:s T');
    echo 'Relógio do jogo: '.$format(GameTime::now())."\n";
    if ($command==='ligar') {
        $start=GameTime::now()+$delay*86400;
        $id=Casino::schedule($argv[2],Casino::MACHINES[$machine],(int)$argv[3],$start);
        echo "Evento #$id: {$argv[2]}, $machine.\nInício: ".$format($start).' — fim: '.$format($start+(int)$argv[3]*86400)."\n"
            ."Guardado. Abertura/fecho automáticos pelo relógio interno, sem cron. Recarrega o jogo.\n";
    } elseif ($command==='desligar') {
        Casino::cancel(isset($argv[2]) ? (int)$argv[2] : null);
        echo "Agendamento cancelado. Giros já pagos continuam resgatáveis.\n";
    } else {
        foreach (Db::rows('SELECT * FROM casino_events ORDER BY ts_start,id') as $event) {
            $status=!(int)$event['enabled']?'cancelado':((int)$event['ts_start']>GameTime::now()?'agendado':((int)$event['ts_end']<=GameTime::now()?'terminado':'ativo'));
            echo '#'.$event['id'].' '.$event['variant'].' '.array_search((int)$event['machine_type'],Casino::MACHINES,true).' '.$status.' '.$format($event['ts_start']).' → '.$format($event['ts_end'])."\n";
        }
        if (!Casino::current()) echo "Casino atualmente fechado.\n";
    }
} catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
