<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Boosters alem dos ja cobertos por buyBooster/buySenseBooster.
 * So cobra premium quando ha efeito persistido (coluna correspondente);
 * O energético do esconderijo persiste a janela real da produção.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $spendPremium = static function (int $userId, int $cost): void {
        $affected = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [$cost, $userId, $cost]
        );
        if ($affected !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    };

    switch ($action) {
        case 'buyLeagueBooster': {
            // Preco do CATALOGO oficial (constants "boosters"): booster_league1 =
            // 4 dias por 2057 MOEDAS; booster_league2 = 7/30/90 dias por 10/40/115
            // donuts. Antes cobrava ceil(dias) em donuts para qualquer par.
            $boosterId = (string)($params['id'] ?? $params['booster_id'] ?? $params['identifier'] ?? 'booster_league1');
            $duration = (int)($params['duration'] ?? 0);
            $costEntry = Live::boosterCost($boosterId, $duration);
            if ($costEntry === null) {
                throw new GameError('errRequestInvalidParameter');
            }
            [$isPremium, $baseCost] = $costEntry;
            if ($isPremium) {
                $spendPremium($userId, $baseCost);
            } else {
                $debited = Db::exec(
                    'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                    [$baseCost, $cid, $baseCost]
                );
                if ($debited !== 1) {
                    throw new GameError('errRemoveGameCurrencyNotEnough');
                }
            }
            // Empilha no prazo corrente (renovar antes de expirar soma tempo).
            // O novo MAXIMO da barra (60/120) e derivado do booster ativo pelo
            // League::syncStamina na proxima sincronizacao -- sem UPDATE manual.
            $base = max(GameTime::now(), (int)Db::value('SELECT ts_active_league_boost_expires FROM `character` WHERE id = ?', [$cid]));
            Db::exec('UPDATE `character`
                         SET active_league_booster_id = ?, ts_active_league_boost_expires = ?, ts_last_action = ?
                       WHERE id = ?', [$boosterId, $base + $duration, GameTime::now(), $cid]);
            break;
        }

        case 'buyMultitaskingBooster':
        case 'unlockMultitaskingBooster': {
            $duration = (int)($params['duration'] ?? -1);
            $spendPremium($userId, 10);
            // duration -1 = permanente (10 anos e alem do horizonte do cliente)
            $expires = $duration > 0 ? GameTime::now() + $duration : GameTime::now() + 315360000;
            Db::exec('UPDATE `character` SET ts_active_multitasking_boost_expires = ?, ts_last_action = ? WHERE id = ?', [$expires, GameTime::now(), $cid]);
            break;
        }

        case 'buyGuildBooster': {
            $guildId = (int)Db::value('SELECT guild_id FROM `character` WHERE id = ?', [$cid]);
            if ($guildId <= 0) break;
            $boosterId = (string)($params['id'] ?? $params['booster_id'] ?? $params['identifier'] ?? 'guild_booster_quest1');
            $duration = (int)($params['duration'] ?? 604800);
            $costEntry = Live::boosterCost($boosterId, $duration);
            if ($costEntry === null) {
                throw new GameError('errRequestInvalidParameter');
            }
            [$isPremium, $baseCost] = $costEntry;
            if ($isPremium) {
                $cost = $baseCost;
            } else {
                $guildCap = (int)Db::value('SELECT stat_guild_capacity FROM `guild` WHERE id = ?', [$guildId]);
                $cost = $baseCost * max(1, $guildCap);
            }
            $col = $isPremium ? 'premium_currency' : 'game_currency';
            $debited = Db::exec(
                "UPDATE `guild` SET `{$col}` = `{$col}` - ? WHERE id = ? AND `{$col}` >= ?",
                [$cost, $guildId, $cost]
            );
            if ($debited !== 1) {
                throw new GameError($isPremium ? 'errRemovePremiumCurrencyNotEnough' : 'errRemoveGameCurrencyNotEnough');
            }
            $catalogEntry = Live::GUILD_BOOSTER_CATALOG[$boosterId] ?? null;
            $boosterType = $catalogEntry !== null ? (int)$catalogEntry[0] : 0;
            $pair = match ($boosterType) {
                1 => ['active_training_booster_id', 'ts_active_training_boost_expires'],
                3 => ['active_duel_booster_id', 'ts_active_duel_boost_expires'],
                default => ['active_quest_booster_id', 'ts_active_quest_boost_expires'],
            };
            $base = max(GameTime::now(), (int)Db::value("SELECT `{$pair[1]}` FROM `guild` WHERE id = ?", [$guildId]));
            Db::exec("UPDATE `guild` SET `{$pair[0]}` = ?, `{$pair[1]}` = ? WHERE id = ?", [$boosterId, $base + $duration, $guildId]);
            break;
        }

        case 'buyHideoutBooster': {
            \HeroZero\Missiles::transaction([$cid],static function()use($cid,$userId,$params,$spendPremium):void {
                $id=(string)($params['id'] ?? '');$duration=(int)($params['duration'] ?? 0);
                if (!in_array($id,['booster_hideout1','booster_hideout2'],true) || !\HeroZero\Hideout::rowFor($cid)) throw new GameError('errRequestInvalidParameter');
                $cost=Live::boosterCost($id,$duration);
                if ($cost===null) throw new GameError('errRequestInvalidParameter');
                [$premium,$amount]=$cost;
                if ($premium) $spendPremium($userId,$amount);
                elseif (Db::exec('UPDATE `character` SET game_currency=game_currency-? WHERE id=? AND game_currency>=?',[$amount,$cid,$amount])!==1) throw new GameError('errRemoveGameCurrencyNotEnough');
                $expiry=(int)Db::value('SELECT ts_active_hideout_boost_expires FROM `character` WHERE id=?',[$cid]);
                \HeroZero\Hideout::activateProductionBooster($cid,$id,max(GameTime::now(),$expiry)+$duration);
            });
            break;
        }

        default:
            break;
    }

    return Live::accountState($userId);
};
