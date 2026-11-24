<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name = $argv[1] ?? '';
if ($name === '') { fwrite(STDERR,"Uso: php scripts/testing/prepare_user_vouchers.php \"Nome do herói\"\n"); exit(1); }
try {
    HeroZero\UserVouchers::transaction(static function () use ($name): void {
        $rows = HeroZero\Db::rows('SELECT id FROM `character` WHERE name = ? FOR UPDATE',[$name]);
        if (count($rows) !== 1) throw new RuntimeException('Nome não encontrado ou ambíguo.');
        $cid = (int)$rows[0]['id'];
        foreach (['quest_energy'=>20,'training_sessions'=>5,'hideout_glue'=>10,'hideout_stone'=>10,
            'quest_booster'=>['type'=>50,'duration'=>1]] as $key=>$amount) HeroZero\UserVouchers::create($cid,$key,$amount);
    });
    echo "Guardados 5 cupões: 20 energia, 5 unidades de treino, 10 supercola, 10 zenit e energético de missões 50%/1 dia.\nRecarrega o jogo e abre Mensagens → Cupões. Repetir o script acrescenta outros 5.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
