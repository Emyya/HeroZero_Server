<?php
declare(strict_types=1);
// Sem --apply: diagnóstico. --apply: backup, ampliação do schema e reparação comprovada.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db,Sidekick,Missiles};
try {
    $pdo=Db::pdo();
    if ($pdo->inTransaction()) throw new RuntimeException('Executar fora de transação de jogo.');
    $known=array_keys(Sidekick::official()['data']);
    $rows=Db::rows('SELECT * FROM sidekicks ORDER BY id');
    $history=[];
    foreach(Db::column('SELECT message FROM casino_wins ORDER BY id') as $json) {
        $m=json_decode($json,true);
        if ((int)($m['type'] ?? 0)!==402) continue;
        if (preg_match('/\(sidekick:([^;]+);/',(string)($m['value1'] ?? ''),$match))
            $history[(int)$m['character_id']][]=$match[1];
    }
    $plan=[];$unresolved=[];
    foreach($rows as $r) foreach(['identifier','sewed_identifier'] as $field) {
        $old=(string)($r[$field] ?? '');
        if (strlen($old)!==32 || in_array($old,$known,true)) continue;
        $candidates=array_values(array_filter($known,static fn($id)=>str_starts_with($id,$old)));
        if (!$candidates) { $unresolved[]=[(int)$r['id'],$field,$old]; continue; }
        $new=count($candidates)===1 ? $candidates[0] : null;
        if ($new===null && $field==='identifier') {
            $matches=array_values(array_filter($history[(int)$r['character_id']] ?? [],static fn($id)=>in_array($id,$candidates,true)));
            $affected=count(array_filter($rows,static fn($row)=>(int)$row['character_id']===(int)$r['character_id'] && $row['identifier']===$old));
            if (count($matches)===$affected && count(array_unique($matches))===1) $new=$matches[0];
        }
        if ($new===null) { $unresolved[]=[(int)$r['id'],$field,$old]; continue; }
        $plan[]=[(int)$r['id'],(int)$r['character_id'],$field,$old,$new];
        echo "Pet #{$r['id']}: $field $old -> $new\n";
    }
    foreach($unresolved as [$id,$field,$old]) echo "PENDENTE: pet #$id, $field $old (sem prova única).\n";
    if (!in_array('--apply',$argv,true)) {echo 'Diagnóstico: '.count($plan)." reparações; usar --apply para guardar backup e aplicar.\n";exit;}
    $cols=Db::rows("SELECT COLUMN_NAME,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sidekicks' AND COLUMN_NAME IN ('identifier','sewed_identifier')");
    $short=array_filter($cols,static fn($r)=>(int)$r['CHARACTER_MAXIMUM_LENGTH']<128);
    $missing=array_diff(['identifier','sewed_identifier'],array_column($cols,'COLUMN_NAME'));
    if (!$short && !$missing && !$plan) {echo "Schema e identificadores já atualizados.\n";exit;}
    $dir=dirname(__DIR__,2).'/server-laravel/storage/backups';
    if (!is_dir($dir) && !mkdir($dir,0770,true)) throw new RuntimeException('Não foi possível criar pasta de backup.');
    $backup=$dir.'/sidekick_identifiers_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3)).'.json';
    if (file_put_contents($backup,json_encode(['columns'=>$cols,'sidekicks'=>$rows,'repair_plan'=>$plan],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Falha ao guardar backup.');
    echo "Backup: $backup\n";
    // DDL fora de transações: MySQL faz commit implícito.
    foreach($missing as $field) $pdo->exec("ALTER TABLE sidekicks ADD `$field` varchar(128) NOT NULL DEFAULT ''");
    foreach($short as $col) {
        $field=$col['COLUMN_NAME'];
        $pdo->exec("ALTER TABLE sidekicks MODIFY `$field` varchar(128) NOT NULL".($field==='sewed_identifier'?" DEFAULT ''":''));
    }
    Missiles::transaction(array_values(array_unique(array_column($plan,1))),static function()use($plan):void {
        foreach($plan as [$id,$cid,$field,$old,$new]) {
            if (Db::exec("UPDATE sidekicks SET `$field`=? WHERE id=? AND character_id=? AND `$field`=?",[$new,$id,$cid,$old])!==1)
                throw new RuntimeException("Pet #$id mudou durante a reparação; transação revertida.");
        }
    });
    echo count($plan)." identificadores reparados; saldos, nomes personalizados e habilidades preservados.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
