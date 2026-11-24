<?php
declare(strict_types=1);
// Teste MySQL com rollback; nome do herói opcional, sem preparação persistente.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db,Sidekick,Live};
try {
    $name=$argv[1] ?? 'laliene';
    $hero=Db::row('SELECT id,user_id FROM `character` WHERE name=?',[$name]);
    if (!$hero) throw new RuntimeException('Herói não encontrado.');
    $pdo=Db::pdo();$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $pdo->beginTransaction();
    try {
        $fixed=Sidekick::official()['fixed_skills'];
        foreach($fixed as $identifier=>$expected) {
            $created=Sidekick::create((int)$hero['id'],$identifier,3,[]);
            $skills=Db::row('SELECT stage1_skill_id,stage2_skill_id,stage3_skill_id FROM sidekicks WHERE id=?',[$created]);
            foreach($expected as $stage=>$skill) if((int)$skills['stage'.$stage.'_skill_id']!==(int)$skill)
                throw new RuntimeException('Habilidade especial em falta: '.$identifier);
        }
        $identifier=array_key_first($fixed);
        $explicit=Sidekick::create((int)$hero['id'],$identifier,3,[],1,0,[1=>1,2=>6,3=>15]);
        $skills=Db::row('SELECT stage1_skill_id,stage2_skill_id,stage3_skill_id FROM sidekicks WHERE id=?',[$explicit]);
        foreach($fixed[$identifier] as $stage=>$skill) if((int)$skills['stage'.$stage.'_skill_id']!==(int)$skill)
            throw new RuntimeException('Chamador substituiu habilidade especial.');
        $generic=array_values(array_diff(array_keys(Sidekick::official()['data']),array_keys($fixed)))[0];
        $created=Sidekick::create((int)$hero['id'],$generic,1,[]);
        $skills=Db::row('SELECT stage1_skill_id,stage2_skill_id,stage3_skill_id FROM sidekicks WHERE id=?',[$created]);
        foreach(Sidekick::SKILL_POOLS as $stage=>$pool) if(!in_array((int)$skills['stage'.$stage.'_skill_id'],$pool,true))
            throw new RuntimeException('Pet genérico fora do pool de habilidades.');
        echo 'PASS: '.count($fixed)." pets com habilidades fixas, chamada explícita e pet genérico; rollback.\n";
        $long='sidekick_season_event_arctic_exploration';
        $id=Sidekick::create((int)$hero['id'],$long,3,[]);
        Db::exec('UPDATE sidekicks SET sewed_identifier=? WHERE id=?',[$long,$id]);
        $row=Db::row('SELECT identifier,sewed_identifier FROM sidekicks WHERE id=?',[$id]);
        if ($row!==['identifier'=>$long,'sewed_identifier'=>$long]) throw new RuntimeException('Identificador truncado.');
        $state=Live::accountState((int)$hero['user_id']);
        $found=array_values(array_filter($state['sidekicks'],static fn($r)=>(int)$r['id']===$id));
        if (count($found)!==1 || $found[0]['identifier']!==$long || $found[0]['sewed_identifier']!==$long) throw new RuntimeException('Estado não conserva identificadores.');
        echo "PASS: criação real e estado da conta preservam identificador e aparência longos; rollback.\n";
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
