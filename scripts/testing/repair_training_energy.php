<?php
declare(strict_types=1);

/** Preserva o défice atual, contando a regeneração do relógio do jogo. */
function trainingEnergyRepairPlan(array $row,int $now,int $factor=1):array
{
    if ((int)$row['max_training_energy']!==100) return $row;
    $factor=max(1,$factor);$last=(int)$row['ts_last_training_energy_change'];
    $minutes=max(0,intdiv($now-$last,60));
    $oldMax=100*$factor;$newMax=30*$factor;
    $current=max(0,min($oldMax,(int)$row['training_energy']+$minutes));
    $row['training_energy']=max(0,$newMax-($oldMax-$current));
    $row['max_training_energy']=30;
    // Rebase sem perder a fração de minuto da regeneração.
    $row['ts_last_training_energy_change']=$last>$now?$last:$last+$minutes*60;
    return $row;
}

// O teste pode carregar apenas a função pura, sem executar a reparação.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')!==__FILE__) return;
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo=null;
try {
    $apply=in_array('--apply',$argv,true);$hero=null;
    foreach($argv as $arg)if(str_starts_with($arg,'--hero='))$hero=substr($arg,7);
    $pdo=HeroZero\Db::pdo();
    if($pdo->inTransaction())throw new RuntimeException('Executar fora de transações de jogo.');
    if($hero!==null&&!HeroZero\Db::row('SELECT id FROM `character` WHERE name=?',[$hero]))throw new RuntimeException('Herói não encontrado.');
    if($apply)$pdo->beginTransaction();
    $sql='SELECT id,name,training_energy,max_training_energy,ts_last_training_energy_change FROM `character` WHERE max_training_energy=100';
    $args=[];if($hero!==null){$sql.=' AND name=?';$args[]=$hero;}
    $rows=HeroZero\Db::rows($sql.' ORDER BY id'.($apply?' FOR UPDATE':''),$args);
    $now=HeroZero\GameTime::now();$factor=HeroZero\BonusEvents::factor(10);
    echo ($apply?'APLICAR':'SIMULAÇÃO').': corrigir base 100 para 30; preservar défice após regeneração, mínimo zero. Não altera missões, treino ativo ou unidades. Bónus atual: '.$factor.".\n";
    foreach($rows as $row){$new=trainingEnergyRepairPlan($row,$now,$factor);echo $row['name'].': '.$row['training_energy'].'/100 → '.$new['training_energy'].'/30 (valor persistido; regeneração rebaseada).'."\n";}
    if($apply&&$rows) {
        $dir=dirname(__DIR__,2).'/server-laravel/storage/backups';
        if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Não foi possível criar pasta de backup.');
        $backup=$dir.'/training_energy_'.bin2hex(random_bytes(8)).'.json';
        if(file_put_contents($backup,json_encode(['game_time'=>$now,'bonus_factor'=>$factor,'characters'=>$rows],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Backup falhou; sem alterações.');
        chmod($backup,0600);
        foreach($rows as $row){$new=trainingEnergyRepairPlan($row,$now,$factor);HeroZero\Db::exec('UPDATE `character` SET training_energy=?,max_training_energy=30,ts_last_training_energy_change=? WHERE id=? AND max_training_energy=100',[$new['training_energy'],$new['ts_last_training_energy_change'],(int)$row['id']]);}
        echo 'Backup: '.$backup."\n";
    }
    if($apply)$pdo->commit();
    echo count($rows).' conta(s) '.($apply?'corrigida(s).':'identificada(s). Usar --apply para gravar.')."\n";
}catch(Throwable $e){if($pdo&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
