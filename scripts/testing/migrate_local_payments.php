<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo=HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transação.');
    $pdo->exec(file_get_contents(dirname(__DIR__,2).'/server-laravel/docker/initdb/16_local_payments.sql'));
    echo "Compras locais: tabela de recibos criada/verificada. Saldos e inventários preservados.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
