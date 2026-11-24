<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, $e->getMessage()."\n"); exit(1); });
use HeroZero\{BonusEvents, DailyLogin, GameTime, Db, Character, GameError, Live};
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "OK: $label\n"; }
function reject(callable $fn, string $error): void {
    try { $fn(); } catch (GameError $e) { check($e->getMessage() === $error, $error); return; }
    throw new RuntimeException('Pedido devia falhar: '.$error);
}
// Relógio apenas em memória: nunca altera o offset persistente do servidor.
function clockAt(string $date): void {
    $ts = (new DateTimeImmutable($date, Live::gameTimezone()))->getTimestamp();
    (new ReflectionProperty(GameTime::class, 'offset'))->setValue(null, $ts - GameTime::epoch() - GameTime::uptime());
}
check(count(DailyLogin::catalog()) === 30, 'catálogo oficial de 30 dias');
check(DailyLogin::rewards(31, 100) === DailyLogin::rewards(1, 100), 'ciclo local continua depois do dia 30');
check(DailyLogin::rewards(5, 100)['premium'] === 3, '3 donuts e chave premium do cliente');
check(DailyLogin::rewards(28, 100)['stat_points'] === 15, '15 pontos de atributos');
check(BonusEvents::calendar('2026-10-24', 3) === ['2026-10-24','2026-10-25','2026-10-26'], 'calendário atravessa mudança de hora');
if (!in_array('--db', $argv, true)) { echo "Offline concluído. Integração: --db [nome do herói]; usa rollback.\n"; exit; }
$name = $argv[2] ?? null;
$cid = (int)($name ? Db::value('SELECT id FROM `character` WHERE name = ? AND user_id > 0', [$name])
    : Db::value('SELECT id FROM `character` WHERE user_id > 0 ORDER BY id LIMIT 1'));
