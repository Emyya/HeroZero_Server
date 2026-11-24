<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\Sidekick;

/**
 * Sidekicks/pets — as acoes `*Sidekick*` (contratos: docs/sidekicks/extract/01).
 *
 * Chaves obrigatorias por acao (o cliente le sem null-check):
 *  - getSidekicks    -> all_sidekicks[] (catalogo ~212, com ever_owned/now_owned/sources)
 *  - renameSidekick  -> sidekick{} atualizado
 *  - mergeSidekick fase 1 (confirm=false) -> merged_sidekick{} (preview, NAO persiste)
 *  - mergeSidekick fase 2 (confirm=true)  -> estado final; source eliminada
 *
 * Erros tratados pelo cliente: errRemovePremiumCurrencyNotEnough (dialogo de
 * donuts), errRenameSameName, errRenameInvalidName. Debito premium SEMPRE
 * atomico e ANTES de aplicar o efeito (padrao buyBooster).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $bool = static fn($v): bool => in_array($v, [true, 1, '1', 'true'], true);

    /** Debito atomico de donuts: falha se o saldo nao cobre (errRemovePremiumCurrencyNotEnough). */
    $debitPremium = static function (int $cost) use ($userId): void {
        if ($cost <= 0) {
            return;
        }
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $userId, $cost]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    };

    /** Remove um id das duas listas de ordem. */
    $forgetFromOrders = static function (int $sidekickId) use ($cid): void {
        $orders = Sidekick::ordersData($cid);
        $ordersLeft = array_values(array_diff($orders['orders'], [$sidekickId]));
        $favoritsLeft = array_values(array_diff($orders['favorit_orders'], [$sidekickId]));
        if ($ordersLeft !== $orders['orders'] || $favoritsLeft !== $orders['favorit_orders']) {
            Sidekick::saveOrders($cid, $ordersLeft, $favoritsLeft);
        }
    };

    $sidekickId = (int)($params['sidekick_id'] ?? 0);
    $extra = [];

    switch ($action) {
        // ------------------------------------------------------------------
        case 'getSidekicks':
            // so o catalogo; a lista do jogador ja vai no accountState.
            break;

        // ------------------------------------------------------------------
        case 'setSidekick':
            // Ativo definido EXCLUSIVAMENTE por inventory.sidekick_id (0 desequipa).
            if ($sidekickId > 0 && Sidekick::findOwned($cid, $sidekickId) === null) {
                break; // id inexistente/alheio: ignora sem erro (cliente nunca envia)
            }
            Sidekick::setActive($cid, max(0, $sidekickId));
            break;

        // ------------------------------------------------------------------
        case 'renameSidekick': {
            $row = Sidekick::findOwned($cid, $sidekickId);
            if ($row === null) {
                break;
            }
            $name = trim((string)($params['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 32) {
                throw new GameError('errRenameInvalidName');   // coluna varchar(32)
            }
            if ($name === (string)$row['name']) {
                throw new GameError('errRenameSameName');
            }
            $debitPremium((int)Sidekick::scalars()['sidekick_rename_premium_currency_amount']);
            Db::exec('UPDATE `sidekicks` SET name = ? WHERE id = ?', [$name, $sidekickId]);
            // handler do cliente acede get_appResponse().get_sidekick() SEM null-check.
            $extra['sidekick'] = Sidekick::findOwned($cid, $sidekickId);
            break;
        }

        // ------------------------------------------------------------------
        case 'releaseSidekick': {
            // Vende: paga sellPrice e APAGA o sidekick numa transacao.
            $row = Sidekick::findOwned($cid, $sidekickId);
            if ($row === null) {
                break;
            }
            $price = Sidekick::sellPrice($row);
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                Sidekick::deleteImprovement($cid, $sidekickId);
                Db::exec('DELETE FROM `sidekicks` WHERE id = ?', [$sidekickId]);
                Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$price, $cid]);
                Db::exec('UPDATE `inventory` SET sidekick_id = 0 WHERE character_id = ? AND sidekick_id = ?', [$cid, $sidekickId]);
                $forgetFromOrders($sidekickId);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            // GOAL: sidekick vendido/libertado.
            $char->bumpGoalStat('sidekick_released');
            // Devolve o sidekick com status=0 para o cliente remover do painel
            // "Meus" (refreshSidekick actualiza por id mas nunca apaga — o filtro
            // status==1 é o mecanismo de exclusão).
            $extra['sidekick'] = array_merge($row, ['status' => 0]);
            break;
        }

        // ------------------------------------------------------------------
        case 'unbindSidekick':
            // Desvincula sem apagar: limpa apenas o apontador de ativo (nao ha
            // outfits ligadas a sidekicks neste servidor ainda).
            Db::exec('UPDATE `inventory` SET sidekick_id = 0 WHERE character_id = ? AND sidekick_id = ?', [$cid, $sidekickId]);
            break;

        // ------------------------------------------------------------------
        case 'reskillSidekick': {
            $row = Sidekick::findOwned($cid, $sidekickId);
            if ($row === null) {
                break;
            }
            $debitPremium((int)Sidekick::scalars()['sidekick_reskill_premium_currency_amount']);
            $skills = Sidekick::randomSkills();
            Db::exec(
                'UPDATE `sidekicks` SET stage1_skill_id = ?, stage2_skill_id = ?, stage3_skill_id = ? WHERE id = ?',
                [$skills[1], $skills[2], $skills[3], $sidekickId]
            );
            $extra['sidekick'] = Sidekick::findOwned($cid, $sidekickId);
            break;
        }

        // ------------------------------------------------------------------
        case 'mergeSidekick': {
            // Funde a skill `skill_stage` da source no target; target recomeca
            // (nivel 1/xp 0); source e ELIMINADA na fase confirmada.
            $targetId = (int)($params['target_sidekick_id'] ?? 0);
            $sourceId = (int)($params['source_sidekick_id'] ?? 0);
            $stage = min(3, max(1, (int)($params['skill_stage'] ?? 1)));
            $target = Sidekick::findOwned($cid, $targetId);
            $source = Sidekick::findOwned($cid, $sourceId);
            if ($target === null || $source === null || $targetId === $sourceId) {
                break;
            }
            $skillCol = 'stage' . $stage . '_skill_id';

            if (!$bool($params['confirm'] ?? false)) {
                // FASE 1 — preview: DO do target com a skill nova aplicada, nada gravado.
                $preview = $target;
                $preview[$skillCol] = (int)$source[$skillCol];
                $extra['merged_sidekick'] = $preview;
                break;
            }

            // FASE 2 — confirma: debita, transfere, reseta e elimina a source.
            $debitPremium((int)Sidekick::scalars()['sidekick_merge_premium_currency_amount']);
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                // reset nivel/xp -> stats recalculam para os bases (nivel 1)
                $sets = [
                    $skillCol => (int)$source[$skillCol],
                    'level' => 1,
                    'xp' => 0,
                ];
                foreach (['stamina', 'strength', 'critical_rating', 'dodge_rating'] as $stat) {
                    $sets['stat_' . $stat] = (int)Db::value("SELECT stat_base_{$stat} FROM sidekicks WHERE id = ? AND character_id = ?", [$targetId, $cid]);
                }
                $sql = 'UPDATE `sidekicks` SET ';
                $bind = [];
                foreach ($sets as $col => $value) {
                    $sql .= '`' . $col . '` = ?, ';
                    $bind[] = $value;
                }
                $bind[] = $targetId;
                Db::exec(rtrim($sql, ', ') . ' WHERE id = ?', $bind);

                Sidekick::deleteImprovement($cid, $sourceId);
                Db::exec('DELETE FROM `sidekicks` WHERE id = ?', [$sourceId]);
                Db::exec('UPDATE `inventory` SET sidekick_id = 0 WHERE character_id = ? AND sidekick_id = ?', [$cid, $sourceId]);
                $forgetFromOrders($sourceId);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            // GOAL: fusao concluida.
            $char->bumpGoalStat('first_sidekick_merged');
            $char->bumpGoalStat('sidekick_merged');
            break;
        }

        // ------------------------------------------------------------------
        case 'sewSidekick': {
            // Coser a skin de outro sidekick neste (visual apenas).
            $targetId = (int)($params['target_sidekick_id'] ?? 0);
            $target = Sidekick::findOwned($cid, $targetId);
            if ($target === null) {
                break;
            }
            $sewedIdentifier = trim((string)($params['sewed_identifier'] ?? ''));
            if ($sewedIdentifier !== '' && !isset(Sidekick::official()['data'][$sewedIdentifier])) {
                break; // identificador desconhecido no catalogo
            }
            $sewedLevel = min(3, max(0, (int)($params['sewed_level'] ?? 0)));
            $debitPremium((int)Sidekick::scalars()['sidekick_sew_premium_currency_amount']);
            Db::exec(
                'UPDATE `sidekicks` SET sewed_identifier = ?, sewed_level = ? WHERE id = ?',
                [$sewedIdentifier, $sewedLevel, $targetId]
            );
            break;
        }

        // ------------------------------------------------------------------
        case 'setSidekickOrder': {
            // move_up/move_to_end reordenam dentro da propria lista (clamp nos
            // extremos, igual ao servidor oficial antigo); is_favorit alterna a
            // marcacao de favorito. Favoritos aparecem primeiro no cliente.
            $owned = Sidekick::findOwned($cid, $sidekickId);
            if ($owned === null) {
                break; // nunca ordenar/marcar um sidekick que nao e nosso
            }
            $orders = Sidekick::ordersData($cid);

            $isFavoritParam = isset($params['is_favorit']) ? $bool($params['is_favorit']) : null;
            if ($isFavoritParam === true && !in_array($sidekickId, $orders['favorit_orders'], true)) {
                $orders['favorit_orders'][] = $sidekickId;
            } elseif ($isFavoritParam === false) {
                $orders['favorit_orders'] = array_values(array_diff($orders['favorit_orders'], [$sidekickId]));
            }

            // Reordena na lista onde o sidekick fica (favoritos ordenam-se entre si).
            $inFavorits = in_array($sidekickId, $orders['favorit_orders'], true);
            $list = $inFavorits ? 'favorit_orders' : 'orders';
            $idx = array_search($sidekickId, $orders[$list], true);
            if ($idx === false) {
                $orders[$list][] = $sidekickId;
                $idx = count($orders[$list]) - 1;
            }
            $count = count($orders[$list]);
            $moveUp = $bool($params['move_up'] ?? true);
            $moveToEnd = $bool($params['move_to_end'] ?? false);
            $dest = $moveUp
                ? ($moveToEnd ? 0 : $idx - 1)
                : ($moveToEnd ? $count - 1 : $idx + 1);
            $dest = max(0, min($count - 1, $dest));
            if ($dest !== $idx) {
                $item = $orders[$list][$idx];
                array_splice($orders[$list], (int)$idx, 1);
                array_splice($orders[$list], $dest, 0, [$item]);
            }

            Sidekick::saveOrders($cid, $orders['orders'], $orders['favorit_orders']);
            break;
        }

        default:
            // Handler default dos dialogs lanca "Unsupported request action" no
            // cliente — nunca deve chegar aqui (Router mapeia as 9 acoes).
            break;
    }

    // Acoes que alteram posse de sidekicks precisam de all_sidekicks actualizado.
    static $needsCatalog = ['getSidekicks', 'releaseSidekick', 'mergeSidekick', 'sellSidekick'];
    $data = Live::accountState($userId);
    return array_merge($data, $extra + (in_array($action, $needsCatalog, true) ? [
        'all_sidekicks' => Sidekick::catalogEntries($cid),
    ] : []));
};
