<?php
/** Calendários semanais: start/end sincronizam apenas ciclos vencidos; status é só leitura. */
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, Tournament, Live};
$cmd=$argv[1]??'status';
if (in_array($cmd,['start','end'],true)) {
    Tournament::synchronize();
    echo "Calendários sincronizados; nenhum ciclo foi encerrado antecipadamente.\n";
} elseif ($cmd!=='status') {
    fwrite(STDERR,"Uso: php tools/tournament.php [start|end|status]. Reset removido para evitar reabrir/pagar o mesmo período.\n");
    exit(1);
}
$labels=[1=>'XP (domingo)',2=>'Habilidades (quarta)',3=>'PvP (terça)'];
foreach (Db::rows('SELECT * FROM tournaments WHERE type IS NOT NULL AND status=1 ORDER BY type') as $t) {
    $end=(new DateTimeImmutable('@'.$t['ts_end']))->setTimezone(Live::gameTimezone())->format('Y-m-d H:i:s T');
    echo $labels[(int)$t['type']].': #'.$t['id'].'; fim/início: '.$end."\n";
}
echo 'Prémios pendentes: '.(int)Db::value('SELECT COUNT(*) FROM tournament_rewards WHERE claimed=0')."\n";
