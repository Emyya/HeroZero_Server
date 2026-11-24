<?php
declare(strict_types=1);

/**
 * action: syncHideout — re-sincronizacao completa do estado do hideout.
 * Params: {}. Origens no cliente: contador de ataques perdidos, forceSync e
 * recuperacao de erros err*PendingActivity* / *InvalidStatus. A resposta e o
 * accountState completo, que ja inclui hideout/hideout_rooms/hideout_opponent/
 * missed_hideout_attacks via Hideout::attachState.
 */

use HeroZero\Live;

return function (array $params): array {
    return Live::accountState(Live::currentUserId($params));
};
