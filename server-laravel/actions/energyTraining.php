<?php
declare(strict_types=1);

use HeroZero\GameTime;

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GuildArtifacts;
use HeroZero\Live;

/**
 * Energia de quest, stamina de duelo/liga e extras de treino.
 * Custos em premium seguem o padrao dos handlers ja capturados (flat, barato)
 * quando o HAR nao tem a resposta real.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $char->syncDailyReset();
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

    // Maximus EFETIVOS: as colunas max_* da BD guardam a base; os trofeus da
    // guilda (type 2 Energia / type 1 Coragem) elevam o teto acima delas.
    $gid = (int)$char->field('guild_id');
    $maxQuestEnergy = GuildArtifacts::effectiveMax(
        'quest_energy', (int)$char->field('max_quest_energy'), $gid
    ) * \HeroZero\BonusEvents::factor(1);
    $maxDuelStamina = GuildArtifacts::effectiveMax(
        'duel_stamina', (int)$char->field('max_duel_stamina'), $gid
    );

    switch ($action) {
        case 'buyQuestEnergy': {
            // quest_energy_refill_amount = 50 (constants). Limite diario: 200 energias/dia
            // (quest_max_refill_amount_per_day = 200 → 4 recargas de 50).
            $refillsToday = (int)$char->field('quest_energy_refill_amount_today');
            if ($refillsToday >= 200) {
                throw new GameError('errBuyQuestEnergyDailyLimitReached');
            }
            $usePremium = ($params['use_premium'] ?? 'true') !== 'false';
            if ($usePremium) {
                $spendPremium($userId, 2);
            } else {
                // Custo em moedas: cost_factor[tier] × gameCurrencyPerTime(level)
                // gameCurrencyPerTime(lvl) = 0.02 + 0.01 × (0.35 × lvl)^1.55
                // Tier = compras já feitas hoje (0–3), cada compra vale 50.
                $costFactors = [1800, 6300, 10800, 15300];
                $tier        = min((int)($refillsToday / 50), 3);
                $factor      = $costFactors[$tier];
                $lvl         = max(1, (int)$char->field('level'));
                $cpt         = 0.02 + 0.01 * (0.35 * $lvl) ** 1.55;
                $moedasCost  = (int)round($factor * $cpt);
                $affected    = Db::exec(
                    'UPDATE `character` SET game_currency = game_currency - ? WHERE id = ? AND game_currency >= ?',
                    [$moedasCost, $cid, $moedasCost]
                );
                if ($affected !== 1) {
                    throw new GameError('errRemoveGameCurrencyNotEnough');
                }
            }
            // quest_energy_refill_amount = 50 (constants_json.data)
            Db::exec('UPDATE `character`
                         SET quest_energy = LEAST(?, quest_energy + 50),
                             quest_energy_refill_amount_today = quest_energy_refill_amount_today + 50,
                             ts_last_action = ?
                       WHERE id = ?', [$maxQuestEnergy, GameTime::now(), $cid]);
            break;
        }

        case 'buyDuelStamina':
            $spendPremium($userId, 2);
            Db::exec('UPDATE `character`
                         SET duel_stamina = ?,
                             ts_last_duel_stamina_change = ?, ts_last_action = ?
                       WHERE id = ?', [$maxDuelStamina, GameTime::now(), GameTime::now(), $cid]);
            break;

        case 'buyEnergyStorage': {
            // Deposito max 100 energias; cada compra adiciona 50 (2 donuts).
            $currentStorage = (int)$char->field('current_energy_storage');
            if ($currentStorage >= 100) {
                throw new GameError('errEnergyStorageFull');
            }
            $spendPremium($userId, 2);
            Db::exec('UPDATE `character`
                         SET current_energy_storage = LEAST(100, current_energy_storage + 50),
                             ts_last_action = ?
                       WHERE id = ?', [GameTime::now(), $cid]);
            break;
        }

        case 'useEnergyStorage':
            Db::exec('UPDATE `character`
                         SET quest_energy = LEAST(?, quest_energy + current_energy_storage),
                             current_energy_storage = 0, ts_last_action = ?
                       WHERE id = ? AND current_energy_storage > 0', [$maxQuestEnergy, GameTime::now(), $cid]);
            break;

        case 'useTrainingStorage':
            Db::exec('UPDATE `character`
                         SET training_count = GREATEST(0, training_count - current_training_storage),
                             current_training_storage = 0, ts_last_action = ?
                       WHERE id = ? AND current_training_storage > 0', [GameTime::now(), $cid]);
            break;

        case 'skipTrainingCooldown':
            $spendPremium($userId, 1);
            Db::exec('UPDATE `character` SET ts_last_training = 0, ts_last_action = ? WHERE id = ?', [GameTime::now(), $cid]);
            break;

        case 'extendTrainingTime':
            $spendPremium($userId, 1);
            Db::exec('UPDATE `training` SET ts_complete = ts_complete + 900 WHERE character_id = ? AND status = 1', [$cid]);
            break;

        case 'buyTrainingSenseBooster': {
            $duration = max(3600, (int)($params['duration'] ?? 21600));
            $spendPremium($userId, max(1, (int)ceil($duration / 21600)));
            // O treino usa o mesmo campo de sense boost do personagem.
            $base = max(GameTime::now(), (int)Db::value('SELECT ts_active_sense_boost_expires FROM `character` WHERE id = ?', [$cid]));
            Db::exec('UPDATE `character` SET ts_active_sense_boost_expires = ?, ts_last_action = ? WHERE id = ?', [$base + $duration, GameTime::now(), $cid]);
            break;
        }

        case 'refreshQuestsAndTrainings':
            $spendPremium($userId, 1);
            $char->regenerateQuests();
            break;

        default:
            break;
    }

    return Live::accountState($userId);
};
