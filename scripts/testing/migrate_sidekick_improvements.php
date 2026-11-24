<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    HeroZero\ItemImprovement::migrateSidekicks();
    HeroZero\Db::exec("UPDATE items SET type = 12 WHERE identifier REGEXP '^improvement_sidekick_(common|rare|epic|legendary)$'");
    HeroZero\Db::exec("UPDATE items SET type = 14 WHERE identifier = 'special_add_sidekick_improvement_slot'");
    echo "BD pronta: sidekicks.item_improvement_id criado/verificado; consumíveis antigos corrigidos.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
