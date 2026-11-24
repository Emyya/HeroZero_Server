<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

/**
 * Miscelanea do personagem: flags de aparencia, estagio, reskill e bonus
 * diario de login.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    switch ($action) {
        case 'setCharacterAppearanceFlag': {
            // unico flag de aparencia persistido: mostrar/esconder mascara
            $flag = (string)($params['flag'] ?? '');
            $value = filter_var($params['value'] ?? $params['show'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            if ($flag === '' || str_contains($flag, 'mask')) {
                Db::exec('UPDATE `character` SET show_mask = ? WHERE id = ?', [$value, $cid]);
            }
            break;
        }

        case 'setCharacterStage': {
            $stage = (int)($params['stage'] ?? $params['quest_stage'] ?? 0);
            // So viaja ate o max ja destravado (quem sobe max_quest_stage e o
            // claim da story dungeon).
            $max = (int)Db::value('SELECT max_quest_stage FROM `character` WHERE id = ?', [$cid]);
            if ($stage > 0 && $stage <= $max && $stage !== (int)Db::value('SELECT current_quest_stage FROM `character` WHERE id = ?', [$cid])) {
                Db::exec('UPDATE `character` SET current_quest_stage = ? WHERE id = ?', [$stage, $cid]);
                $char = Character::loadByUser($userId);   // relê current_quest_stage
                // NAO regenerar: as missoes de cada cidade sao persistentes. Só
                // cria o pool se esta cidade ainda nao tiver nenhum (1a visita).
                // Com regenerateQuests() aqui, andar entre cidades sorteava
                // missoes novas de cada vez.
                $char->ensureQuestPool($stage);
            }
            break;
        }

        case 'reskillCharacterStats': {
            // Custo: 1 item special_reskill da mochila, ou 25 donuts.
            // O cliente envia os novos valores desejados de cada stat base;
            // o servidor valida o total (deve igualar a soma dos bases actuais
            // mais os pontos livres). Preserva stat_bought_*: o histórico de
            // compras determina o custo futuro, independentemente da distribuição.
            $reskillDonutCost = 25;
            $reskillMinStat   = 10;

            $cur = Db::row(
                'SELECT stat_base_stamina, stat_base_strength, stat_base_critical_rating,
                        stat_base_dodge_rating, stat_points_available
                   FROM `character` WHERE id = ?', [$cid]
            ) ?? [];

            $newStamina  = (int)($params['stamina']         ?? 0);
            $newStrength = (int)($params['strength']        ?? 0);
            $newCritical = (int)($params['critical_rating'] ?? 0);
            $newDodge    = (int)($params['dodge_rating']    ?? 0);

            $oldTotal = (int)$cur['stat_base_stamina']
                      + (int)$cur['stat_base_strength']
                      + (int)$cur['stat_base_critical_rating']
                      + (int)$cur['stat_base_dodge_rating']
                      + (int)$cur['stat_points_available'];
            $newTotal = $newStamina + $newStrength + $newCritical + $newDodge;

            if ($newTotal !== $oldTotal
                || $newStamina  < $reskillMinStat
                || $newStrength < $reskillMinStat
                || $newCritical < $reskillMinStat
                || $newDodge    < $reskillMinStat) {
                throw new \HeroZero\GameError('errReskillInvalidStats');
            }

            // Procurar item special_reskill na mochila
            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
            $reskillItemId = null;
            $reskillSlot   = null;
            for ($i = 1; $i <= 18; $i++) {
                $bagId = (int)($inv['bag_item' . $i . '_id'] ?? 0);
                if ($bagId <= 0) continue;
                $identifier = Db::value(
                    'SELECT identifier FROM `items` WHERE character_id = ? AND id = ?',
                    [$cid, $bagId]
                );
                if ($identifier === 'special_reskill') {
                    $reskillItemId = $bagId;
                    $reskillSlot   = 'bag_item' . $i . '_id';
                    break;
                }
            }

            if ($reskillItemId !== null) {
                // Consome o item
                Db::exec("UPDATE `inventory` SET `{$reskillSlot}` = 0 WHERE character_id = ?", [$cid]);
                Db::exec('DELETE FROM `items` WHERE character_id = ? AND id = ?', [$cid, $reskillItemId]);
            } else {
                // Paga em donuts
                $paid = Db::exec(
                    'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                    [$reskillDonutCost, $userId, $reskillDonutCost]
                );
                if ($paid < 1) throw new \HeroZero\GameError('errRemovePremiumCurrencyNotEnough');
            }

            Db::exec(
                'UPDATE `character`
                    SET stat_base_stamina          = ?,
                        stat_base_strength         = ?,
                        stat_base_critical_rating  = ?,
                        stat_base_dodge_rating     = ?,
                        stat_points_available      = 0
                  WHERE id = ?',
                [$newStamina, $newStrength, $newCritical, $newDodge, $cid]
            );
            break;
        }

        case 'claimDailyBonusRewardReward': {
            \HeroZero\DailyMilestones::claimById($cid, (int)($params['id'] ?? 0),
                filter_var($params['discard_item'] ?? false, FILTER_VALIDATE_BOOLEAN),
                (int)($params['type'] ?? 0));
            break;
        }

        case 'claimDailyLoginBonus': {
            \HeroZero\DailyLogin::claim($cid, (int)($params['id'] ?? 0),
                filter_var($params['discard_item'] ?? false, FILTER_VALIDATE_BOOLEAN));
            break;
        }

        case 'getCharacterMaxSpendableAmount': {
            $state = Live::accountState($userId);
            $char = Character::loadByUser($userId);
            $state['max_spendable_amount'] = $char->premiumCurrency();
            return $state;
        }

        // selectStorygoal / useTitle /
        // claimNewsRewards: sem persistencia propria — eco do accountState.
        default:
            break;
    }

    return Live::accountState($userId);
};
