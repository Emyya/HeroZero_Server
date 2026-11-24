<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo=HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transação.');
    $sql=file_get_contents(dirname(__DIR__,2).'/server-laravel/docker/initdb/13_event_treasures.sql');
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
    echo "Caça às Toupeiras: migração idempotente concluída. Não foram ativados eventos nem alterados progressos antigos.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
