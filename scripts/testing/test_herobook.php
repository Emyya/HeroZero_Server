<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Herobook;

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "OK: {$message}\n";
}
function rejects(callable $fn, string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage() === $error, $error); return; }
    throw new RuntimeException('Pedido devia falhar: ' . $error);
}
$constants = Herobook::constants();
check(count($constants['herobook_objectives']) === 53, 'Catálogo oficial: 53 objetivos');
check(Herobook::seasonPoints(1, 'gods_1') === 1 && Herobook::seasonPoints(2, 'gods_1') === 5,
    'Season gods_1: 1 ponto diário, 5 semanais');
$initGame = require dirname(__DIR__, 2).'/server-laravel/actions/initGame.php';
$clientConstants = $initGame([])['constants'];
foreach (HeroZero\Season::rotation() as $season) {
    check(Herobook::seasonPoints(1, $season) === 1 && Herobook::seasonPoints(2, $season) === 5
        && $clientConstants['season_locations'][14][$season] === 1
        && $clientConstants['season_locations'][13][$season] === 5,
        "Chamadas $season: cliente e resgate usam 1/5 pontos");
}
foreach ([1=>'herobook_daily_rewards',2=>'herobook_weekly_rewards'] as $duration=>$key) {
    $ticket = 1;
    $seen = [];
    foreach ($constants[$key] as $definition) {
        $reward = Herobook::rollReward(40, $duration, 1.0, $ticket);
        check(count($reward) === 1 && reset($reward) > 0, "Prémio tipo {$definition['type']} duração {$duration} válido");
        $seen[] = (int)$definition['type'];
        $ticket += (int)$definition['chance'];
    }
    check(count(array_unique($seen)) === 9, "Todos os nove tipos de prémio da duração {$duration}");
}
foreach ([1 => 'daily', 2 => 'weekly'] as $duration => $period) {
    $pool = array_filter($constants['herobook_objectives'], static fn(array $d): bool =>
        in_array((int)$d['type'], Herobook::SUPPORTED_TYPES, true) && (int)$d['min_level'] <= 40 && (int)$d[$period] > 0);
    check(count($pool) >= 3, "Pool {$period} viável no nível mínimo");
    check(Herobook::deadline($duration) > GameTime::now(), "Prazo {$period} futuro");
}
if (($argv[1] ?? '') === '--offline') exit(0);

