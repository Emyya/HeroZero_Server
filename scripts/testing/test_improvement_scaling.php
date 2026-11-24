<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';

use HeroZero\Db;
use HeroZero\ItemImprovement;
use HeroZero\Sidekick;

function check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}

// Valores de referência do cliente: percentagem com duas casas e mínimo de 5 membros.
check(ItemImprovement::workshopGuildBonus(100, 10) === 50.0, 'Bónus bruto 100 / 10 membros = 50%');
check(ItemImprovement::workshopGuildBonus(1, 3) === 1.0, 'Equipas pequenas usam denominador mínimo de 5 membros');
check(ItemImprovement::workshopGuildBonus(1, 30) === 0.17, 'Arredondamento da percentagem antes de calcular atributos');
check(ItemImprovement::workshopMaxStats(300, 10) === 360, 'Herói 300, oficina 10, sem equipa: máximo 360');
check(ItemImprovement::workshopMaxStats(300, 10, 100, 10) === 480, 'Bónus de equipa entra no máximo: 480');
check(ItemImprovement::workshopMaxStats(1000, 10, 1, 30) === 1201, 'Percentagem arredondada segue fórmula do cliente');
check(ItemImprovement::workshopMaxStats(1, 1) === 1, 'Máximo pequeno usado na regressão de orçamento');

foreach ([0, 1, 2, 3, 7, 360, 480, 10000] as $maximum) {
    for ($repeat = 0; $repeat < 50; $repeat++) {
        foreach (ItemImprovement::workshopRarities($maximum) as $index => $option) {
            $quality = $option['quality'];
            unset($option['quality']);
            $budget = (int)floor($maximum * (($index + 1) / 3.0) + 0.5);
            if ($quality !== $index + 1 || array_sum($option) !== $budget || min($option) < 0
                || count(array_filter($option)) !== min($quality, $budget)) {
                throw new RuntimeException("Orçamento/distribuição inválidos: máximo {$maximum}");
            }
        }
    }
}
check(true, 'Distribuição conserva orçamento e não cria atributos extra nos máximos 0/1/2');

// PDO simulado: testa a fonte partilhada e aplicação da gema sem tocar na BD real.
final class ScalingPdo extends PDO
{
    public int $guildId = 7;
    public array $guild = ['gem_production_level' => 100];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ScalingStatement($this, $query);
    }
}
final class ScalingStatement extends PDOStatement
{
    public function __construct(private ScalingPdo $db, private string $sql) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed
    {
        if (str_contains($this->sql, 'SELECT guild_id')) return $this->db->guildId;
        if (str_contains($this->sql, 'SELECT COUNT(*)')) return 10;
        throw new RuntimeException('Consulta inesperada: ' . $this->sql);
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'FROM `guild`')) return $this->db->guild;
        if (str_contains($this->sql, 'FROM items')) return [
            'quality' => 1, 'stat_stamina' => 100, 'stat_strength' => 1,
            'stat_critical_rating' => 0, 'stat_dodge_rating' => 50,
        ];
        throw new RuntimeException('Consulta inesperada: ' . $this->sql);
    }
}
$property = new ReflectionProperty(Db::class, 'pdo');
$previous = $property->getValue();
$db = new ScalingPdo();
$property->setValue(null, $db);
try {
    check(ItemImprovement::workshopGuildData(1) === ['level' => 100, 'members' => 10], 'Fonte comum de bónus para geração e resposta');
    $db->guild = [];
    check(ItemImprovement::workshopGuildData(1) === ['level' => 0, 'members' => 10], 'Schema sem coluna opcional mantém bónus zero');
    $db->guildId = 0;
    check(ItemImprovement::workshopGuildData(1) === ['level' => 0, 'members' => 0], 'Herói sem equipa');
    $pet = ['character_id' => 1, 'quality' => 3, 'level' => 30, 'item_improvement_id' => -12,
        'stat_base_stamina' => 1000, 'stat_base_strength' => 10,
        'stat_base_critical_rating' => 20, 'stat_base_dodge_rating' => 100];
    $result = Sidekick::withImprovement($pet);
    check($result['stat_stamina'] === 1642 && $result['stat_strength'] === 16
        && $result['stat_critical_rating'] === 30 && $result['stat_dodge_rating'] === 224,
        'Gema comum num pet épico nível 30: fator do pet, aplicado à base com gema');
    check($pet['stat_base_stamina'] === 1000 && Sidekick::withImprovement($pet) === $result,
        'Leituras repetidas não acumulam a gema nas bases originais');
} finally {
    $property->setValue(null, $previous);
}
echo "Scaling validado offline e com PDO simulado; sem integração MySQL.\n";
