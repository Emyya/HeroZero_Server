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

function verify(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}
function rejects(callable $action, string $label): void {
    try { $action(); } catch (GameError $e) {
        verify($e->getMessage() === 'errInventoryInvalidItem', $label);
        return;
    }
    throw new RuntimeException($label . ': pedido aceite indevidamente');
}

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    $cid = (int)Db::value('SELECT character_id FROM inventory ORDER BY character_id LIMIT 1');
    if (!$cid) throw new RuntimeException('Necessário um personagem de teste com inventário.');
    $base = Db::row('SELECT * FROM items LIMIT 1');
    if (!$base) throw new RuntimeException('Necessário um item como template.');
    unset($base['id']);
    $create = static function (array $overrides) use ($base, $cid, $pdo): int {
        $row = array_merge($base, ['character_id' => $cid, 'required_level' => 1,
            'ts_availability_end' => 0, 'stat_stamina' => 0, 'stat_strength' => 0,
            'stat_critical_rating' => 0, 'stat_dodge_rating' => 0, 'stat_weapon_damage' => 0], $overrides);
        Db::exec('INSERT INTO items (`' . implode('`,`', array_keys($row)) . '`) VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
        return (int)$pdo->lastInsertId();
    };
    $equipment = $create(['identifier' => 'test_mask', 'type' => 1, 'stat_strength' => 10]);
    $opener = $create(['identifier' => 'special_add_improvement_slot', 'type' => 13]);
    $first = $create(['identifier' => 'improvement_mask_common', 'type' => 12, 'stat_strength' => 25]);
    $second = $create(['identifier' => 'improvement_mask_rare', 'type' => 12, 'stat_strength' => 40]);
    $wrong = $create(['identifier' => 'improvement_cape_common', 'type' => 12]);
    Db::exec('UPDATE inventory SET mask_item_id = ?, bag_item1_id = ?, bag_item2_id = ?,
        bag_item3_id = ?, bag_item4_id = ? WHERE character_id = ?', [$equipment, $opener, $first, $second, $wrong, $cid]);
    $equipBonus = new ReflectionMethod(Character::class, 'equipBonus');
    $before = $equipBonus->invoke(Character::load($cid));
    rejects(fn() => ItemImprovement::useItem($cid, $first, $equipment, false), 'Sem espaço: não consome a melhoria');
    verify((int)Db::value('SELECT bag_item2_id FROM inventory WHERE character_id = ?', [$cid]) === $first, 'Rollback preserva mochila');
    ItemImprovement::useItem($cid, $opener, $equipment, false);
    verify((int)Db::value('SELECT ts_availability_end FROM items WHERE id = ?', [$equipment]) === 1, 'Abre espaço');
    rejects(fn() => ItemImprovement::useItem($cid, $wrong, $equipment, false), 'Rejeita tipo incompatível');
    ItemImprovement::useItem($cid, $first, $equipment, false);
    verify((int)Db::value('SELECT ts_availability_end FROM items WHERE id = ?', [$equipment]) === -$first, 'Associação persistida');
    verify(count(ItemImprovement::data($cid)) >= 1, 'Melhoria enviada ao cliente');
    verify(!in_array($first, array_map('intval', array_column(Character::load($cid)->itemsData([]), 'id')), true), 'Melhoria aplicada sai da coleção de itens');
    verify(ItemImprovement::sellPrice($cid, Db::row('SELECT * FROM items WHERE id = ?', [$equipment]))
        === (int)$base['sell_price'] + (int)round((int)$base['sell_price'] * 0.25), 'Venda inclui bónus da melhoria');
    $after = $equipBonus->invoke(Character::load($cid));
    verify($after['strength'] === $before['strength'] + 25, 'Atributos equipados incluem melhoria');
    rejects(fn() => ItemImprovement::useItem($cid, $second, $equipment, false), 'Substituição exige confirmação');
    ItemImprovement::useItem($cid, $second, $equipment, true);
    verify(Db::row('SELECT id FROM items WHERE id = ?', [$first]) === null, 'Substituição elimina melhoria anterior');
    $after = $equipBonus->invoke(Character::load($cid));
    verify($after['strength'] === $before['strength'] + 40, 'Substituição não acumula bónus');
    rejects(fn() => ItemImprovement::useItem($cid, $second, $equipment, true), 'Não reutiliza melhoria já aplicada');
    Db::exec('UPDATE inventory SET mask_item_id = 0, bag_item5_id = ? WHERE character_id = ?', [$equipment, $cid]);
    $after = $equipBonus->invoke(Character::load($cid));
    verify($after['strength'] === $before['strength'] - 10, 'Desequipar remove equipamento e melhoria dos atributos');
    ItemImprovement::deleteAttached($cid, $equipment);
    verify(Db::row('SELECT id FROM items WHERE id = ?', [$second]) === null, 'Venda elimina melhoria associada');
} finally {
    $pdo->rollBack();
}
echo "Testes concluídos; alterações revertidas.\n";
