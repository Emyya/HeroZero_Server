<?php
declare(strict_types=1);
// Integração MySQL: preparação temporária e rollback integral.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__, 2) . '/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use HeroZero\{Db, GameError, GameTime, Hideout};
function checkCollection(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: $label\n";
}
try {
    $hero = Db::row('SELECT id,user_id FROM `character` WHERE name=?', [$argv[1] ?? 'skel']);
    if (!$hero) throw new RuntimeException('Herói não encontrado.');
    $cid = (int)$hero['id'];
    $room = Db::row("SELECT * FROM hideout_rooms WHERE character_id=? AND identifier='gem_production' LIMIT 1", [$cid]);
    if (!$room) throw new RuntimeException('Herói sem oficina.');
    $pdo = Db::pdo();
    $conn = Illuminate\Support\Facades\DB::connection();
    $conn->setPdo($pdo);
    $conn->setReadPdo($pdo);
    $collect = require dirname(__DIR__, 2) . '/server-laravel/actions/hideoutExtra.php';
    $params = ['user_id'=>(int)$hero['user_id'], 'action'=>'collectHideoutRoomActivityResult', 'hideout_room_id'=>(int)$room['id']];
    echo "Preparação temporária da oficina e mochila; todas as alterações serão revertidas.\n";
    $pdo->beginTransaction();
    try {
        foreach ([13, 14, 4] as $slot) {
            $data = ['improvement_slot'=>$slot];
            if ($slot === 4) $data['improvement_rarity'] = ['0'=>['id'=>0, 'identifier'=>'improvement_belt_common', 'quality'=>1, 'stat_stamina'=>50, 'stat_strength'=>0, 'stat_critical_rating'=>0, 'stat_dodge_rating'=>0]];
            Db::exec('UPDATE inventory SET bag_item1_id=0 WHERE character_id=?', [$cid]);
            Db::exec('UPDATE hideout_rooms SET status=?,ts_activity_end=?,max_resource_amount=?,additional_value_2=? WHERE id=?', [Hideout::STATUS_PRODUCING, GameTime::now()-1, $slot===4?0:$slot, json_encode($data), $room['id']]);
            $count = (int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?', [$cid]);
            $state = $collect($params);
            $id = (int)Db::value('SELECT bag_item1_id FROM inventory WHERE character_id=?', [$cid]);
            $item = Db::row('SELECT * FROM items WHERE id=? AND character_id=?', [$id, $cid]);
            checkCollection($item !== null && (int)$item['type'] === ($slot===4?12:$slot), "Destino $slot: item entregue");
            if ($slot===4) checkCollection((int)$item['stat_stamina']===50, 'Atributos da opção preservados');
            checkCollection(in_array($id, array_map('intval', array_column($state['items'], 'id')), true), 'Resposta inclui o item entregue');
            checkCollection((int)Db::value('SELECT status FROM hideout_rooms WHERE id=?', [$room['id']])===Hideout::STATUS_IDLE, 'Oficina disponível após recolha');
            try { $collect($params); throw new RuntimeException('Recolha duplicada aceite.'); }
            catch (GameError $e) { checkCollection($e->getMessage()==='errCollectActivityResultInvalidStatus', 'Recolha duplicada rejeitada'); }
            checkCollection((int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?', [$cid])===$count+1, 'Só um item criado');
            checkCollection($pdo->inTransaction(), 'Transação exterior preservada');
        }
        $full = [];
        for ($i=1; $i<=18; $i++) $full[] = "bag_item{$i}_id=" . $id;
        Db::exec('UPDATE inventory SET '.implode(',', $full).' WHERE character_id=?', [$cid]);
        Db::exec('UPDATE hideout_rooms SET status=?,ts_activity_end=? WHERE id=?', [Hideout::STATUS_PRODUCING, GameTime::now()-1, $room['id']]);
        $before = Db::row('SELECT * FROM hideout_rooms WHERE id=?', [$room['id']]);
        $count = (int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?', [$cid]);
        try { $collect($params); throw new RuntimeException('Recolha com mochila cheia aceite.'); }
        catch (GameError $e) { checkCollection($e->getMessage()==='errInventoryNoEmptySlot', 'Mochila cheia rejeitada'); }
        checkCollection(Db::row('SELECT * FROM hideout_rooms WHERE id=?', [$room['id']])===$before, 'Falha preserva produção para recolher depois');
        checkCollection((int)Db::value('SELECT COUNT(*) FROM items WHERE character_id=?', [$cid])===$count, 'Falha não cria itens');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    echo "PASS: recolha da oficina; alterações revertidas.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
