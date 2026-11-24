<?php
declare(strict_types=1);
// Este processo pertence exclusivamente ao container do jogo.
require dirname(__DIR__, 2).'/server-laravel/app/HeroZero/ActiveGameClock.php';

$directory = dirname(__DIR__, 2).'/.run';
if (!is_dir($directory) && !mkdir($directory, 0775, true)) exit(1);
$lock = fopen($directory.'/game_clock.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Já existe um processo a acumular o relógio do jogo.\n");
    exit(1);
}
if (!is_file($directory.'/epoch')) file_put_contents($directory.'/epoch', (string)time());
// Desativar o cálculo legado também nos bots ainda não reiniciados.
if (is_file($directory.'/started_at')) unlink($directory.'/started_at');
$clock = new HeroZero\ActiveGameClock($directory);
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, static function () use (&$running): void { $running = false; });
    }
}
do {
    $clock->sample(hrtime(true));
    usleep(250000);
} while ($running);
$clock->sample(hrtime(true));
