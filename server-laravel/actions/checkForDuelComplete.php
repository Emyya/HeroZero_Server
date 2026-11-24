<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $duel = Db::row('SELECT * FROM `duel` WHERE character_a_id = ? AND character_a_status IN (1,2) ORDER BY id DESC LIMIT 1', [$char->id()]);
    if ($duel) {
        Db::exec('UPDATE `duel` SET character_a_status = 2 WHERE id = ?', [(int)$duel['id']]);
        $duel['character_a_status'] = 2;
    }
    $boot = Live::template('autoLoginUser');
    $data = Live::template('checkForDuelComplete');
    if (isset($boot['user']) && isset($data['user'])) $data['user'] = $char->overlayUser($boot['user']);
    if ($duel) {
        $data['duel'] = Live::shapeLike($boot['duel'] ?? [], $duel);
        // Inclui battle sem battles[]: Lw.onShow chama duel.get_battle() para
        // saber se ganhou/perdeu e mostrar o botao de continuar. Sem battle na
        // resposta, _battle fica null -> crash silencioso -> botao nunca aparece.
        $battleRow = Db::row('SELECT * FROM `battle` WHERE id = ?', [(int)$duel['battle_id']]) ?? [];
        $data['battle'] = Live::shapeLike($boot['battle'] ?? [], $battleRow);
        unset($data['battles']);
    }
    return $data;
};
