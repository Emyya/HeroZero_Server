<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{QuestNpcs,Character,Db};
function npcCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$pdo=null;$failed=false;
try {
    $root=dirname(__DIR__,2);$catalog=json_decode(file_get_contents($root.'/server-laravel/data/reference/quest_templates.json'),true);
    $constants=json_decode(gzuncompress(file_get_contents($root.'/assets/data/constants_json.data')),true);
    $texts=json_decode(gzuncompress(file_get_contents($root.'/server-laravel/public/assets/i18n/pt_BR/text.data')),true);
    foreach($catalog['quests'] as $stage)foreach($stage['fight'] as $mission)npcCheck(isset(QuestNpcs::catalog()[$mission]),'Missão sem mapa: '.$mission);
    foreach(QuestNpcs::catalog() as $mission=>$npc) {
        npcCheck(isset($constants['quest_templates'][$mission],$constants['npcs'][$npc],$texts['npc/'.$mission.'/name']),'Identificador/texto inválido: '.$mission);
        foreach(['','_t2'] as $suffix)npcCheck(is_file($root.'/server-laravel/public/assets/npc/'.$npc.$suffix.'.webp'),'Asset em falta: '.$npc.$suffix);
    }
    npcCheck(QuestNpcs::identifier('quest_stage3_fight14')==='npc_shady_balloon_seller','Captura 1: vendedor de balões');
    npcCheck(QuestNpcs::identifier('quest_stage3_fight13')==='npc_overzealous_policewoman','Captura 2: policial');
    npcCheck(QuestNpcs::identifier('quest_unknown','npc_thief')==='npc_thief','Desconhecida conserva fallback explícito');
    echo 'PASS offline: 195 missões normais, '.count(QuestNpcs::catalog())." associações válidas, textos e assets; casos das duas capturas.\n";
    if(!in_array('--mysql',$argv,true))exit(0);
    $app=require $root.'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $name='skel';foreach($argv as $arg)if(str_starts_with($arg,'--hero='))$name=substr($arg,7);
    $char=Db::row('SELECT id FROM `character` WHERE name=?',[$name]);npcCheck($char!==null,'Herói não encontrado');$cid=(int)$char['id'];
    echo "Preparação temporária de missões de $name; tudo revertido. Não jogar com este herói durante o teste.\n";
    $pdo=Db::pdo();$pdo->beginTransaction();Db::row('SELECT id FROM `character` WHERE id=? FOR UPDATE',[$cid]);
    $q=Db::row('SELECT * FROM quests WHERE character_id=? AND type=2 AND status=1 LIMIT 1',[$cid]);npcCheck($q!==null,'É necessária uma oferta de combate');
    Db::exec('UPDATE quests SET identifier=?,fight_npc_identifier=? WHERE id=?',['quest_stage3_fight14','npc_olympia_hippies',(int)$q['id']]);
    $before=Db::row('SELECT * FROM quests WHERE id=?',[(int)$q['id']]);$repaired=QuestNpcs::repairOffer($cid,$before);
    npcCheck($repaired['fight_npc_identifier']==='npc_shady_balloon_seller','Oferta antiga reparada');
    foreach($before as $key=>$value)if($key!=='fight_npc_identifier')npcCheck($repaired[$key]===$value,'Reparação mudou '.$key);
    npcCheck(QuestNpcs::repairOffer($cid,$repaired)===$repaired,'Reparação idempotente');
    foreach([2,4] as $status) {
        Db::exec('UPDATE quests SET status=?,fight_npc_identifier=? WHERE id=?',[$status,'npc_thief',(int)$q['id']]);
        $running=Db::row('SELECT * FROM quests WHERE id=?',[(int)$q['id']]);npcCheck(QuestNpcs::repairOffer($cid,$running)===$running,'Missão iniciada/concluída preservada');
    }
    Db::exec('DELETE FROM quests WHERE character_id=? AND stage=3',[$cid]);
    Character::load($cid)->ensureQuestPool(3);
    foreach(Db::rows('SELECT * FROM quests WHERE character_id=? AND stage=3',[$cid]) as $generated)if((int)$generated['type']===2)npcCheck($generated['fight_npc_identifier']===QuestNpcs::identifier($generated['identifier']),'Gerador real usa mapa');
    echo "PASS MySQL: reparação apenas de ofertas, restantes campos preservados, idempotência e geração real.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");$failed=true;} finally {if($pdo&&$pdo->inTransaction()){$pdo->rollBack();echo "ROLLBACK: missões originais preservadas.\n";}}

if($failed)exit(1);
