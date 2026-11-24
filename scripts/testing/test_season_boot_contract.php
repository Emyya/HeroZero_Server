<?php
declare(strict_types=1);

// Verifica contrato de boot e a evidência do HAR, sem ligação à BD.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/server-laravel/actions/autoLoginUser.php');
if (!str_contains($source, '$data[\'season_rewards\'] = \\HeroZero\\Season::rewardsForCharacter($char->id());')) {
    throw new RuntimeException('Login não pré-carrega recompensas reais.');
}
echo "OK: login pré-carrega as recompensas antes da primeira abertura.\n";
if (!isset($argv[1])) exit(0);
$har = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$opens = [];
foreach ($har['log']['entries'] as $entry) {
    $action = null;
    foreach ($entry['request']['postData']['params'] ?? [] as $param) {
        if ($param['name'] === 'action') $action = $param['value'];
    }
    if ($action !== 'openSeasonPanel') continue;
    $content = $entry['response']['content'];
    $text = $content['text'] ?? '';
    if (($content['encoding'] ?? '') === 'base64') $text = base64_decode($text);
    $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR)['data'];
    $states = [];
    foreach ($data['season_rewards'] as $reward) {
        $states[(int)$reward['id']] = (int)$reward['status'];
        if ((!$reward['premium'] || $data['season_progress']['premium_unlocked']) && (int)$reward['season_points'] <= (int)$data['season_progress']['season_points'] && (int)$reward['status'] === 0) {
            throw new RuntimeException('Recompensa atingida aparece bloqueada na resposta.');
        }
    }
    $opens[] = $states;
}
if (count($opens) < 2 || $opens[0] !== $opens[1]) throw new RuntimeException('Esperadas duas aberturas com estados iguais.');
echo "OK: ambas as aberturas do HAR têm estados iguais, sem bloqueios falsos no servidor.\n";
