<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo=HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transação.');
    $sql=file_get_contents(dirname(__DIR__,2).'/server-laravel/docker/initdb/15_herocon.sql');
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
    foreach (['guild_competition_reward_boost_factor','ts_guild_competition_reward_boost_expires'] as $column) {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $stmt->execute(['guild',$column]);
        if (!(int)$stmt->fetchColumn()) $pdo->exec("ALTER TABLE `guild` ADD COLUMN `$column` INT NOT NULL DEFAULT 0");
    }
    echo "HeroCon: migração idempotente concluída. Histórico e progressos existentes preservados.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
