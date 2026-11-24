<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';

use HeroZero\Hideout;
use HeroZero\ItemImprovement;

function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK: {$label}\n";
}

// Regresso ao caso real: o cliente procura improvement_slot, não slot.
$room = ['id' => 33, 'identifier' => 'gem_production', 'status' => 1, 'level' => 5,
    'current_resource_amount' => 0, 'max_resource_amount' => 0,
    'ts_last_resource_change' => 0, 'ts_activity_end' => 0,
    'additional_value_2' => '{"slot":2,"ts_slot_refresh":1793911892}'];
$shaped = Hideout::shapeRoom($room);
$data = json_decode($shaped['additional_value_2'], true);
check($data['improvement_slot'] === 2 && !isset($data['slot']), 'Estado antigo compatível com o seletor do cliente');
check($data['ts_slot_refresh'] === 1793911892, 'Compatibilidade preserva data do sorteio');

$catalog = ItemImprovement::catalog();
foreach (range(1, 7) as $slot) {
    $rarities = [];
    foreach (range(1, 3) as $quality) {
        $rarities[] = ['quality' => $quality, 'stat_stamina' => 10 * $quality,
            'stat_strength' => 0, 'stat_critical_rating' => 0, 'stat_dodge_rating' => 0];
    }
    $state = Hideout::improvementData(['additional_value_2' => json_encode(['slot' => $slot, 'improvement_rarity' => $rarities])]);
    $wire = json_decode(json_encode($state));
    check(is_object($wire->improvement_rarity), "Tipo {$slot}: raridades como objeto, não array");
    foreach ((array)$wire->improvement_rarity as $index => $rarity) {
        $definition = $catalog[$rarity->identifier] ?? [];
        check(($definition['type'] ?? 0) === $slot && ($definition['quality'] ?? 0) === $rarity->quality,
            "Tipo {$slot}, opção {$index}: identificador reconhecido pelo cliente");
        check($rarity->stat_stamina === 10 * $rarity->quality && $rarity->id === 0, 'Pré-visualização preserva atributos');
    }
    $again = Hideout::improvementData(['additional_value_2' => json_encode($state)]);
    check(json_encode($state) === json_encode($again), 'Normalização idempotente');
}
foreach ([13, 14] as $slot) {
    $state = Hideout::improvementData(['additional_value_2' => json_encode(['slot' => $slot])]);
    check($state['improvement_slot'] === $slot, "Consumível {$slot}: tipo preservado");
}

// Opcional: reprocessar as respostas capturadas pelo utilizador.
if (isset($argv[1])) {
    $har = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $count = 0;
    foreach ($har['log']['entries'] as $entry) {
        $content = $entry['response']['content'];
        $text = $content['text'] ?? '';
        if (($content['encoding'] ?? '') === 'base64') $text = base64_decode($text);
        $response = json_decode($text, true);
        foreach ($response['data']['hideout_rooms'] ?? [] as $captured) {
            if ($captured['identifier'] !== 'gem_production') continue;
            $fixed = json_decode(Hideout::shapeRoom($captured)['additional_value_2'], true);
            check(in_array($fixed['improvement_slot'] ?? 0, [1, 2, 3, 4, 5, 6, 7, 13, 14], true), 'Resposta HAR contém tipo visível após correção');
            $count++;
        }
    }
    check($count > 0, "HAR: {$count} respostas da oficina verificadas");
}
echo "Contrato da oficina validado sem acesso à BD.\n";