if (!$cid) throw new RuntimeException('Herói não encontrado.');
$pdo = Db::pdo();
// Tournament usa a facade Laravel: partilhar PDO para não escapar ao rollback.
Illuminate\Support\Facades\DB::connection()->setPdo($pdo);
Illuminate\Support\Facades\DB::connection()->setReadPdo($pdo);
$pdo->beginTransaction();
try {
    clockAt('2030-01-10 00:00:01');
    Db::exec('DELETE FROM bonus_event_days'); Db::exec('DELETE FROM bonus_events');
    Db::exec('DELETE FROM daily_login_rewards WHERE character_id = ?', [$cid]);
    Db::exec('DELETE FROM daily_bonus_resources WHERE character_id = ?', [$cid]);
    Db::exec('UPDATE `character` SET level = 100, ts_last_daily_login_bonus = 0, daily_login_bonus_day = 1,
        guild_id = 0, max_quest_energy = 100, quest_energy = 12, ts_last_quest_energy_refill = ?,
        training_count = 3, max_training_count = 10, ts_last_training = ? WHERE id = ?', [GameTime::now(), GameTime::now(), $cid]);
    $uid = (int)Db::value('SELECT user_id FROM `character` WHERE id = ?', [$cid]);
    $p = DailyLogin::pending($cid);
    check($p['login_day'] == 1 && DailyLogin::pending($cid)['id'] === $p['id'], 'preview estável e primeiro dia');
    $before = (int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]);
    DailyLogin::claim($cid, (int)$p['id'], false);
    check((int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]) === $before + 5000, 'moedas entregues');
    reject(fn() => DailyLogin::claim($cid, (int)$p['id'], false), 'errCollectDailyLoginRewardInvalidStatus');
    check(DailyLogin::pending($cid) === null, 'um resgate por data');
    clockAt('2030-01-11 00:00:01');
    $p = DailyLogin::pending($cid); check($p['login_day'] == 2, 'dia seguinte avança');
    reject(fn() => DailyLogin::claim($cid, (int)$p['id'] + 99999, false), 'errCollectDailyLoginRewardInvalidStatus');
    $xp = (int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]);
    DailyLogin::claim($cid, (int)$p['id'], false);
    check((int)Db::value('SELECT xp FROM `character` WHERE id = ?', [$cid]) === $xp + (int)json_decode($p['rewards'], true)['xp'], 'XP entregue');
    clockAt('2030-01-12 00:00:01');
    Db::exec('UPDATE `character` SET daily_login_bonus_day = 4 WHERE id = ?', [$cid]);
    $p = DailyLogin::pending($cid); $donuts = (int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [$uid]);
    DailyLogin::claim($cid, (int)$p['id'], false);
    check((int)Db::value('SELECT premium_currency FROM `user` WHERE id = ?', [$uid]) === $donuts + 3, 'donuts creditados à conta');
    clockAt('2030-01-13 00:00:01');
    $p = DailyLogin::pending($cid); check($p['item_id'] > 0, 'dia 6 cria épico real');
    $data = DailyLogin::attach(['items'=>[]], $cid);
    check((int)$data['items'][0]['id'] === (int)$p['item_id'], 'preview incluído antes de desenhar');
    $sets = []; for ($i=1;$i<=18;$i++) $sets[] = 'bag_item'.$i.'_id = 2147483647';
    Db::exec('UPDATE inventory SET '.implode(',', $sets).' WHERE character_id = ?', [$cid]);
    reject(fn() => DailyLogin::claim($cid, (int)$p['id'], false), 'errInventoryNoEmptySlot');
    check((int)Db::value('SELECT status FROM daily_login_rewards WHERE id = ?', [$p['id']]) === 0, 'falha reverte prémio e marcador');
    Db::exec('UPDATE inventory SET bag_item1_id = 0 WHERE character_id = ?', [$cid]);
    DailyLogin::claim($cid, (int)$p['id'], false);
    check((int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id = ?', [$cid]) === (int)$p['item_id'], 'épico entregue à mochila');
    clockAt('2030-01-14 00:00:01');
    Db::exec('UPDATE `character` SET daily_login_bonus_day = 10 WHERE id = ?', [$cid]);
    $p = DailyLogin::pending($cid); $vouchers = (int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id = ?', [$uid]);
    DailyLogin::claim($cid, (int)$p['id'], false);
    check((int)Db::value('SELECT COUNT(*) FROM vouchers WHERE user_id = ?', [$uid]) === $vouchers + 2, 'recursos entregues como cupões pessoais');

    $event = BonusEvents::create('Teste rollback', BonusEvents::today(), 3, [1,5,7,8,10]);
    check(count(BonusEvents::payload()['bonus_datas']) === 15 && BonusEvents::factor(7) === 2, 'calendário de 3 dias e cinco vantagens');
    try { BonusEvents::create('Sobreposto', BonusEvents::today(), 1, [7]); throw new RuntimeException('Sobreposição aceite'); }
    catch (PDOException $e) { check((int)$e->errorInfo[1] === 1062, 'sobreposição rejeitada atomicamente'); }
    $char = Character::load($cid); $char->syncDailyReset(); $char->syncTrainingForLevel();
    check((int)Db::value('SELECT quest_energy FROM `character` WHERE id = ?', [$cid]) === 200, 'reset concede energia dupla');
    check((int)Db::value('SELECT training_count FROM `character` WHERE id = ?', [$cid]) === 20, 'reset concede treinos duplos');
    Db::exec('UPDATE `character` SET quest_energy = 180, training_count = 17 WHERE id = ?', [$cid]);
    $char = Character::load($cid); $char->syncDailyReset(); $char->syncTrainingForLevel();
    check((int)$char->field('quest_energy') === 180 && (int)$char->field('training_count') === 17, 'sync não volta a encher recursos');
    // Fórmula autoritativa de energia de treino, com base 30 enviada ao cliente.
    Db::exec('UPDATE `character` SET training_energy = 60, max_training_energy = 30, ts_last_training_energy_change = ? WHERE id = ?', [GameTime::now(),$cid]);
    $energy = (new ReflectionMethod(Character::class, 'liveTrainingEnergy'))->invoke(Character::load($cid));
    check($energy === 60, 'barra de treino permite 60 sem duplicar base');
    // Resgate de missão real: base persistida permanece 321 moedas / 123 XP.
    $quest = Db::row('SELECT id FROM quests WHERE character_id = ? LIMIT 1', [$cid]);
    if (!$quest) throw new RuntimeException('Herói sem missões para testar.');
    Db::exec('UPDATE quests SET status = 3, type = 1, rewards = ? WHERE id = ?', [json_encode(['coins'=>321,'xp'=>123]), $quest['id']]);
    Db::exec('UPDATE `character` SET active_quest_id = ? WHERE id = ?', [$quest['id'], $cid]);
    $before = Db::row('SELECT xp, game_currency FROM `character` WHERE id = ?', [$cid]);
    Character::load($cid)->claimQuestRewards();
    $after = Db::row('SELECT xp, game_currency FROM `character` WHERE id = ?', [$cid]);
    check((int)$after['xp'] - (int)$before['xp'] === 246 && (int)$after['game_currency'] - (int)$before['game_currency'] === 642,
        'resgate de missão entrega XP e moedas duplos');
    // Nenhuma fuga de previews ao consultar outro jogador: attach só em accountState/boot próprios.
    $state = Live::accountState($uid);
    check($state['character']['max_quest_energy'] == 100 && $state['character']['max_training_count'] == 10,
        'protocolo envia máximos base para o cliente multiplicar uma vez');
    check(isset($state['bonus_info']['bonus_datas']), 'estado normal inclui calendário');
    BonusEvents::cancel($event);
    check(BonusEvents::factor(7) === 1 && BonusEvents::payload()['bonus_datas'] === [], 'cancelar termina vantagens e anúncio');
    // Activação a meio do dia, depois de o jogador gastar recursos.
    Db::exec('UPDATE `character` SET quest_energy = 12, training_count = 3, ts_last_quest_energy_refill = ?, ts_last_training = ? WHERE id = ?',
        [GameTime::now(),GameTime::now(),$cid]);
    Db::exec('UPDATE daily_bonus_resources SET quest_date = ?, training_date = ?, quest_factor = 1, training_factor = 1 WHERE character_id = ?',
        [BonusEvents::today(),BonusEvents::today(),$cid]);
    $midday = BonusEvents::create('Meio do dia', BonusEvents::today(), 1, [1,5,10]);
    $char = Character::load($cid); $char->syncDailyReset(); $char->syncTrainingForLevel();
    check((int)$char->field('quest_energy') === 112 && (int)$char->field('training_count') === 13, 'activação a meio do dia concede só o acréscimo');
    Db::exec('UPDATE `character` SET training_energy = 31, ts_last_training_energy_change = ? WHERE id = ?', [GameTime::now(),$cid]);
    Character::load($cid)->buyTrainingEnergy(0);
    check((int)Db::value('SELECT training_energy FROM `character` WHERE id = ?',[$cid]) === 46, 'recarga usa máximo de evento e savepoint');
    Db::exec('UPDATE training SET status = 3 WHERE character_id = ?',[$cid]);
    $char = Character::load($cid);
    $pool = (new ReflectionMethod(Character::class, 'currentTrainingPool'))->invoke($char);
    $char->startTraining((int)$pool[0]);
    check((int)Db::value('SELECT training_energy FROM `character` WHERE id = ?',[$cid]) === 60, 'início de treino enche barra até ao máximo de evento');
    BonusEvents::cancel($midday);
    $char = Character::load($cid); $char->syncDailyReset(); $char->syncTrainingForLevel();
    $before = Db::row('SELECT quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
    $again = BonusEvents::create('Reactivação',BonusEvents::today(),1,[1,5]);
    $char = Character::load($cid); $char->syncDailyReset(); $char->syncTrainingForLevel();
    check(Db::row('SELECT quest_energy, training_count FROM `character` WHERE id = ?',[$cid]) === $before, 'cancelar e reactivar não duplica concessão no mesmo dia');
    BonusEvents::cancel($again);
    clockAt('2030-01-15 00:00:01');
    Character::load($cid)->syncDailyReset();
    check((int)Db::value('SELECT quest_energy FROM `character` WHERE id = ?', [$cid]) === 100, 'dia sem evento volta à energia normal');
    clockAt('2030-01-16 00:00:01');
    Db::exec('UPDATE `character` SET daily_login_bonus_day = 5 WHERE id = ?', [$cid]);
    $old = DailyLogin::pending($cid);
    clockAt('2030-01-17 00:00:01');
    $next = DailyLogin::pending($cid);
    check($next['login_day'] == $old['login_day'] && $next['id'] != $old['id'], 'dia ignorado não avança e recebe novo ID');
    check(!Db::value('SELECT id FROM items WHERE id = ?', [$old['item_id']]), 'preview expirado eliminado');
    reject(fn() => DailyLogin::claim($cid, (int)$old['id'], false), 'errCollectDailyLoginRewardInvalidStatus');
    $other = (int)Db::value('SELECT id FROM `character` WHERE user_id > 0 AND id <> ? LIMIT 1', [$cid]);
    if ($other) reject(fn() => DailyLogin::claim($other, (int)$next['id'], false), 'errCollectDailyLoginRewardInvalidStatus');
    $coins = (int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]);
    $sell = (int)Db::value('SELECT sell_price FROM items WHERE id = ?', [$next['item_id']]);
    DailyLogin::claim($cid, (int)$next['id'], true);
    check((int)Db::value('SELECT game_currency FROM `character` WHERE id = ?', [$cid]) === $coins + $sell
        && !Db::value('SELECT id FROM items WHERE id = ?', [$next['item_id']]), 'descartar vende o épico atomicamente');
    clockAt('2030-07-01 23:59:58');
    $summer = DailyLogin::pending($cid); DailyLogin::claim($cid, (int)$summer['id'], false);
    clockAt('2030-07-02 00:00:01');
    check(DailyLogin::pending($cid) !== null, 'meia-noite de Lisboa permite login antes da meia-noite UTC no verão');
    $bootHandler = require dirname(__DIR__,2).'/server-laravel/actions/autoLoginUser.php';
    $boot = $bootHandler(['user_id'=>$uid, 'existing_user_id'=>$uid,
        'existing_session_id'=>Db::value('SELECT session_id FROM `user` WHERE id = ?', [$uid])]);
    check(isset($boot['daily_login_bonus_day'], $boot['daily_login_bonus_rewards'], $boot['bonus_info']),
        'login real inclui recompensa diária e calendário');
    echo "Integração MySQL concluída; todas as alterações de teste revertidas.\n";
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
