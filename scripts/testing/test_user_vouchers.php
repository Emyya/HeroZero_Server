<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\UserVouchers;
use HeroZero\Db;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: {$message}\n"; }
function rejects(callable $fn, string $error): void {
    try { $fn(); } catch (HeroZero\GameError $e) { check($e->getMessage() === $error,$error); return; }
    throw new RuntimeException('Pedido devia falhar: '.$error);
}
try {
    check(UserVouchers::parseCodes(['codes'=>'["b","a"]']) === ['a','b'],'Array JSON do cliente, ordenado para locks');
    check(UserVouchers::parseCodes(['code'=>'a']) === ['a'],'Resgate individual');
    foreach (['[]','"a"','[1]','{"x":"a"}','invalid'] as $bad) rejects(fn()=>UserVouchers::parseCodes(['codes'=>$bad]),'errRedeemVoucherInvalidCode');
    rejects(fn()=>UserVouchers::parseCodes(['codes'=>'["a","a"]']),'errInvalidateAlreadyRedeemed');
    foreach (UserVouchers::KEYS as $key) UserVouchers::validateReward($key,20);
    foreach (UserVouchers::BOOSTERS as $key=>[$type]) {
        foreach (HeroZero\Live::BOOSTER_CATALOG as $entry) if ($entry[0] === $type) { UserVouchers::validateReward($key,['type'=>$entry[1],'duration'=>1]); break; }
    }
    check(UserVouchers::boosterId('quest_booster',50) === 'booster_quest3','Energético resolve catálogo');
    check(UserVouchers::boosterId('quest_booster',999) === null,'Tipo de energético desconhecido recusado');
    check(json_decode(HeroZero\Season::clientRewardJson('{"reward_type":8,"reward_factor":60}'),true) === ['quest_energy'=>60], 'Passe: tipo 8 dá 60 energias, conforme JS');
    if (($argv[1] ?? '') === '--offline') exit(0);
    $pdo = Db::pdo();
    $pdo->beginTransaction();
    try {
        $name = $argv[1] ?? null;
        $cid = (int)($name ? Db::value('SELECT id FROM `character` WHERE name = ? AND user_id > 0', [$name])
            : Db::value('SELECT id FROM `character` WHERE user_id > 0 ORDER BY id LIMIT 1'));
        if (!$cid) throw new RuntimeException('Necessário um herói.');
        Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE',[$cid]);
        Db::exec('UPDATE vouchers SET uses_current = 1 WHERE code LIKE ?', ['msg\_'.$cid.'\_%']);
        Db::exec('UPDATE `character` SET level = 100, guild_id = 0, max_quest_energy = 100,
            quest_energy = 100, training_count = 10 WHERE id = ?',[$cid]);
        $today = HeroZero\BonusEvents::today();
        // Calendário isolado dentro do rollback, sem alterar eventos persistentes.
        Db::exec('DELETE FROM bonus_event_days WHERE bonus_date = ?', [$today]);
        Db::exec('INSERT INTO daily_bonus_resources (character_id, quest_date, training_date, quest_factor, training_factor)
            VALUES (?, ?, ?, 1, 1) ON DUPLICATE KEY UPDATE quest_date = VALUES(quest_date),
            training_date = VALUES(training_date), quest_factor = 1, training_factor = 1', [$cid,$today,$today]);
        UserVouchers::create($cid,'quest_energy',20);
        UserVouchers::create($cid,'training_sessions',5);
        $rows = UserVouchers::data($cid);
        check(count($rows) === 2,'Dois prémios guardados nas mensagens');
        $wire = UserVouchers::attach(['character'=>[]],$cid);
        check(count(json_decode($wire['character']['new_user_voucher_ids'],true)) === 2,'Notificação usa JSON');
        UserVouchers::markRead($cid);
        $wire = UserVouchers::attach(['character'=>[]],$cid);
        check(json_decode($wire['character']['new_user_voucher_ids'],true) === [] && count($wire['user_vouchers']) === 2,'Ler não consome cupões');
        $codes = array_column($rows,'code');
        $total = UserVouchers::redeem($cid,$codes);
        check($total === ['quest_energy'=>20,'training_sessions'=>5],'Resgate conjunto entrega ambos');
        $char = Db::row('SELECT quest_energy, training_count FROM `character` WHERE id = ?',[$cid]);
        check((int)$char['quest_energy'] === 120 && (int)$char['training_count'] === 15,'Prémio acima do máximo sem truncar; treino são unidades');
        for ($i = 0; $i < 2; $i++) {
            $model = HeroZero\Character::load($cid);
            $model->syncDailyReset();
            $model->syncTrainingForLevel();
            check((int)$model->field('quest_energy') === 120 && (int)$model->field('training_count') === 15,
                'Sincronização repetida preserva excedentes dos cupões');
        }
        check(UserVouchers::data($cid) === [],'Cupões resgatados desaparecem');
        rejects(fn()=>UserVouchers::redeem($cid,$codes),'errInvalidateAlreadyRedeemed');
        UserVouchers::create($cid,'quest_energy',5);
        $code = UserVouchers::data($cid)[0]['code'];
        rejects(fn()=>UserVouchers::redeem($cid,[$code,'missing-code']),'errRedeemVoucherInvalidCode');
        check(count(UserVouchers::data($cid)) === 1 && (int)Db::value('SELECT quest_energy FROM `character` WHERE id = ?',[$cid]) === 120,'Falha no lote não consome prémio');
        $other = (int)Db::value('SELECT id FROM `character` WHERE id <> ? LIMIT 1',[$cid]);
        if ($other) rejects(fn()=>UserVouchers::redeem($other,[$code]),'errCheckVoucherRequirementsWrongUser');
        $h = Db::row('SELECT * FROM hideout WHERE character_id = ? FOR UPDATE',[$cid]);
        if ($h) {
            Db::exec('UPDATE hideout SET current_resource_glue = max_resource_glue WHERE character_id = ?',[$cid]);
            UserVouchers::create($cid,'hideout_glue',1);
            $all = array_column(UserVouchers::data($cid),'code');
            rejects(fn()=>UserVouchers::redeem($cid,$all),'errRedeemVoucherGlueFull');
            check(count(UserVouchers::data($cid)) === 2,'Depósito cheio preserva todos os cupões');
        }
        // Verifica geração pelas duas fontes reais, sem marcar prémios de contas reais.
        $before = count(UserVouchers::data($cid));
        $method = new ReflectionMethod(HeroZero\Herobook::class,'deliver');
        $method->invoke(null,$cid,['quest_energy'=>7,'training_sessions'=>2]);
        check(count(UserVouchers::data($cid)) === $before+2,'Chamadas geram cupões de energia e treino');
        $method = new ReflectionMethod(HeroZero\Season::class,'deliverReward');
        foreach ([6,8] as $type) $method->invoke(null,$cid,['reward_type'=>$type,'reward_factor'=>3,'reward_identifier'=>'']);
        check(count(UserVouchers::data($cid)) === $before+4,'Passe gera cupões de energia e treino');
        $latest = array_slice(UserVouchers::data($cid),-2);
        check(json_decode($latest[0]['rewards'],true) === ['training_sessions'=>3]
            && json_decode($latest[1]['rewards'],true) === ['quest_energy'=>3], 'Passe: treino tipo 6 e energia tipo 8 com quantidade direta');
        // Reproduz os saldos do HAR e atravessa o handler/estado completo real.
        Illuminate\Support\Facades\DB::connection()->setPdo($pdo);
        Illuminate\Support\Facades\DB::connection()->setReadPdo($pdo);
        Db::exec('UPDATE `character` SET max_quest_energy = 120, quest_energy = 117, training_count = 22 WHERE id = ?', [$cid]);
        $uid = (int)Db::value('SELECT user_id FROM `character` WHERE id = ?', [$cid]);
        $handler = require dirname(__DIR__,2).'/server-laravel/actions/vouchers.php';
        foreach (['quest_energy'=>50, 'training_sessions'=>4] as $key=>$amount) {
            UserVouchers::create($cid,$key,$amount);
            $pending = UserVouchers::data($cid);
            $code = $pending[count($pending)-1]['code'];
            $response = $handler(['action'=>'redeemVoucher','user_id'=>$uid,'code'=>$code]);
            check($response['voucher_rewards']->$key === $amount, 'Handler devolve prémio '.$key);
        }
        check((int)$response['character']['quest_energy'] === 167 && (int)$response['character']['training_count'] === 26,
            'Estado completo preserva 117+50 energias e 22+4 unidades do caso HAR');
        $state = HeroZero\Live::accountState($uid);
        check((int)$state['character']['quest_energy'] === 167 && (int)$state['character']['training_count'] === 26,
            'Estado seguinte mantém os excedentes do caso HAR');
    } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    echo "Integração concluída; alterações revertidas.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
