<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $pdo = HeroZero\Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Migração exige ausência de transação.');
    foreach (['worldboss_event'=>['npc_strength_percent'=>'SMALLINT NULL DEFAULT NULL',
        'npc_critical_percent'=>'SMALLINT NULL DEFAULT NULL', 'npc_dodge_percent'=>'SMALLINT NULL DEFAULT NULL'],
        'worldboss_attack'=>['iterations'=>'INT NOT NULL DEFAULT 1', 'battle_ids'=>'TEXT NULL',
        'coin_reward'=>'INT NOT NULL DEFAULT 0', 'xp_reward'=>'INT NOT NULL DEFAULT 0'],
        'worldboss_reward'=>['claimed'=>'TINYINT NOT NULL DEFAULT 0']] as $table=>$columns) {
        foreach ($columns as $column=>$definition) {
            if (!HeroZero\Db::value('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table,$column])) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                echo "Adicionado $table.$column.\n";
            }
        }
    }
    if (!HeroZero\Db::value("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'worldboss_reward' AND index_name = 'uq_worldboss_reward_owner'")) {
        if (HeroZero\Db::row('SELECT worldboss_event_id, character_id FROM worldboss_reward GROUP BY worldboss_event_id, character_id HAVING COUNT(*) > 1 LIMIT 1'))
            throw new RuntimeException('Existem prémios duplicados antigos; não foram removidos. Resolver antes de criar o índice único.');
        $pdo->exec('ALTER TABLE worldboss_reward ADD UNIQUE KEY uq_worldboss_reward_owner (worldboss_event_id, character_id)');
    }
    echo "Schema de vilões atualizado/verificado; nenhum evento iniciado.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
