<?php
declare(strict_types=1);
// Aceitação persistente ou consulta. Não altera saldos nem força prémios.
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\{Db,EventDraws as D};
try {
    $name=$argv[1]??'';$mode=$argv[2]??'--show';
    if($name===''||!in_array($mode,['--show','--accept'],true)||count($argv)>3)throw new InvalidArgumentException('Uso: prepare_event_draw.php "Herói" [--show|--accept]. --show consulta. --accept aceita persistentemente o convite atual, sem sortear/entregar prémios nem alterar saldos.');
    $app=require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $rows=Db::rows('SELECT c.id FROM `character` c JOIN `user` u ON u.id=c.user_id WHERE c.name=?',[$name]);if(count($rows)!==1)throw new InvalidArgumentException('Herói inexistente/ambíguo.');$cid=(int)$rows[0]['id'];
    if($mode==='--accept'){D::assign($cid,(D::current()??throw new RuntimeException('Sem Pirâmide da Sorte ativa.'))['identifier']);echo "Convite aceite; nenhum sorteio/prémio/saldo alterado. Recarrega o jogo.\n";}
    $p=D::state($cid);if(!$p['id']){echo 'Sem participação pendente; convite global: '.(D::current()['identifier']??'nenhum')."\n";exit;}
    echo "Herói: $name; {$p['identifier']}; estado {$p['status']}; ".count($p['drawn']).' prémios sorteados, '.count($p['claimed']).' recolhidos; próximo custo: '.D::cost($p['identifier'],$p['drawn'])." donuts.\n";
    foreach($p['drawn'] as $i=>$ts)echo 'Índice '.$i.': '.(isset($p['claimed'][$i])?'recolhido':'por recolher')."\n";
    echo "O custo só é aplicável enquanto houver sorteios disponíveis e não houver prémio por recolher. --show não normaliza expiração.\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
