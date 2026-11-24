<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{EventQuests,Db,GameTime,GameError,Character,Live,Router,Season};
function eventCheck(bool $ok,string $text):void {if(!$ok)throw new RuntimeException($text);echo "OK: $text\n";}
function eventReject(callable $fn,string $text):void {try{$fn();}catch(GameError|InvalidArgumentException $e){echo "OK: rejeita $text\n";return;}throw new RuntimeException('Aceitou '.$text);}
try {
    $ref=EventQuests::reference();$texts=json_decode(gzuncompress(file_get_contents(dirname(__DIR__,2).'/server-laravel/public/assets/i18n/pt_BR/text.data')),true,512,JSON_THROW_ON_ERROR);
    eventCheck(count($ref['event_quests'])===35,'35 templates disponíveis');
    foreach($ref['event_quests'] as $key=>$d) {
        eventCheck(isset($texts['event_quest/'.$key.'/name']),'nome traduzido: '.$key);
        $indexes=[];
        foreach($d['objectives'] as $oid=>$o) {
            if(!in_array($o['type'],[1,2,4,5,6,7,8,9,10,11],true)||$o['value']<1||isset($indexes[$o['index']]))throw new RuntimeException('Objetivo inválido: '.$oid);
            $indexes[$o['index']]=true;
            if(!isset($texts['event_quest/objective/'.$oid.'/name']))throw new RuntimeException('Tradução em falta: '.$oid);
            if($o['type']===7&&!isset($ref['event_items'][$o['reference']]))throw new RuntimeException('Objeto inválido: '.$oid);
            if($o['reward_type']===5&&!isset($ref['item_templates'][$o['reward_reference']]))throw new RuntimeException('Item inválido: '.$oid);
            if($o['reward_type']===6&&!isset($ref['optical_changes'][(int)$o['reward_reference']]))throw new RuntimeException('Aparência inválida: '.$oid);
        }
    }
    eventCheck(EventQuests::matches('*_halloween_*','quest_halloween_time1')&&!EventQuests::matches('*_halloween_*','quest_stage1_time1'),'matching de referências temáticas');
    eventCheck(EventQuests::skipCost(0,25)===29&&EventQuests::skipCost(24,25)===2&&EventQuests::skipCost(25,25)===0,'preço premium arredondado como o cliente');
    eventCheck(EventQuests::amount(3,0.5,100)===3&&EventQuests::amount(3,0.4,100)===0,'Math.round do fator, incluindo fronteira 0.5');
    eventCheck(EventQuests::amount(2,5,100)===5,'quantidade de donuts');
    eventCheck(EventQuests::amount(1,0.5,100)===7058,'fórmula de moedas no nível 100');
    if(!in_array('--db',$argv,true)){echo "PASS: offline. Integração: --db --hero=Nome (rollback).\n";exit;}
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=Db::pdo();$connection=Illuminate\Support\Facades\DB::connection();$connection->setPdo($pdo);$connection->setReadPdo($pdo);
    $name='';foreach($argv as $a)if(str_starts_with($a,'--hero='))$name=substr($a,7);
    $heroes=$name!==''?Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]):Db::rows('SELECT c.* FROM `character` c JOIN `user` u ON u.id=c.user_id ORDER BY c.id LIMIT 1');
    if(count($heroes)!==1)throw new RuntimeException('Herói não encontrado ou ambíguo.');
    $hero=$heroes[0];$cid=(int)$hero['id'];$uid=(int)$hero['user_id'];
    echo "Integração no herói {$hero['name']}; saldos, inventário, progressos e calendário serão revertidos.\n";
    $pdo->beginTransaction();
    try {
        Db::exec('UPDATE event_quest_occurrences SET enabled=0');Db::exec('UPDATE event_quest_progress SET claimed=1 WHERE character_id=?',[$cid]);
        Db::exec('UPDATE `character` SET level=100,event_quest_id=0 WHERE id=?',[$cid]);
        Db::exec('UPDATE `user` SET premium_currency=10000 WHERE id=?',[$uid]);
        $now=GameTime::now();
        foreach($ref['event_quests'] as $key=>$d) {
            $pdo->exec('SAVEPOINT event_case');
            try {
                $eid=EventQuests::schedule($key,$now-1,$now+86400);
                eventReject(fn()=>EventQuests::schedule($key,$now,$now+3600),'sobreposição do calendário');
                $invite=EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest'];
                eventCheck(!isset($invite['id'])&&$invite['identifier']===$key,'convite sem id: '.$key);
                EventQuests::assign($cid,$key);eventReject(fn()=>EventQuests::assign($cid,$key),'segunda aceitação');
                $data=EventQuests::attach(['character'=>['id'=>$cid]],$cid);$e=$data['event_quest'];
                eventCheck($e['id']===$data['character']['event_quest_id']&&is_int($e['status'])&&is_string($e['rewards']),'shape atribuída: '.$key);
                $itemIds=array_column($data['items'],'id');
                foreach($d['objectives'] as $oid=>$o) {
                    $field='objective'.$o['index'].'_value';
                    $itemId=$e['objective'.$o['index'].'_reward_item_id'];
                    if($itemId>0)eventCheck(in_array($itemId,$itemIds,true),'preview de objetivo enviado');
                    eventReject(fn()=>EventQuests::claimObjective($cid,$oid),'resgate antes da meta');
                    $reference=$o['reference'];if(str_contains($reference,'*'))$reference=str_replace('*','test',$reference);
                    EventQuests::record($cid,(int)$o['type'],(int)$o['value']+100,$reference);
                    $before=Db::row('SELECT payload FROM event_quest_progress WHERE id=?',[$e['id']]);$p=json_decode($before['payload'],true);
                    eventCheck($p['values'][$o['index']]===$o['value'],'progresso limitado à meta: '.$oid);
                    EventQuests::claimObjective($cid,$oid,true);
                    eventReject(fn()=>EventQuests::claimObjective($cid,$oid),'resgate repetido');
                    EventQuests::record($cid,(int)$o['type'],99999,$reference);
                    $p=json_decode(Db::value('SELECT payload FROM event_quest_progress WHERE id=?',[$e['id']]),true);
                    eventCheck($p['values'][$o['index']]===$o['value']+1,'contador resgatado preservado');
                }
                $e=EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest'];
                eventCheck($e['status']===2,'todos os objetivos resgatados: '.$key);
                eventReject(fn()=>EventQuests::claimFinal($cid,PHP_INT_MAX),'escolha alheia');
                $before=(int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid]);
                EventQuests::claimFinal($cid,(int)$e['reward_item3_id'],false);
                eventCheck((int)Db::value('SELECT event_quest_id FROM `character` WHERE id=?',[$cid])===0,'evento fechado: '.$key);
                eventReject(fn()=>EventQuests::claimFinal($cid,(int)$e['reward_item3_id']),'prémio final repetido');
                eventCheck(!isset(EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest']),'mesma ocorrência não volta a convidar');
                eventCheck((int)Db::value('SELECT stat_points_available FROM `character` WHERE id=?',[$cid])>$before,'prémio final numérico creditado');
            } finally {$pdo->exec('ROLLBACK TO SAVEPOINT event_case');$pdo->exec('RELEASE SAVEPOINT event_case');}
        }
        $pdo->exec('SAVEPOINT eligibility_case');
        try {
            EventQuests::schedule('halloween',$now+86400,$now+172800);
            eventCheck(EventQuests::current()===null,'evento futuro não abre antes da janela');
            eventReject(fn()=>EventQuests::assign($cid,'halloween'),'aceitação antes do início');
        } finally {$pdo->exec('ROLLBACK TO SAVEPOINT eligibility_case');$pdo->exec('RELEASE SAVEPOINT eligibility_case');}
        $pdo->exec('SAVEPOINT level_case');
        try {
            EventQuests::schedule('halloween',$now-1,$now+86400);
            Db::exec('UPDATE `character` SET level=4 WHERE id=?',[$cid]);
            eventReject(fn()=>EventQuests::assign($cid,'halloween'),'nível abaixo de cinco');
            eventCheck(!isset(EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest']),'nível insuficiente não recebe convite');
            Db::exec('UPDATE `character` SET level=100 WHERE id=?',[$cid]);
            eventReject(fn()=>EventQuests::assign($cid,'easter'),'template diferente do ativo');
        } finally {$pdo->exec('ROLLBACK TO SAVEPOINT level_case');$pdo->exec('RELEASE SAVEPOINT level_case');}
        // Premium: débito, falta de saldo, repetição e rollback exterior.
        $eid=EventQuests::schedule('halloween',$now-1,$now+86400);EventQuests::assign($cid,'halloween');
        $oid='halloween_quest_completed';$before=(int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid]);
        EventQuests::claimObjective($cid,$oid,false,true);
        eventCheck((int)Db::value('SELECT premium_currency FROM `user` WHERE id=?',[$uid])===$before-29,'conclusão instantânea debita 29');
        eventReject(fn()=>EventQuests::claimObjective($cid,$oid,false,true),'conclusão instantânea repetida');
        Db::exec('UPDATE `user` SET premium_currency=0 WHERE id=?',[$uid]);
        eventReject(fn()=>EventQuests::claimObjective($cid,'halloween_eventitem_pumpkin_collected',false,true),'saldo insuficiente');
        $p=json_decode(Db::value('SELECT payload FROM event_quest_progress WHERE character_id=? AND claimed=0',[$cid]),true);
        eventCheck($p['values'][2]===0,'falha premium preserva progresso');
        EventQuests::cancel($eid);$e=EventQuests::attach(['character'=>['id'=>$cid]],$cid)['event_quest'];
        eventCheck($e['status']===3,'cancelamento expira evento incompleto');
        EventQuests::claimObjective($cid,$oid,true);
        eventReject(fn()=>EventQuests::claimObjective($cid,'halloween_eventitem_pumpkin_collected',false,true),'premium após cancelamento');
        EventQuests::claimFinal($cid,0);
        eventCheck($pdo->inTransaction(),'savepoints preservam transação exterior');
        echo "PASS: 35 eventos e resgates MySQL com rollback.\n";
    } finally {if($pdo->inTransaction())$pdo->rollBack();}
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
