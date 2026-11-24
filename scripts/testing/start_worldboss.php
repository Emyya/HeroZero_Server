<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\Worldboss;
use HeroZero\Db;
try {
    $args = array_slice($argv,1);
    $script = 'scripts/testing/'.basename($argv[0]);
    $category = $worldbossCategory ?? 'all';
    $aliases = $worldbossAliases ?? [];
    if (!$args || $args[0] === '--help') {
        echo "Uso: php $script --list\n"
            ."     php $script TEMPLATE [--stage=1] [--hp=100000] [--minutes=60] [--min-level=1] [--max-level=999] [--dry-run]\n"
            ."Cria um evento global persistente, sem alterar heróis ou iniciar ataques. Recarregar o jogo após iniciar.\n";
        exit(0);
    }
    $catalog = Worldboss::constants()['worldboss_event_templates'];
    $catalog = array_filter($catalog, static function ($id) use ($category): bool {
        $kind = str_starts_with($id, 'default_event_stage') ? 'normal'
            : (str_ends_with($id, '_season_worldboss') ? 'season' : 'event');
        return $category === 'all' || $category === $kind;
    }, ARRAY_FILTER_USE_KEY);
    if ($args[0] === '--list') {
        foreach ($catalog as $id=>$d) {
            $names = array_keys($aliases, $id, true);
            echo ($names ? implode('/', $names).' => ' : '').$id.' => '.$d['npc_identifier']."\n";
        }
        exit(0);
    }
    $requested = array_shift($args);
    $id = $aliases[$requested] ?? $requested;
    if (!isset($catalog[$id])) throw new InvalidArgumentException('Template desconhecido ou fora da categoria deste script. Consultar --list.');
    preg_match('/stage([0-9]+)/', $id, $match);
    $o = ['stage'=>(int)($match[1] ?? 1), 'hp'=>100000, 'minutes'=>60, 'min-level'=>1, 'max-level'=>999]; $dry=false;
    foreach ($args as $arg) {
        if ($arg === '--dry-run') { $dry=true; continue; }
        if (!preg_match('/^--(stage|hp|minutes|min-level|max-level)=([0-9]+)$/D', $arg, $m)) throw new InvalidArgumentException('Opção inválida: '.$arg);
        $o[$m[1]]=(int)$m[2];
    }
    if ($o['stage']<1 || $o['stage']>14 || $o['hp']<1 || $o['hp']>2000000000 || $o['minutes']<1 || $o['minutes']>10080
        || $o['min-level']<1 || $o['max-level']<$o['min-level']) throw new InvalidArgumentException('Parâmetros fora dos limites.');
    echo "Vilão $id ({$catalog[$id]['npc_identifier']}), zona {$o['stage']}, vida {$o['hp']}, {$o['minutes']} minutos, níveis {$o['min-level']}–{$o['max-level']}.\n";
    echo 'Prémios: '.json_encode(Worldboss::defaultRewards($id),JSON_UNESCAPED_UNICODE).".\n";
    if ($dry) { echo "Simulação: não foram gravados dados.\n"; exit(0); }
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo(); $pdo->beginTransaction();
    if (Db::row('SELECT id FROM worldboss_event WHERE stage = ? AND status = 1 AND ts_end > ? AND npc_hitpoints_current > 0 FOR UPDATE', [$o['stage'],HeroZero\GameTime::now()]))
        throw new RuntimeException('Já existe um vilão ativo nesta zona. Esperar pelo fim; nenhum evento foi substituído.');
    $eid=Worldboss::create($id,$o['stage'],$o['hp'],$o['minutes']*60,$o['min-level'],$o['max-level']);
    $pdo->commit();
    echo "Evento $eid iniciado. Tempo medido pelo relógio GameTime.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e->getMessage()."\n"); exit(1);
}
