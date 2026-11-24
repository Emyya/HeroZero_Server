<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/app/HeroZero/AssetCache.php';
use HeroZero\AssetCache;
$src = tempnam(sys_get_temp_dir(), 'hz-asset-');
$name = 'test_'.bin2hex(random_bytes(6));
$file = dirname(__DIR__, 2).'/server-laravel/storage/framework/cache/hz_'.$name.'.cache';
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
try {
    file_put_contents($src, 'a');
    $builds = 0;
    $build = static function () use (&$builds): array { ++$builds; return ['nested'=>['value'=>$builds]]; };
    $a = AssetCache::slice($name, $src, $build);
    $a['nested']['value'] = 99;
    $b = AssetCache::slice($name, $src, $build);
    $check($builds === 1 && $b['nested']['value'] === 1, 'Cache não isolou alterações do consumidor');
    // Outro pedido: memória estática vazia, fallback persistente válido.
    $memory = new ReflectionProperty(AssetCache::class, 'memory');
    $memory->setValue(null, []);
    $b = AssetCache::slice($name, $src, $build);
    $check($builds === 1 && $b['nested']['value'] === 1, 'Cache persistente não reutilizada');
    file_put_contents($src, 'changed-size');
    clearstatcache(true, $src);
    $b = AssetCache::slice($name, $src, $build);
    $check($builds === 2 && $b['nested']['value'] === 2, 'Alteração do asset não invalidou cache');
    echo 'PASS: reutilização, isolamento e invalidação; APCu='.(function_exists('apcu_enabled') && apcu_enabled() ? 'ativo' : 'fallback').PHP_EOL;
    if (in_array('--benchmark', $argv, true)) {
        // Apenas dados sintéticos; sem BD, contas ou pedidos HTTP.
        $large = $name.'_large';
        $largeFile = dirname($file).'/hz_'.$large.'.cache';
        try {
            AssetCache::slice($large, $src, static fn(): array => array_fill(0, 20000, ['id'=>123, 'identifier'=>'test_item', 'stats'=>[1, 2, 3, 4]]));
            $start = hrtime(true);
            for ($i=0; $i<50; ++$i) {
                $value = unserialize(file_get_contents($largeFile), ['allowed_classes'=>false])['v'];
            }
            $disk = (hrtime(true)-$start)/1e6;
            $start = hrtime(true);
            for ($i=0; $i<50; ++$i) {
                // Esvaziar só memória por pedido simula pedidos PHP distintos.
                $memory->setValue(null, []);
                $value = AssetCache::slice($large, $src, static fn()=>throw new RuntimeException('Cache perdida'));
            }
            $shared = (hrtime(true)-$start)/1e6;
            $start = hrtime(true);
            for ($i=0; $i<50; ++$i) $value = AssetCache::slice($large, $src, static fn()=>[]);
            $local = (hrtime(true)-$start)/1e6;
            printf("50 leituras sintéticas: disco %.1f ms; entre pedidos %.1f ms; mesmo pedido %.1f ms\n", $disk, $shared, $local);
        } finally {
            @unlink($largeFile);
        }
    }
} finally {
    @unlink($src);
    @unlink($file);
}
