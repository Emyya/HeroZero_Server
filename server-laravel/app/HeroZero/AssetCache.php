<?php
declare(strict_types=1);

namespace HeroZero;

/** Assets imutáveis: memória por pedido, APCu entre pedidos e fallback em disco.
 * Nunca guarda personagens, saldos ou outro estado vivo do jogo.
 */
final class AssetCache
{
    private static array $memory = [];

    /**
     * Devolve $build() cacheado. `$srcPath` e o asset de origem cuja alteracao
     * invalida a entrada; pode ser '' quando o build nao depende de ficheiro.
     *
     * @return mixed
     */
    public static function slice(string $name, string $srcPath, callable $build)
    {
        $key = @filemtime($srcPath) . ':' . @filesize($srcPath);
        $file = __DIR__ . '/../../storage/framework/cache/hz_' . $name . '.cache';

        $identity = $file . ':' . $srcPath . ':' . $key;
        if (array_key_exists($identity, self::$memory)) {
            return self::$memory[$identity];
        }
        $shared = 'hz_asset_v1:' . hash('sha256', $identity);
        $apcu = function_exists('apcu_enabled') && apcu_enabled();
        if ($apcu) {
            $value = apcu_fetch($shared, $hit);
            if ($hit) return self::$memory[$identity] = $value;
        }

        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $bag = @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($bag) && ($bag['k'] ?? null) === $key && array_key_exists('v', $bag)) {
                if ($apcu) apcu_store($shared, $bag['v'], 3600);
                return self::$memory[$identity] = $bag['v'];
            }
        }

        $value = $build();
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        // Escrita atomica: tmp + rename, para dois pedidos simultaneos nunca
        // lerem um ficheiro a meio da escrita.
        @file_put_contents($file . '.' . getmypid(), serialize(['k' => $key, 'v' => $value]));
        @rename($file . '.' . getmypid(), $file);
        if ($apcu) apcu_store($shared, $value, 3600);
        return self::$memory[$identity] = $value;
    }
}
