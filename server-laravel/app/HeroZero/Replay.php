<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Modo replay: serve respostas reais capturadas de uma sessão do servidor oficial
 * (tools/br30...har -> server/data/<action>.json). Enquanto a lógica dinâmica de
 * cada action não está pronta, isto faz o cliente oficial bootar de verdade.
 *
 * Os fixtures contêm dados da conta de convidado do dono da captura — uso local.
 */
final class Replay
{
    private const DIR = __DIR__ . '/../../data';

    public static function has(string $action): bool
    {
        return is_file(self::DIR . '/' . $action . '.json');
    }

    /** Retorna o payload `data` da fixture, com o relógio ajustado para agora. */
    public static function data(string $action): array
    {
        $src = self::DIR . '/' . $action . '.json';
        $data = AssetCache::slice('replay_' . $action, $src, static function () use ($src) {
            return json_decode(file_get_contents($src), true);
        });
        if (!is_array($data)) {
            throw new GameError('response_errorIO');
        }
        // sincroniza o relógio para o cliente não recusar por drift.
        if (array_key_exists('server_time', $data)) {
            $data['server_time'] = GameTime::now();
        }
        if (array_key_exists('time_correction', $data)) {
            $data['time_correction'] = 0;
        }
        // Fins de temporada/torneio sao datas absolutas da captura (jul/2026) e ficam
        // no passado -- o client trata a liga/torneio como encerrados no boot. Sao
        // ciclos semanais: rola para a proxima ocorrencia futura preservando dia/hora.
        foreach (['league_season_end_timestamp'] as $k) {
            if (isset($data[$k]) && is_numeric($data[$k]) && (int)$data[$k] > 0) {
                $ts = (int)$data[$k];
                while ($ts <= GameTime::now()) {
                    $ts += 7 * 86400;
                }
                $data[$k] = $ts;
            }
        }
        return array_replace($data, Tournament::calendarTimestamps());
    }
}
