<?php
declare(strict_types=1);

use HeroZero\Live;
use HeroZero\Replay;

return function (array $params): array {
    $data = Live::withLocalConstants(Replay::data('initGame'));
    // Antes do login, constantes estáticas fechadas; boot sincroniza o evento.
    if ((int)($params['user_id'] ?? 0) > 0) {
        $data['constants'] = array_replace($data['constants'], \HeroZero\Casino::constants(\HeroZero\Casino::current()));
    }
    return $data;
};
