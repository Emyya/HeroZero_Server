<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/app/HeroZero/ActiveGameClock.php';
use HeroZero\ActiveGameClock;

$dir = sys_get_temp_dir().'/hz-clock-'.bin2hex(random_bytes(6));
mkdir($dir);
$read = static fn(): int => json_decode(file_get_contents($dir.'/game_clock.json'), true)['uptime_ns'];
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
try {
    file_put_contents($dir.'/uptime_accumulated', '42');
    file_put_contents($dir.'/started_at', '9999999999');
    $clock = new ActiveGameClock($dir);
    $clock->sample(1000000000);
    $check($read() === 42000000000, 'Migração contou tempo da data do host');
    $clock->sample(86401000000000);
    $check($read() === 86442000000000, 'Um dia ligado não acumulou um dia');
    $clock = new ActiveGameClock($dir);
    $clock->sample(999999999999999);
    $check($read() === 86442000000000, 'Reinício contou tempo desligado');
    $clock->sample(1000000499999999);
    $check($read() === 86442500000000, 'Fração de segundo perdida');
    file_put_contents($dir.'/started_at', '-9999999999');
    $clock->sample(1000000999999999);
    $check($read() === 86443000000000, 'Data civil interferiu no relógio');
    $clock->sample(0);
    $check($read() === 86443000000000, 'Relógio recuou');
    echo "PASS: migração, 24h ligado, reinício sem tempo offline, frações e data civil isolada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
} finally {
    foreach (glob($dir.'/*') as $path) unlink($path);
    rmdir($dir);
}