$pdo = Db::pdo();
$pdo->beginTransaction();
try {
    $cid = (int)Db::value('SELECT id FROM `character` WHERE level >= 40 ORDER BY id LIMIT 1');
    if (!$cid) throw new RuntimeException('Necessário herói nível 40+.');
    Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
    Db::exec('UPDATE herobook_objectives SET status = 4, ts_end = 0 WHERE character_id = ?', [$cid]);
    $data = Herobook::data($cid);
    $rows = array_filter($data, 'is_array');
    check(count($rows) === 5, 'Gera 3 chamadas diárias e 2 semanais');
    check(count(array_filter(Herobook::data($cid), 'is_array')) === 5, 'Abrir novamente não duplica');
    $row = reset($rows);
    $id = (int)$row['id'];
    // Fixar XP só neste fixture, para teste determinístico do resgate.
    $row['rewards'] = '{"xp":100}';
    Db::exec('UPDATE herobook_objectives SET rewards = ? WHERE id = ?', [$row['rewards'], $id]);
    rejects(fn() => Herobook::act($cid, $id, 'collectHerobookReward'), 'errInvalidHerobookObjectiveId');
    Herobook::record($cid, (int)$row['type'], (int)$row['max_value']);
    check((int)Db::value('SELECT status FROM herobook_objectives WHERE id = ?', [$id]) === 2, 'Atividade completa objetivo');
    $xp = (int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]);
    $progress = HeroZero\Season::ensureProgress($cid);
    $points = (int)$progress['season_points'];
    Herobook::act($cid, $id, 'collectHerobookReward');
    check((int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]) === $xp + json_decode($row['rewards'], true)['xp'], 'Entrega XP');
    $expectedPoints = $points + Herobook::seasonPoints((int)$row['duration_type'], $progress['identifier']);
    check((int)Db::value('SELECT season_points FROM season_progress WHERE character_id = ?', [$cid]) === $expectedPoints,
        'Resgate avança pontos da temporada');
    rejects(fn() => Herobook::act($cid, $id, 'collectHerobookReward'), 'errInvalidHerobookObjectiveId');
    check((int)Db::value('SELECT season_points FROM season_progress WHERE character_id = ?', [$cid]) === $expectedPoints,
        'Resgate duplicado não duplica pontos da temporada');
    $row = Db::row('SELECT * FROM herobook_objectives WHERE character_id = ? AND status = 1 ORDER BY id LIMIT 1', [$cid]);
    $uid = (int)Db::value('SELECT user_id FROM `character` WHERE id = ?', [$cid]);
    Db::exec('UPDATE `user` SET premium_currency = 100 WHERE id = ?', [$uid]);
    Herobook::act($cid, (int)$row['id'], 'renewHerobookObjective');
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [$uid]) === 99, 'Troca cobra 1 donut');
    check((int)Db::value('SELECT status FROM herobook_objectives WHERE id = ?', [(int)$row['id']]) === 4, 'Troca aborta o anterior');
    $new = Db::row('SELECT * FROM herobook_objectives WHERE character_id = ? AND status = 1 ORDER BY id DESC LIMIT 1', [$cid]);
    Herobook::act($cid, (int)$new['id'], 'instantFinishHerobookObjective');
    check((int)Db::value('SELECT status FROM herobook_objectives WHERE id = ?', [(int)$new['id']]) === 2, 'Conclusão por donuts permite resgatar');
    Db::exec('UPDATE `user` SET premium_currency = 0 WHERE id = ?', [$uid]);
    $pending = Db::row('SELECT id FROM herobook_objectives WHERE character_id = ? AND status = 1 LIMIT 1', [$cid]);
    rejects(fn() => Herobook::act($cid, (int)$pending['id'], 'instantFinishHerobookObjective'), 'errRemovePremiumCurrencyNotEnough');
    Db::exec('UPDATE herobook_objectives SET ts_end = ? WHERE id = ?', [GameTime::now() - 1, (int)$pending['id']]);
    rejects(fn() => Herobook::act($cid, (int)$pending['id'], 'renewHerobookObjective'), 'errInvalidHerobookObjectiveId');
    Herobook::data($cid);
    check((int)Db::value('SELECT status FROM herobook_objectives WHERE id = ?', [(int)$pending['id']]) === 4,
        'Objetivo expirado incompleto fica abortado');
    check((int)Db::value('SELECT COUNT(*) FROM herobook_objectives WHERE character_id = ? AND status <> 4 AND ts_end > ?', [$cid, GameTime::now()]) === 5,
        'Renovação repõe as cinco vagas');
    // Temas antigos sem pontos oficiais: reproduzir a rotação e o resgate real.
    foreach (['beetle', 'bear'] as $season) {
        $index = array_search($season, HeroZero\Season::rotation(), true);
        Db::exec('UPDATE season_calendar SET ts_anchor = ? WHERE id = 1',
            [GameTime::now() - $index * 30 * 86400]);
        $progress = HeroZero\Season::ensureProgress($cid);
        check($progress['identifier'] === $season, "Rotação entra em $season");
        $points = (int)$progress['season_points'];
        foreach ([1 => 1, 2 => 5] as $duration => $amount) {
            $objective = Db::row('SELECT id FROM herobook_objectives WHERE character_id = ? AND duration_type = ? AND status = 1 AND ts_end > ? LIMIT 1',
                [$cid, $duration, GameTime::now()]);
            if (!$objective) throw new RuntimeException('Sem chamada livre para o teste.');
            $id = (int)$objective['id'];
            Db::exec('UPDATE herobook_objectives SET status = 2, rewards = ? WHERE id = ?', ['{"xp":1}', $id]);
            Herobook::act($cid, $id, 'collectHerobookReward');
            $points += $amount;
            check((int)Db::value('SELECT season_points FROM season_progress WHERE character_id = ?', [$cid]) === $points,
                "$season duração $duration entrega $amount pontos");
            rejects(fn() => Herobook::act($cid, $id, 'collectHerobookReward'), 'errInvalidHerobookObjectiveId');
            check((int)Db::value('SELECT season_points FROM season_progress WHERE character_id = ?', [$cid]) === $points,
                "$season não duplica pontos");
            Db::exec('UPDATE herobook_objectives SET status = 2 WHERE id = ?', [$id]);
            Herobook::act($cid, $id, 'collectHerobookReward', true);
            check((int)Db::value('SELECT season_points FROM season_progress WHERE character_id = ?', [$cid]) === $points,
                "$season descarte não dá pontos");
            // Repõe só o fixture para o próximo tema, dentro da transacção.
            Db::exec('UPDATE herobook_objectives SET status = 1 WHERE id = ?', [$id]);
        }
    }
} finally {
    $pdo->rollBack();
}
echo "Integração concluída; alterações revertidas.\n";
