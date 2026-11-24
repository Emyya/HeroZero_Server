<?php
declare(strict_types=1);

/**
 * Teste do sistema de trofeus — TUDO dentro de transacao com ROLLBACK.
 * Nao altera estado real da base de dados.
 */

require (dirname(__DIR__, 3)) . '/server-laravel/vendor/autoload.php';
require_once (dirname(__DIR__, 3)) . '/server-laravel/app/HeroZero/Protocol.php'; // GameError vive aqui
$app = require (dirname(__DIR__, 3)) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\GuildArtifacts;

function guildRow(int $id): array
{
    return Db::row('SELECT id,name,artifact_ids,artifacts_owned_current,artifacts_owned_max,artifacts_won,artifacts_lost FROM `guild` WHERE id = ?', [$id]) ?? [];
}

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    echo "=== inicial ===\n";
    echo "  s46: " . json_encode(guildRow(1), JSON_UNESCAPED_UNICODE) . "\n";
    echo "  oxA: " . json_encode(guildRow(2), JSON_UNESCAPED_UNICODE) . "\n";
    echo "  a circular: " . GuildArtifacts::circulatingCount() . "\n";

    // --- A) ROUBO: esvazia 2 slots ao vencedor para ter espaco; oxAlpha vence
    //         por DEFESA contra sonnet46 que tem trofeus ---
    echo "\n=== A) roubo: vencedor=oxAlpha (defesa), perdedor=sonnet46 ===\n";
    Db::exec("UPDATE `guild` SET artifact_ids = '[32]', artifacts_owned_current = 1 WHERE id = 2"); // oxa 1/1 cheio → sem roubo
    $evNone = GuildArtifacts::resolveBattleTransfer(2, 1, false);
    echo "  sem slot livre: " . json_encode($evNone) . " (esperado null)\n";
    Db::exec("UPDATE `guild` SET artifact_ids = '[]', artifacts_owned_current = 0 WHERE id = 2"); // agora com slot livre
    $ev = GuildArtifacts::resolveBattleTransfer(2, 1, false);
    echo "  evento: " . json_encode($ev) . "\n";
    echo "  s46: " . json_encode(guildRow(1)) . "\n";
    echo "  oxA: " . json_encode(guildRow(2)) . "\n";

    // --- B) EMISSAO: esvazia ambas (so na transacao), sonnet46 vence por ATAQUE ---
    echo "\n=== B) emissao: ambas vazias, vencedor=sonnet46 (ataque) ===\n";
    Db::exec("UPDATE `guild` SET artifact_ids = '[]', artifacts_owned_current = 0, artifacts_won = 0, artifacts_lost = 0 WHERE id IN (1,2)");
    $ev2 = GuildArtifacts::resolveBattleTransfer(1, 2, true);
    echo "  evento: " . json_encode($ev2) . "\n";
    echo "  a circular: " . GuildArtifacts::circulatingCount() . "\n";
    echo "  oxA: " . json_encode(guildRow(2)) . "\n";

    // --- B2) sem slot livre → nada acontece: enche sonnet46 (5 slots) e limpa oxa ---
    echo "\n=== B2) vencedor SEM slot livre → null ===\n";
    Db::exec("UPDATE `guild` SET artifact_ids = '[41,4,48,6,43]', artifacts_owned_current = 5 WHERE id = 1");
    $ev3 = GuildArtifacts::resolveBattleTransfer(1, 2, true);
    echo "  evento: " . json_encode($ev3) . " (esperado null)\n";

    // --- C/D/E) releaseArtifact via action real ---
    $fn = require (dirname(__DIR__, 3)) . '/server-laravel/actions/releaseArtifact.php';
    $leader = Db::row("SELECT id,user_id,name FROM `character` WHERE guild_id = 1 AND guild_rank = 1 LIMIT 1");
    $member = Db::row("SELECT id,user_id,name FROM `character` WHERE guild_id = 1 AND guild_rank > 2 LIMIT 1");
    echo "\n=== C) cooldown (lider, ts_last_artifact_released=agora) ===\n";
    Db::exec('UPDATE `guild` SET ts_last_artifact_released = ? WHERE id = 1', [GameTime::now()]);
    try {
        $fn(['user_id' => (int)$leader['user_id'], 'id' => 41]);
        echo "  ERRO: devia falhar com cooldown\n";
    } catch (GameError $e) {
        echo "  GameError: " . $e->getMessage() . " (esperado errReleaseArtifactActiveCooldown)\n";
    }

    echo "\n=== D) permissao (membro rank>2) ===\n";
    if ($member !== null) {
        try {
            $fn(['user_id' => (int)$member['user_id'], 'id' => 41]);
            echo "  ERRO: devia falhar com permissao\n";
        } catch (GameError $e) {
            echo "  GameError: " . $e->getMessage() . " (esperado errReleaseArtifactNoPermission)\n";
        }
    } else {
        echo "  (sem membros rank>2 na guilda — salto)\n";
    }

    echo "\n=== E) sucesso (lider, sem cooldown, trofeu 41; donuts debitados + rollback) ===\n";
    $before = (int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [(int)$leader['user_id']]);
    echo "  donuts antes: {$before}\n";
    Db::exec('UPDATE `guild` SET ts_last_artifact_released = 0 WHERE id = 1');
    try {
        $resp = $fn(['user_id' => (int)$leader['user_id'], 'id' => 41]);
        echo "  resposta ok? keys=" . count($resp) . "; tem guild=" . isset($resp['guild']) . "\n";
    } catch (GameError $e) {
        echo "  GameError inesperado: " . $e->getMessage() . "\n";
    }
    echo "  s46 apos release: " . json_encode(guildRow(1)) . "\n";
} finally {
    $pdo->rollBack();
    echo "\n*** ROLLBACK feito — estado real intacto ***\n";
}
echo "estado final real:\n";
echo "  s46: " . json_encode(guildRow(1)) . "\n";
echo "  oxA: " . json_encode(guildRow(2)) . "\n";
