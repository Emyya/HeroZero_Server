<?php
declare(strict_types=1);
// Integração MySQL, duas ligações. A liga A segura os locks globais do HeroCon e do
// calendário de torneios (como um pedido a fechar ciclos); a liga B (a do jogo) tem de:
//  1) responder sem esperar quando os ciclos estão ativos (caminho sem lock);
//  2) esperar pelo lock quando faltam <= 5 s para o fim (margem), ou seja, o fecho
//     continua serializado. Tudo termina em rollback; o relógio persistente não é tocado.
// Uso: php test_calendar_lock_free.php
// Efeito persistente possível: se ainda não houver ciclos ativos, a sincronização normal
// do servidor cria-os (o mesmo que o primeiro pedido de um jogador faria).
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, HeroCon, Tournament, GameTime};

function lfCheck(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FALHOU: '.$label); echo "OK: $label\n"; }

$cfg = config('herozero.db');
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
$a = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$b = Db::pdo();
$b->exec('SET SESSION innodb_lock_wait_timeout = 2');
$offset = new ReflectionProperty(GameTime::class, 'offset');
$savedOffset = $offset->getValue();
$setNow = static function (int $now) use ($offset): void { $offset->setValue(GameTime::offset() + $now - GameTime::now()); };
$timed = static function (callable $fn): array {
    $t = microtime(true);
    try { $fn(); $err = null; } catch (Throwable $e) { $err = $e; }
    return [microtime(true) - $t, $err];
};

try {
    // Estado normal do servidor: garante ciclos atuais (idempotente).
    Tournament::synchronize();
    HeroCon::available() && HeroCon::sync();
    $herocon = HeroCon::available();
    $now = GameTime::now();

    $a->beginTransaction();
    $a->query('SELECT id FROM tournament_calendar_lock WHERE id = 1 FOR UPDATE')->fetchAll();
    if ($herocon) $a->query('SELECT id FROM herocon_control WHERE id = 1 FOR UPDATE')->fetchAll();

    // 1) Ciclos ativos: nenhum pedido pode esperar pelos locks de A.
    [$dt, $err] = $timed(static fn() => Tournament::synchronize());
    lfCheck($err === null && $dt < 1.0, sprintf('Tournament::synchronize sem esperar com ciclos ativos (%.3fs)', $dt));
    if ($herocon) {
        [$dt, $err] = $timed(static fn() => HeroCon::sync());
        lfCheck($err === null && $dt < 1.0, sprintf('HeroCon::sync sem esperar com ciclos ativos (%.3fs)', $dt));
        $cycle = HeroCon::sync();
        lfCheck(is_array($cycle) && (int)$cycle['finished'] === 0, 'HeroCon::sync devolve o ciclo ativo');
    }
    $cycles = Tournament::synchronize();
    lfCheck(count($cycles) === 3 && array_keys($cycles) === [Tournament::TYPE_XP, Tournament::TYPE_STATS, Tournament::TYPE_PVP], 'três torneios, mesma forma de resposta');

    // 2) Dentro da margem de fim de ciclo: tem de voltar a exigir o lock (timeout = protegido).
    $end = min(array_map(static fn($c) => (int)$c['ts_end'], $cycles));
    $setNow($end - 2);
    [$dt, $err] = $timed(static fn() => Tournament::synchronize($end - 2));
    lfCheck($err !== null && str_contains($err->getMessage(), 'Lock wait timeout') && $dt >= 1.5, sprintf('torneios: fecho continua serializado pelo lock (%.1fs)', $dt));
    if ($herocon) {
        $hend = (int)HeroCon::sync()['ts_end'];
        $setNow($hend - 2);
        [$dt, $err] = $timed(static fn() => HeroCon::sync());
        lfCheck($err !== null && str_contains($err->getMessage(), 'Lock wait timeout') && $dt >= 1.5, sprintf('HeroCon: fecho continua serializado pelo lock (%.1fs)', $dt));
    }
    echo "PASS: caminho sem lock com ciclos ativos; lock mantido na margem de fecho.\n";
} finally {
    $offset->setValue($savedOffset);
    if ($a->inTransaction()) $a->rollBack();
    if ($b->inTransaction()) $b->rollBack();
}
