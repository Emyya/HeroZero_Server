<?php
declare(strict_types=1);

// Regressão sem ligação à BD: executa o dispatch real com PDO simulado.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';

use HeroZero\Db;
use HeroZero\Season;

class SeasonPointsStatement extends PDOStatement
{
    public function __construct(private SeasonPointsPDO $db) {}
    public function execute(?array $params = null): bool
    {
        if ((int)$params[1] !== 25296) throw new RuntimeException('Personagem inesperado');
        $this->db->points += (int)$params[0];
        return true;
    }
    public function rowCount(): int { return 1; }
}
class SeasonPointsPDO extends PDO
{
    public int $points = 0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($query !== 'UPDATE `character` SET stat_points_available = stat_points_available + ? WHERE id = ?') {
            throw new RuntimeException('SQL inesperado (pontos não podem entregar donuts): ' . $query);
        }
        return new SeasonPointsStatement($this);
    }
}

$db = new SeasonPointsPDO();
(new ReflectionProperty(Db::class, 'pdo'))->setValue(null, $db);
$deliver = new ReflectionMethod(Season::class, 'deliverReward');
foreach ([3 => 10] as $type => $amount) {
    $before = $db->points;
    $deliver->invoke(null, 25296, ['reward_type' => $type, 'reward_factor' => $amount]);
    if ($db->points !== $before + $amount) throw new RuntimeException('Crédito incorreto');
    echo "OK: tipo {$type} entrega {$amount} pontos gratuitos, sem donuts\n";
}
