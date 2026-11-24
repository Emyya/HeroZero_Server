<?php
declare(strict_types=1);

use HeroZero\GameTime;
use HeroZero\Live;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Apenas CLI.'); }
$usage = 'Uso: php scripts/testing/advance_game_time.php DIAS';
if (($argv[1] ?? '') === '--help') {
    echo $usage."\nAvança o relógio global do jogo. Um dia = 86400 segundos.\n"
        ."A alteração fica guardada e soma-se ao desvio actual; não requer MySQL.\n";
    exit(0);
}
$text = $argv[1] ?? '';
$days = filter_var($text, FILTER_VALIDATE_INT, ['options' => [
    'min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, 86400),
]]);
if (count($argv) !== 2 || !preg_match('/^[0-9]+$/D', $text) || $days === false) {
    fwrite(STDERR, $usage."\nIndica um número inteiro positivo de dias.\n");
    exit(1);
}

try {
    require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
    $app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $seconds = $days * 86400;
    $oldOffset = GameTime::offset();
    $before = GameTime::now();
    if ($oldOffset > PHP_INT_MAX - $seconds || $before > PHP_INT_MAX - $seconds) {
        throw new RuntimeException('O avanço excede o limite do relógio.');
    }
    $timezone = Live::gameTimezone();
    $format = static fn(int $ts): string => (new DateTimeImmutable('@'.$ts))
        ->setTimezone($timezone)->format('d/m/Y H:i:s T');
    $oldDate = $format($before);
    $newDate = $format($before + $seconds);

    // Transformar falhas de escrita do relógio em erro CLI, sem anunciar sucesso.
    set_error_handler(static function (int $severity, string $message): never {
        throw new RuntimeException('Não foi possível guardar o relógio: '.$message);
    });
    try { GameTime::addDays($days); }
    finally { restore_error_handler(); }

    echo "Relógio avançado {$days} dia(s): +{$seconds} segundos.\n"
        ."Antes: {$oldDate}\nDepois: {$newDate}\n"
        ."Desvio guardado: {$oldOffset} → ".GameTime::offset()." segundos.\n"
        ."A mudança afecta todo o jogo. Recarrega o cliente para sincronizar imediatamente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
