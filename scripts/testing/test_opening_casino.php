<?php
declare(strict_types=1);
// Integração MySQL com rollback; exige calendário vazio, não apaga eventos existentes.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Casino, Db, GameTime};
$pdo = null;
function checkOpening(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: $label\n";
}
try {
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    Db::row('SELECT id FROM casino_control WHERE id=1 FOR UPDATE');
    if ((int)Db::value('SELECT COUNT(*) FROM casino_events') !== 0) {
        throw new RuntimeException('Teste exige calendário vazio; nenhum evento existente foi alterado.');
    }
    $now = GameTime::now();
    checkOpening(Casino::ensureOpeningEvent($now-3*86400) === null, 'janela terminada não abre evento');
    checkOpening(Casino::ensureOpeningEvent(0) === null, 'epoch inválido não abre evento');
    checkOpening(Casino::ensureOpeningEvent($now+86400) === null, 'epoch futuro não abre evento');
    $epoch = $now-60;
    $id = Casino::ensureOpeningEvent($epoch);
    checkOpening($id !== null, 'criação na janela inicial');
    $event = Db::row('SELECT * FROM casino_events WHERE id=?', [$id]);
    checkOpening($event['variant']==='no_cash_and_xp' && (int)$event['machine_type']===1, 'tema e máquina slots corretos');
    checkOpening((int)$event['ts_start']===$epoch && (int)$event['ts_end']===$epoch+259200, 'três dias exatos desde o epoch');
    checkOpening(Casino::ensureOpeningEvent($epoch)===null, 'reinício não duplica nem prolonga');
    checkOpening(Db::row('SELECT * FROM casino_events WHERE id=?', [$id])===$event, 'datas preservadas');
    Casino::cancel($id);
    checkOpening(Casino::ensureOpeningEvent($epoch)===null, 'cancelamento manual não é revertido');
    checkOpening((int)Db::value('SELECT COUNT(*) FROM casino_events')===1, 'apenas um evento');
    $pdo->rollBack();
    echo "OK: rollback concluído; nenhum evento persistido.\n";
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
