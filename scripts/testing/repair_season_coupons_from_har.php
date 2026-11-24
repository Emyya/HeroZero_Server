<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
require __DIR__.'/season_coupon_har.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$name=$argv[1] ?? '';
$path=$argv[2] ?? dirname(__DIR__,2).'/resgate.har';
if ($name === '') { fwrite(STDERR,"Uso: php scripts/testing/repair_season_coupons_from_har.php \"Herói\" [HAR]\n"); exit(1); }
try {
    $report=seasonCouponHar($path);
    [$created,$changed,$skipped]=HeroZero\UserVouchers::transaction(static function () use ($name,$report): array {
        $rows=HeroZero\Db::rows('SELECT id, user_id FROM `character` WHERE name = ? FOR UPDATE',[$name]);
        if (count($rows) !== 1 || (int)$rows[0]['id'] !== $report['character_id']) throw new RuntimeException('O herói não corresponde ao HAR.');
        $cid=(int)$rows[0]['id']; $uid=(int)$rows[0]['user_id'];
        $progress=HeroZero\Db::row('SELECT id, claimed_rewards FROM season_progress WHERE character_id = ? FOR UPDATE',[$cid]);
        $claimed=array_map('intval',json_decode($progress['claimed_rewards'] ?? '[]',true));
        $created=0; $changed=0; $skipped=0;
        foreach ($report['missing_energy'] as $r) {
            if (!in_array($r['reward_id'],$claimed,true)) throw new RuntimeException('O passe foi resetado: não aplicar este HAR ao progresso atual.');
            $code='msg_'.$cid.'_fix_energy_'.$progress['id'].'_'.$r['reward_id'];
            if (HeroZero\Db::row('SELECT id FROM vouchers WHERE code = ? FOR UPDATE',[$code])) { $skipped++; continue; }
            HeroZero\Db::exec('INSERT INTO vouchers (code,rewards,user_id,ts_creation,ts_start,uses_max,status) VALUES (?,?,?,?,0,1,1)',
                [$code,json_encode(['quest_energy'=>$r['amount']]),$uid,HeroZero\GameTime::now()]);
            $created++;
        }
        foreach ($report['wrong_resources'] as $r) {
            if (!in_array($r['reward_id'],$claimed,true)) throw new RuntimeException('O passe foi resetado: não aplicar este HAR ao progresso atual.');
            $v=HeroZero\Db::row('SELECT * FROM vouchers WHERE id = ? FOR UPDATE',[$r['voucher_id']]);
            if (!$v || (int)$v['user_id'] !== $uid || !str_starts_with($v['code'],'msg_'.$cid.'_')) throw new RuntimeException('Cupão não corresponde ao herói.');
            if ((int)$v['uses_current'] !== 0 || (int)$v['status'] !== 1 || $v['rewards'] === $r['new_rewards']) { $skipped++; continue; }
            if (json_decode($v['rewards'],true) !== json_decode($r['old_rewards'],true)) throw new RuntimeException('Cupão foi alterado após o HAR.');
            HeroZero\Db::exec('UPDATE vouchers SET rewards = ? WHERE id = ?',[$r['new_rewards'],$r['voucher_id']]);
            $changed++;
        }
        return [$created,$changed,$skipped];
    });
    echo "Recuperação: {$created} cupões de energia criados, {$changed} cupões corrigidos para zenit, {$skipped} já tratados/usados.\n";
    echo "Alterações guardadas. Recarrega o jogo. XP/atributos antigos não foram removidos; cupões já usados não foram alterados.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
