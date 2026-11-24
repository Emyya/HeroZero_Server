<?php
declare(strict_types=1);

/**
 * action: releaseArtifact  (libertar um trofeu da guilda — docs/trofeus/implementacao.md)
 * Entrada: { id: <artifact_id> }
 *
 * Contrato do cliente (DialogReleaseArtifact/DU):
 *   sendActionRequest("releaseArtifact", {id}) →
 *   "" ok (updateData + fecha) | errRemovePremiumCurrencyNotEnough |
 *   errReleaseArtifactActiveBattle | errReleaseArtifactActiveCooldown |
 *   errReleaseArtifactNoPermission
 *
 * Regras oficiais: so lider/conselheiro; custa 40 donuts; cooldown de 7 dias
 * por guilda (ts_last_artifact_released); sem batalha pendente; o trofeu volta
 * ao pool (fica disponivel para emissao futura).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\GuildArtifacts;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $artifactId = (int)($params['id'] ?? 0);

    $char = Character::loadByUser($userId);
    $guildId = (int)($char->field('guild_id') ?? 0);
    $rank = (int)($char->field('guild_rank') ?? 0);
    // guild_rank: 1 = lider, 2 = conselheiro.
    if ($guildId <= 0 || !in_array($rank, [1, 2], true)) {
        throw new GameError('errReleaseArtifactNoPermission');
    }

    // Sem libertar trofeus com batalha a decorrer (os ids pending ficam a 0
    // quando a batalha resolve — GuildBattles::simulateGuildBattle).
    $pending = Db::row(
        'SELECT pending_guild_battle_attack_id, pending_guild_battle_defense_id FROM `guild` WHERE id = ?',
        [$guildId]
    );
    if ($pending !== null
        && ((int)$pending['pending_guild_battle_attack_id'] > 0 || (int)$pending['pending_guild_battle_defense_id'] > 0)) {
        throw new GameError('errReleaseArtifactActiveBattle');
    }

    // Cooldown: um trofeu por guilda a cada 7 dias.
    $last = (int)(Db::value('SELECT ts_last_artifact_released FROM `guild` WHERE id = ?', [$guildId]) ?? 0);
    if ($last > 0 && $last + GuildArtifacts::RELEASE_COOLDOWN > GameTime::now()) {
        throw new GameError('errReleaseArtifactActiveCooldown');
    }

    $guild = Db::row('SELECT * FROM `guild` WHERE id = ?', [$guildId]);
    if ($guild === null) {
        throw new GameError('errRequestInvalidParameter');
    }
    $ids = GuildArtifacts::ownedIds($guild);
    if (!in_array($artifactId, $ids, true)) {
        throw new GameError('errRequestInvalidParameter');
    }

    // Custo em donuts do USER (debito atomico — mesmo padrao do buyBooster).
    $debited = Db::exec(
        'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
        [GuildArtifacts::RELEASE_COST, $char->userId(), GuildArtifacts::RELEASE_COST]
    );
    if ($debited !== 1) {
        throw new GameError('errRemovePremiumCurrencyNotEnough');
    }

    $remaining = array_values(array_filter($ids, static fn(int $v): bool => $v !== $artifactId));
    Db::exec(
        'UPDATE `guild` SET artifact_ids = ?, ts_last_artifact_released = ? WHERE id = ?',
        [json_encode($remaining), GameTime::now(), $guildId]
    );
    GuildArtifacts::forgetEffects($guildId);
    GuildArtifacts::syncCounters($guildId);

    return Live::accountState($userId);
};
