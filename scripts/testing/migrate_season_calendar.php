<?php
declare(strict_types=1);
// Cria apenas o calendário; os progressos são sincronizados no próximo acesso.
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo = HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transacção.');
    $pdo->exec('CREATE TABLE IF NOT EXISTS season_calendar (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY, ts_anchor BIGINT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    $cycle = HeroZero\Season::currentCycle();
    echo 'Calendário global criado/verificado: '.$cycle['identifier'].', início '
        .$cycle['ts_start'].', fim '.$cycle['ts_end'].".\n"
        ."As contas sincronizam no próximo login/pedido. Um tema diferente limpa o progresso do passe; objetos e saldos permanecem.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
