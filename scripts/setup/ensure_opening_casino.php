<?php
declare(strict_types=1);
// Preparação persistente chamada pelo start.sh; nunca altera o relógio.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\{Casino, GameTime};

try {
    $path = dirname(__DIR__, 2) . '/.run/epoch';
    $raw = is_file($path) ? trim((string)file_get_contents($path)) : '';
    if (!ctype_digit($raw) || (int)$raw <= 0) {
        throw new RuntimeException('Epoch ausente/inválido; iniciar pelo start.sh.');
    }
    $id = Casino::ensureOpeningEvent(GameTime::epoch());
    echo $id === null ? "Casino de abertura: calendário preservado ou janela inicial terminada.\n"
        : "Casino de abertura criado: no_cash_and_xp, slots, 3 dias desde o primeiro arranque.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro ao preparar casino de abertura: ' . $e->getMessage() . "\n");
    exit(1);
}
