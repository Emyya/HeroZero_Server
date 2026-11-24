<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Sidekick,Db,Router};
function verify(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "OK: $message\n";
}
try {
    $root=dirname(__DIR__,2);
    $data=json_decode(gzuncompress(file_get_contents($root.'/assets/data/constants_json.data')),true,512,JSON_THROW_ON_ERROR);
    $sources=Sidekick::official()['sources'];
    $texts=json_decode(gzuncompress(file_get_contents($root.'/server-laravel/public/assets/i18n/pt_BR/text.data')),true,512,JSON_THROW_ON_ERROR);
    $goalPets=[];
    foreach ($data['goals'] as $id=>$goal) foreach ($goal['values'] as $target=>$reward) {
        $pet=$reward['reward_identifier'] ?? '';
        if (!str_starts_with($pet,'sidekick_')) continue;
        $goalPets[$pet][]='goal_'.$id.'_'.$target;
    }
    foreach ($goalPets as $pet=>$expected)
        verify($sources[$pet]===array_values(array_unique($expected)), 'Conquista correta sem brechó/indisponível: '.$pet);
    verify($sources['sidekick_peacock']===['goal_different_sidekick_collected_140']
        && str_contains($texts['sidekick_source/'.$sources['sidekick_peacock'][0]],'140'), 'Kylie apresenta conquista de 140 pets');
    $references=0;
    $scan=static function ($value,string $source) use (&$scan,$sources,&$references,$data): void {
        if (is_array($value)) foreach($value as $child)$scan($child,$source);
        elseif(is_string($value)) {
            preg_match_all('/sidekick_[A-Za-z0-9_]+/',$value,$matches);
            foreach($matches[0] as $pet) {
                if(!isset($data['item_templates'][$pet]))continue;
                verify(in_array($source,$sources[$pet] ?? [],true),'Fonte derivada: '.$pet.' / '.$source);
                $references++;
            }
        }
    };
    foreach (['event_quests'=>'event_','event_draw_pools'=>'event_draw_','event_treasures'=>'event_treasure_',
        'hidden_object_event_quests'=>'hidden_object_event_','tower_event_objectives'=>'tower_event_',
        'legendary_dungeons'=>'legendary_dungeon_'] as $section=>$prefix)
        foreach($data[$section] as $id=>$definition)$scan($definition,$prefix.$id);
    foreach($data['seasons'] as $id=>$season)foreach($season['season_points'] as $reward)
        foreach(['premium_reward_identifier'=>'season_premium_','reward_identifier'=>'season_free_'] as $field=>$prefix) {
            $pet=$reward[$field] ?? '';
            if(str_starts_with($pet,'sidekick_'))verify(in_array($prefix.$id,$sources[$pet],true),'Fonte do passe: '.$pet);
        }
    foreach($sources as $pet=>$rows)foreach($rows as $source)
        if(!isset($texts['sidekick_source/'.$source]))throw new RuntimeException('Tradução em falta: '.$source);
    verify(true,'Todas as fontes têm texto pt_BR; '.$references.' referências de eventos verificadas');
    if(in_array('--offline',$argv,true)){echo "PASS: fontes offline.\n";exit(0);}
    unset($scan,$data,$texts,$goalPets);
    $app=require $root.'/server-laravel/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $hero='skel';foreach(array_slice($argv,1) as $arg) {
        if(str_starts_with($arg,'--hero='))$hero=substr($arg,7);
        else throw new InvalidArgumentException('Uso: test_sidekick_sources.php --offline | --hero=Nome');
    }
    $char=Db::row('SELECT id,user_id FROM `character` WHERE name=?',[$hero]);
    if(!$char)throw new RuntimeException('Herói não encontrado');
    $pdo=Db::pdo();$pdo->beginTransaction();
    try {
        $response=Router::dispatch('getSidekicks',['action'=>'getSidekicks','user_id'=>(int)$char['user_id']]);
        $seen=[];
        foreach($response['all_sidekicks'] as $pet) {
            if($pet['sources']!==($sources[$pet['identifier']] ?? []))throw new RuntimeException('Catálogo incoerente: '.$pet['identifier']);
            $seen[$pet['identifier']]=true;
        }
        verify(count($seen)===count(Sidekick::official()['data']) && isset($seen['sidekick_peacock']), 'Handler getSidekicks envia as fontes corrigidas de todo o catálogo');
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
    echo "PASS: handler MySQL com rollback.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
