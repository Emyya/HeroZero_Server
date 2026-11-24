<?php
declare(strict_types=1);
// Aceitação administrativa por nome. Não agenda eventos nem prepara recursos.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,TowerEvents,StoryDungeon};
try {
    $args=array_slice($argv,1);$hero=array_shift($args)??'';$dryRun=false;
    if($hero===''||$hero==='--help') {
        echo "Uso: php scripts/testing/join_tower_event.php \"Nome do herói\" [--dry-run]\nAceita a academia global ativa apenas para esse herói. Exige nome único e nível mínimo do catálogo.\n--dry-run valida sem gravar. Não agenda eventos, não dá recursos/prémios e não reinicia participações.\nO fluxo normal continua a ser o convite no jogo.\n";exit;
    }
    foreach($args as $arg)if($arg==='--dry-run')$dryRun=true;else throw new InvalidArgumentException('Opção inválida: '.$arg);
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(!TowerEvents::installed())throw new RuntimeException('Executa primeiro: php scripts/testing/migrate_tower_events.php');
    $rows=Db::rows('SELECT c.id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$hero]);
    if(count($rows)!==1)throw new InvalidArgumentException('Herói inexistente ou nome ambíguo.');
    $cid=(int)$rows[0]['id'];
    $join=static function(array $char)use($hero,$cid,$dryRun):void {
        $e=TowerEvents::current()??throw new InvalidArgumentException('Não existe academia global ativa. Inicia-a com start_tower_event.php.');
        if((int)$char['level']<(int)TowerEvents::reference()['tower_event_min_level'])throw new InvalidArgumentException('Herói abaixo do nível mínimo.');
        if(Db::row('SELECT id FROM tower_event_progress WHERE character_id=? AND occurrence_id=?',[$cid,(int)$e['id']])) {
            echo "$hero já participou nesta ocorrência; nenhuma alteração.\n";return;
        }
        if(Db::row('SELECT id FROM tower_event_progress WHERE character_id=? AND closed=0 LIMIT 1',[$cid]))throw new InvalidArgumentException('Herói tem uma participação aberta; recolhe os prémios e atualiza o jogo primeiro.');
        if($dryRun){echo "SIMULAÇÃO: $hero aceita ".$e['identifier'].". Nenhuma alteração gravada.\n";return;}
        TowerEvents::assign($cid,$e['identifier']);echo "$hero aceitou ".$e['identifier'].". Recarrega o jogo para entrar na academia.\n";
    };
    if($dryRun)$join(Db::row('SELECT * FROM `character` WHERE id=?',[$cid]));else StoryDungeon::transaction($cid,$join);
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
