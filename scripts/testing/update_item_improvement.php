<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\ItemImprovement;

$name = $argv[1] ?? '';
$itemId = $argv[2] ?? '';
$values = array_slice($argv, 3);
if ($values === []) $values = ['1000'];
if (count($values) === 1) $values = array_fill(0, 4, $values[0]);
$valid = $name !== '' && ctype_digit($itemId) && (int)$itemId > 0 && count($values) === 4;
foreach ($values as $value) {
    // Colunas MEDIUMINT assinadas no schema do jogo.
    if (!ctype_digit($value) || strlen($value) > 7 || (int)$value > 8388607) $valid = false;
}
if (!$valid) {
    fwrite(STDERR, "Uso: php scripts/testing/update_item_improvement.php \"Herói\" ID_GEMA [valor para todos = 1000]\n"
        . "Ou: php scripts/testing/update_item_improvement.php \"Herói\" ID_GEMA resistência força crítico esquiva\n");
    exit(1);
}

$pdo = null;
try {
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo.');
    $cid = (int)$heroes[0]['id'];
    $item = Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE', [(int)$itemId, $cid]);
    if (!$item || (int)$item['type'] !== 12 || !isset(ItemImprovement::catalog()[$item['identifier']])) {
        throw new RuntimeException('Esse ID não corresponde a uma modificação deste herói.');
    }
    $stats = ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating'];
    $old = array_map(static fn(string $stat): int => (int)$item[$stat], $stats);
    $new = array_map('intval', $values);
    Db::exec('UPDATE items SET stat_stamina = ?, stat_strength = ?, stat_critical_rating = ?, stat_dodge_rating = ?
        WHERE id = ? AND character_id = ?', array_merge($new, [(int)$itemId, $cid]));
    $pdo->commit();
    echo "Herói: {$heroes[0]['name']} — gema {$itemId} ({$item['identifier']})\n";
    echo 'Antes: ' . implode(' / ', $old) . "\n";
    echo 'Agora: ' . implode(' / ', $new) . " (resistência / força / crítico / esquiva)\n";
    echo "Guardado na BD. Recarrega o jogo; funciona também em gemas já aplicadas.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
