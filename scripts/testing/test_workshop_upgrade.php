<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Hideout;

// Apenas o contador de objetivos toca na BD durante a conclusão de uma evolução.
class UpgradeTestStatement extends PDOStatement
{
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return '{}'; }
    public function rowCount(): int { return 1; }
}
class UpgradeTestPDO extends PDO
{
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (!str_contains($query, 'goal_stats')) throw new RuntimeException('SQL inesperado: ' . $query);
        return new UpgradeTestStatement();
    }
}
(new ReflectionProperty(Db::class, 'pdo'))->setValue(null, new UpgradeTestPDO());
$char = (new ReflectionClass(Character::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Character::class, 'row'))->setValue($char, ['id' => 25296]);

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}
$fixture = ['id' => 33, 'character_id' => 25296, 'identifier' => 'gem_production',
    'level' => 8, 'status' => Hideout::STATUS_UPGRADING, 'ts_activity_end' => 1793917547,
    'current_resource_amount' => 0, 'max_resource_amount' => 0, 'current_generator_level' => 0,
    'additional_value_2' => '{"improvement_slot":1}'];
foreach ([Hideout::STATUS_BUILDING, Hideout::STATUS_UPGRADING, Hideout::STATUS_PLACING] as $status) {
    $room = array_merge($fixture, ['status' => $status]);
    $h = ['idle_worker_count' => 0, 'max_worker_count' => 1, 'current_level' => 8,
        'current_attacker_units' => 0, 'current_defender_units' => 0];
    $rooms = [$room];
    Hideout::completeRoomActivity($char, $h, $room, $rooms);
    check($room['status'] === Hideout::STATUS_IDLE && $room['ts_activity_end'] === 0,
        "Atividade {$status}: oficina fica disponível, sem produção fictícia");
    check($room['current_resource_amount'] === 0 && $room['max_resource_amount'] === 0,
        'Sem modificação para resgatar');
    check($room['level'] === ($status === Hideout::STATUS_BUILDING ? 1 : ($status === Hideout::STATUS_UPGRADING ? 9 : 8)),
        'Nível atualizado corretamente');
}
foreach (Hideout::MANUAL_PRODUCTION_ROOMS as $identifier) {
    $old = array_merge($fixture, ['identifier' => $identifier, 'status' => Hideout::STATUS_PRODUCING,
        'ts_activity_end' => 0, 'max_resource_amount' => 1]);
    $fixed = Hideout::normalizeManualRoomState($old);
    check($fixed['status'] === Hideout::STATUS_IDLE && $fixed['level'] === 8,
        "{$identifier}: recupera estado antigo sem alterar o nível");
    foreach ([1, time() + 3600] as $deadline) {
        $real = array_merge($old, ['ts_activity_end' => $deadline]);
        check(Hideout::normalizeManualRoomState($real) === $real, 'Produção real pronta/em curso preservada');
    }
}
foreach (Hideout::AUTO_PRODUCTION_ROOMS as $identifier) {
    $auto = array_merge($fixture, ['identifier' => $identifier, 'status' => Hideout::STATUS_PRODUCING, 'ts_activity_end' => 0]);
    check(Hideout::normalizeManualRoomState($auto) === $auto, "{$identifier}: produção automática preservada");
}
if (isset($argv[1])) {
    $har = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $count = 0;
    foreach ($har['log']['entries'] as $entry) {
        $content = $entry['response']['content'];
        $text = $content['text'] ?? '';
        if (($content['encoding'] ?? '') === 'base64') $text = base64_decode($text);
        $response = json_decode($text, true);
        foreach ($response['data']['hideout_rooms'] ?? [] as $room) {
            if ($room['identifier'] !== 'gem_production' || (int)$room['status'] !== 6 || (int)$room['ts_activity_end'] !== 0) continue;
            $fixed = Hideout::normalizeManualRoomState($room);
            check($fixed['status'] === 1 && $fixed['level'] === $room['level'], 'HAR: evolução preservada e resgate fictício removido');
            $count++;
        }
    }
    check($count > 0, "HAR: {$count} estados inválidos recuperados");
}
echo "Regressão validada; nenhum acesso à BD real.\n";
