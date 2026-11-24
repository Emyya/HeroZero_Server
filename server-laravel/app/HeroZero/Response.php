<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Envelope de resposta esperado pelo cliente:
 *   { "data": {...}, "error": "" }
 * O cliente exige que AMBOS os campos existam (JsonActionRemoteRequest.onRequestCompleted).
 */
final class Response
{
    public static function ok($data): string
    {
        // Coage campos presentes-mas-null ao container vazio esperado (kill do
        // crash null -> .iterator()); so mexe em quem ja esta na resposta.
        if (is_array($data)) {
            $data = ResponseSchema::normalize($data);
            // Fuso do relogio de jogo: o cliente le este campo em QUALQUER
            // resposta (eb.refreshData) e usa-o para slots de batalha de guilda
            // e fronteiras de dia. O template do boot traz -10800 (UTC-3,
            // captura oficial brasileira) -- sobrescrever SEMPRE com o nosso
            // fuso, senao "hoje/amanha" e a hora dos slots ficam desfasados.
            $data['server_timestamp_offset'] = Live::gameTimestampOffset();
        }
        return json_encode(
            ['data' => $data ?? new \stdClass(), 'error' => ''],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function error(string $error): string
    {
        return json_encode(
            ['data' => new \stdClass(), 'error' => $error],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
