<?php
declare(strict_types=1);

/** Prepara um teste manual persistente: espaço vazio + melhoria na mochila. */
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Character;
use HeroZero\Db;

$name = $argv[1] ?? '';
$requestedItemId = $argv[2] ?? null;
if ($name === '' || ($requestedItemId !== null && (!ctype_digit($requestedItemId) || (int)$requestedItemId <= 0))) {
    fwrite(STDERR, "Uso: php scripts/testing/prepare_item_improvement.php \"Nome do herói\" [ID do equipamento]\n");
    exit(1);
}

$pdo = null;
try {
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome não encontrado ou ambíguo. Usa o nome exato do personagem.');
    $cid = (int)$heroes[0]['id'];
    $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
    if (!$inventory) throw new RuntimeException('Personagem sem inventário.');
    $slots = ['mask_item_id', 'cape_item_id', 'suit_item_id', 'belt_item_id', 'boots_item_id', 'weapon_item_id', 'gadget_item_id'];
    $equipment = null;
    foreach ($slots as $slot) {
        $id = (int)($inventory[$slot] ?? 0);
        if ($id <= 0 || ($requestedItemId !== null && $id !== (int)$requestedItemId)) continue;
        $candidate = Db::row('SELECT * FROM items WHERE character_id = ? AND id = ? FOR UPDATE', [$cid, $id]);
        if ($candidate && (int)$candidate['type'] >= 1 && (int)$candidate['type'] <= 7
            && in_array((int)$candidate['ts_availability_end'], [0, 1], true)) {
            $equipment = $candidate;
            break;
        }
    }
    if (!$equipment) throw new RuntimeException('Não há equipamento equipado elegível. O script preserva melhorias existentes e itens com disponibilidade especial.');
    $free = null;
    for ($i = 1; $i <= 18; $i++) {
        $field = 'bag_item' . $i . '_id';
        if (array_key_exists($field, $inventory) && (int)$inventory[$field] === 0) {
            $free = $field;
            break;
        }
    }
    if ($free === null) throw new RuntimeException('Mochila cheia. Liberta um espaço antes de repetir.');
    $character = Character::load($cid);
    $modId = $character->createImprovementItem((int)$equipment['type'], 3, [
        'stat_stamina' => 1000, 'stat_strength' => 1000,
        'stat_critical_rating' => 1000, 'stat_dodge_rating' => 1000,
    ]);
    Db::exec('UPDATE items SET ts_availability_end = 1 WHERE character_id = ? AND id = ?', [$cid, (int)$equipment['id']]);
    Db::exec("UPDATE inventory SET `{$free}` = ? WHERE character_id = ?", [$modId, $cid]);
    $pdo->commit();
    echo "Herói: {$heroes[0]['name']} (ID {$cid})\n";
    echo "Espaço vazio aberto em {$equipment['identifier']} (item {$equipment['id']}).\n";
    echo "Modificação épica {$modId} colocada em {$free}: +1000 em cada um dos quatro atributos.\n";
    echo "Recarrega o jogo e arrasta a modificação da mochila para esse equipamento.\n";
    echo "As alterações foram guardadas na BD. A modificação ainda não foi aplicada.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
