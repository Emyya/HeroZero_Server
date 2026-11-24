<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require __DIR__.'/season_coupon_har.php';
try {
    $js=file_get_contents(dirname(__DIR__,2).'/server-laravel/public/js/HeroZero.min.js');
    $start=strpos($js,'et.prototype.refreshWithoutReward=function');
    $end=strpos($js,'et.prototype.handleAvatarPreviewShow=', $start);
    $method=substr($js,$start,$end-$start);
    foreach ([4=>[2,'xp'],6=>[4,'training_sessions'],8=>[3,'quest_energy'],11=>[12,'hideout_glue'],12=>[13,'hideout_stone']] as $type=>[$generic,$key]) {
        if (!preg_match('/case '.$type.':this\._rewardIcon\.set_rewardEntry\(new ea\('.$generic.',/',$method)) throw new RuntimeException('Mapeamento do JS mudou: tipo '.$type);
        $wire=json_decode(HeroZero\Season::clientRewardJson(json_encode(['reward_type'=>$type,'reward_factor'=>60])),true);
        if (!isset($wire[$key])) throw new RuntimeException('Tipo '.$type.' não produz '.$key);
        if (in_array($type,[6,8],true) && $wire[$key] !== 60) throw new RuntimeException('Quantidade direta incorreta.');
        echo "OK: tipo {$type} corresponde a {$key}, confirmado no JS\n";
    }
    $path=$argv[1] ?? dirname(__DIR__,2).'/resgate.har';
    if (is_file($path)) {
        $report=seasonCouponHar($path);
        echo 'HAR: '.count($report['missing_energy']).' resgates antigos sem cupão, total '.array_sum(array_column($report['missing_energy'],'amount'))." energias.\n";
        echo 'HAR: '.count($report['wrong_resources'])." cupões de zenit foram criados como energia.\n";
    }
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
