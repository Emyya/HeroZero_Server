<?php
declare(strict_types=1);

// Crédito persistente no saldo da conta associada ao herói; não substitui o saldo.
use HeroZero\Db;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Apenas CLI.'); }
$usage = 'Uso: php scripts/testing/give_donuts.php "Nome do herói" quantidade';
if (($argv[1] ?? '') === '--help') { echo $usage . "\nAcrescenta donuts ao saldo existente da conta.\n"; exit(0); }
$name = trim($argv[1] ?? '');
$amountText = $argv[2] ?? '';
$amount = filter_var($amountText, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
if (count($argv) !== 3 || $name === '' || !preg_match('/^[0-9]+$/D', $amountText) || $amount === false) {
    fwrite(STDERR, $usage . "\nA quantidade deve ser um inteiro positivo (até 2147483647).\n");
    exit(1);
}

$pdo = null;
try {
    require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
    $app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    $heroes = Db::rows('SELECT id, user_id, name FROM `character` WHERE name = ? FOR UPDATE', [$name]);
    if (count($heroes) !== 1) throw new RuntimeException('Nome do herói não encontrado ou ambíguo. Nenhum crédito efetuado.');
    $hero = $heroes[0];
    $account = Db::row('SELECT id, premium_currency FROM `user` WHERE id = ? FOR UPDATE', [(int)$hero['user_id']]);
    if (!$account) throw new RuntimeException('Conta do herói não encontrada.');
    $before = (int)$account['premium_currency'];
    if ($before < 0 || $before > 2147483647 - $amount) throw new RuntimeException('Saldo inválido ou crédito excede o limite da coluna premium_currency.');
    $affected = Db::exec('UPDATE `user` SET premium_currency = premium_currency + ? WHERE id = ?', [$amount, (int)$account['id']]);
    if ($affected !== 1) throw new RuntimeException('Não foi possível creditar os donuts.');
    $after = (int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [(int)$account['id']]);
    if ($after !== $before + $amount) throw new RuntimeException('Saldo final inesperado. Crédito revertido.');
    $pdo->commit();
    echo "{$hero['name']}: +{$amount} donuts. Saldo: {$before} → {$after}.\n";
    echo "Recarrega o jogo para atualizar o saldo apresentado.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $message = $e instanceof PDOException ? 'Falha ao aceder à BD (código ' . $e->getCode() . '). Nenhum crédito efetuado.' : $e->getMessage();
    fwrite(STDERR, $message . "\n");
    exit(1);
}
