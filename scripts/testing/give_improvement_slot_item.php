<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\ItemImprovement;

$name = $argv[1] ?? '';
$kind = $argv[2] ?? 'equipment';
if ($name === '' || !in_array($kind, ['equipment', 'sidekick'], true)) {
    fwrite(STDERR, "Uso: php scripts/testing/give_improvement_slot_item.php \"Herói\" [equipment|sidekick]\n");
    exit(1);
}
$pdo = null;
try {
    if ($kind === 'sidekick') ItemImprovement::migrateSidekicks();
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
    if (!$inventory) throw new RuntimeException('Personagem sem inventário.');
    $free = null;
    for ($i = 1; $i <= 18; $i++) {
        $field = 'bag_item' . $i . '_id';
        if (array_key_exists($field, $inventory) && (int)$inventory[$field] === 0) {
            $free = $field;
            break;
        }
    }
    if ($free === null) throw new RuntimeException('Mochila cheia. Liberta um espaço.');
    $identifier = $kind === 'equipment' ? 'special_add_improvement_slot' : 'special_add_sidekick_improvement_slot';
    $id = Character::load($cid)->createSpecialItem($identifier);
    Db::exec("UPDATE inventory SET `{$free}` = ? WHERE character_id = ?", [$id, $cid]);
    $pdo->commit();
    echo "{$heroes[0]['name']}: consumível {$identifier} (ID {$id}) colocado em {$free}.\n";
    echo "Recarrega o jogo e usa-o na mochila para escolher onde abrir o espaço.\n";
    echo "O equipamento/acompanhante ainda não foi alterado.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
