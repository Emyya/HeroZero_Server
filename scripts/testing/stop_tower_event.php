<?php
declare(strict_types=1);
// Cancela o evento global atual ou uma ocorrência específica; conserva o histórico.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,TowerEvents,GameTime,Missiles};
try {
    $target='--current';$dryRun=false;$targetSet=false;
    foreach(array_slice($argv,1) as $arg) {
        if($arg==='--help') {
            echo "Uso: php scripts/testing/stop_tower_event.php [--current | IDENTIFICADOR | --id=N] [--dry-run]\nSem alvo, cancela a academia global atualmente ativa.\nIDENTIFICADOR exige uma única ocorrência habilitada ativa/futura. --id=N seleciona uma ocorrência específica.\n--dry-run consulta sem gravar. Cancelar afeta todos os heróis; prémios desbloqueados continuam resgatáveis.\nNão apaga histórico, equipamentos nem progresso.\n";exit;
        }
        if($arg==='--dry-run'){$dryRun=true;continue;}
        if($targetSet)throw new InvalidArgumentException('Indica apenas um alvo.');
        $target=$arg;$targetSet=true;
    }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(!TowerEvents::installed())throw new RuntimeException('Executa primeiro: php scripts/testing/migrate_tower_events.php');
    $cancel=static function()use($target,$dryRun):void {
        if(!$dryRun)Db::row('SELECT id FROM tower_event_control WHERE id=1 FOR UPDATE');
        if($target==='--current')$row=TowerEvents::current();
        elseif(str_starts_with($target,'--id=')&&ctype_digit(substr($target,5))&&(int)substr($target,5)>0)$row=Db::row('SELECT * FROM tower_event_occurrences WHERE id=?',[(int)substr($target,5)]);
        else {
            TowerEvents::definition($target);
            $rows=Db::rows('SELECT * FROM tower_event_occurrences WHERE identifier=? AND enabled=1 AND ts_end>? ORDER BY ts_start,id',[$target,GameTime::now()]);
            if(count($rows)>1)throw new InvalidArgumentException('Há várias ocorrências desse tema. Usa --calendar no script de arranque e escolhe --id=N.');
            $row=$rows[0]??null;
        }
        if(!$row)throw new InvalidArgumentException('Não existe academia para esse alvo.');
        if(!(int)$row['enabled']){echo 'Ocorrência #'.$row['id']." já cancelada; nenhuma alteração.\n";return;}
        if($dryRun){echo 'SIMULAÇÃO: cancelar globalmente #'.$row['id'].' '.$row['identifier'].". Nenhuma alteração gravada.\n";return;}
        TowerEvents::cancel((int)$row['id']);
        echo 'Academia global #'.$row['id'].' '.$row['identifier']." cancelada. Recarrega o jogo; prémios desbloqueados continuam resgatáveis.\n";
    };
    if($dryRun)$cancel();else Missiles::transaction([],$cancel);
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
