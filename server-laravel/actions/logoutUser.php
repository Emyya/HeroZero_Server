<?php
declare(strict_types=1);

use HeroZero\GameTime;

return function (array $params): array {
    return [
        'server_time' => GameTime::now(),
        'time_correction' => 0,
    ];
};
