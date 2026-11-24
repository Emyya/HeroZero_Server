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
$sidekickId = $argv[2] ?? '0';
$quality = $argv[3] ?? '4';
if ($name === '' || !ctype_digit($sidekickId) || !in_array($quality, ['1', '2', '3', '4'], true)) {
    fwrite(STDERR, "Uso: php scripts/testing/prepare_sidekick_improvement.php \"Herói\" [ID sidekick ou 0=automático] [raridade 1–4; padrão 4]\n");
    exit(1);
}
$pdo = null;
try {
    // DDL antes da transação: MySQL faz commit implícito em ALTER TABLE.
    ItemImprovement::migrateSidekicks();
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $inventory = Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE', [$cid]);
    if (!$inventory) throw new RuntimeException('Personagem sem inventário.');
    $target = (int)$sidekickId > 0
        ? Db::row('SELECT * FROM sidekicks WHERE character_id = ? AND id = ? AND status = 1 FOR UPDATE', [$cid, (int)$sidekickId])
        : Db::row('SELECT * FROM sidekicks WHERE character_id = ? AND status = 1 AND item_improvement_id IN (0, 1)
            ORDER BY (id = ?) DESC, id LIMIT 1 FOR UPDATE', [$cid, (int)($inventory['sidekick_id'] ?? 0)]);
    if (!$target || !in_array((int)$target['item_improvement_id'], [0, 1], true)) {
        throw new RuntimeException('Sem sidekick elegível. As melhorias existentes são preservadas.');
    }
    $free = null;
    for ($i = 1; $i <= 18; $i++) {
        $field = 'bag_item' . $i . '_id';
        if (array_key_exists($field, $inventory) && (int)$inventory[$field] === 0) {
            $free = $field;
            break;
        }
    }
    if ($free === null) throw new RuntimeException('Mochila cheia. Liberta um espaço.');
    $modId = Character::load($cid)->createImprovementItem(9, (int)$quality, [
        'stat_stamina' => 1000, 'stat_strength' => 1000, 'stat_critical_rating' => 1000, 'stat_dodge_rating' => 1000,
    ]);
    Db::exec('UPDATE sidekicks SET item_improvement_id = 1 WHERE id = ? AND character_id = ?', [(int)$target['id'], $cid]);
    Db::exec("UPDATE inventory SET `{$free}` = ? WHERE character_id = ?", [$modId, $cid]);
    $pdo->commit();
    $rarity = [1 => 'comum', 2 => 'rara', 3 => 'épica', 4 => 'lendária'][(int)$quality];
    echo "Herói: {$heroes[0]['name']} (ID {$cid})\n";
    echo "Espaço aberto no sidekick {$target['name']} / {$target['identifier']} (ID {$target['id']}).\n";
    echo "Modificação {$rarity} {$modId} em {$free}: +1000 base em cada atributo, escalados pelo nível do sidekick.\n";
    echo "Recarrega o jogo. Usa a modificação na mochila e escolhe esse sidekick; confirma a aplicação.\n";
    echo "Alterações guardadas. A modificação ainda não foi aplicada.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
