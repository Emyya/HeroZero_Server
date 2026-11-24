<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\ItemImprovement;
use HeroZero\Sidekick;

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}
function reject(callable $fn, string $label): void {
    try { $fn(); } catch (GameError $e) { echo "OK: {$label}\n"; return; }
    throw new RuntimeException($label . ': pedido aceite');
}
if (!ItemImprovement::hasSidekickSchema()) {
    fwrite(STDERR, "Executa primeiro: php scripts/testing/migrate_sidekick_improvements.php\n");
    exit(1);
}
$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    $template = Db::row('SELECT * FROM sidekicks WHERE status = 1 LIMIT 1');
    if (!$template) throw new RuntimeException('Necessário um sidekick existente para o teste.');
    $cid = (int)$template['character_id'];
    unset($template['id']);
    $template['item_improvement_id'] = 0;
    $template['level'] = 5;
    $template['xp'] = 0;
    foreach (['stamina', 'strength', 'critical_rating', 'dodge_rating'] as $stat) {
        $template['stat_base_' . $stat] = 10;
        $template['stat_' . $stat] = Sidekick::statAtLevel((int)$template['quality'], 10, 5);
    }
    Db::exec('INSERT INTO sidekicks (`' . implode('`,`', array_keys($template)) . '`) VALUES ('
        . implode(',', array_fill(0, count($template), '?')) . ')', array_values($template));
    $sid = (int)$pdo->lastInsertId();
    Db::exec('UPDATE inventory SET sidekick_id = ? WHERE character_id = ?', [$sid, $cid]);
    $hero = Character::load($cid);
    $opener = $hero->createSpecialItem('special_add_sidekick_improvement_slot', 14);
    Db::exec('UPDATE inventory SET bag_item1_id = ? WHERE character_id = ?', [$opener, $cid]);
    ItemImprovement::useItem($cid, $opener, $sid, false, true);
    check((int)Db::value('SELECT item_improvement_id FROM sidekicks WHERE id = ?', [$sid]) === 1, 'Consumível abre espaço');
    $previous = null;
    foreach (range(1, 4) as $quality) {
        $value = $quality * 20;
        $id = $hero->createImprovementItem(9, $quality, ['stat_strength' => $value]);
        Db::exec('UPDATE inventory SET bag_item1_id = ? WHERE character_id = ?', [$id, $cid]);
        if ($previous !== null) reject(fn() => ItemImprovement::useItem($cid, $id, $sid, false, true), 'Substituição exige confirmação');
        ItemImprovement::useItem($cid, $id, $sid, $previous !== null, true);
        $do = Sidekick::findOwned($cid, $sid);
        check($do['item_improvement_id'] === -$id, "Raridade {$quality}: associação persistida");
        check($do['stat_base_strength'] === 10 + $value, "Raridade {$quality}: base inclui melhoria");
        check($do['stat_strength'] === Sidekick::statAtLevel((int)$template['quality'], 10 + $value, 5), 'Bónus escalado pelo nível e raridade do sidekick');
        check((int)Db::value('SELECT stat_base_strength FROM sidekicks WHERE id = ?', [$sid]) === 10, 'Base original preservada');
        check((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]) === 0, 'Modificação sai da mochila');
        check(in_array($id, array_map('intval', array_column(ItemImprovement::data($cid), 'id')), true), 'Modificação enviada ao cliente');
        check(!in_array($id, array_map('intval', array_column($hero->itemsData([]), 'id')), true), 'Modificação aplicada excluída de items');
        check(Sidekick::activeRow($cid)['stat_strength'] === $do['stat_strength'], 'Acompanhante ativo usa os atributos modificados');
        if ($previous !== null) check(Db::row('SELECT id FROM items WHERE id = ?', [$previous]) === null, 'Melhoria anterior eliminada');
        $previous = $id;
    }
    $wrong = $hero->createImprovementItem(1, 3, ['stat_strength' => 100]);
    Db::exec('UPDATE inventory SET bag_item1_id = ? WHERE character_id = ?', [$wrong, $cid]);
    reject(fn() => ItemImprovement::useItem($cid, $wrong, $sid, true, true), 'Rejeita melhoria de equipamento');
    reject(fn() => ItemImprovement::useItem($cid, $previous, $sid, true, true), 'Rejeita reutilização de melhoria aplicada');
    $raw = Db::row('SELECT * FROM sidekicks WHERE id = ?', [$sid]);
    $with = Sidekick::sellPrice($raw);
    Db::exec('UPDATE sidekicks SET item_improvement_id = 1 WHERE id = ?', [$sid]);
    $without = Sidekick::sellPrice(Db::row('SELECT * FROM sidekicks WHERE id = ?', [$sid]));
    check($with > $without, 'Preço de libertação inclui melhoria');
    Db::exec('UPDATE sidekicks SET item_improvement_id = ? WHERE id = ?', [-$previous, $sid]);
    Sidekick::deleteImprovement($cid, $sid);
    check(Db::row('SELECT id FROM items WHERE id = ?', [$previous]) === null, 'Libertação/fusão elimina melhoria');
} finally {
    $pdo->rollBack();
}
echo "Testes concluídos; alterações revertidas.\n";
