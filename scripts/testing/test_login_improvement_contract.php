<?php
declare(strict_types=1);

// Regressão do contrato de boot, sem ligação à BD.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/server-laravel/actions/autoLoginUser.php');
if (!str_contains($source, '$data[\'item_improvements\'] = \\HeroZero\\ItemImprovement::data($char->id());')) {
    throw new RuntimeException('Boot não envia modificações da conta.');
}
echo "OK: autoLoginUser inclui as modificações reais da conta.\n";

if (!isset($argv[1])) exit(0);
$har = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$boot = null;
$known = [];
foreach ($har['log']['entries'] as $entry) {
    $content = $entry['response']['content'];
    $text = $content['text'] ?? '';
    if (($content['encoding'] ?? '') === 'base64') $text = base64_decode($text);
    $response = json_decode($text, true);
    $action = null;
    foreach ($entry['request']['postData']['params'] ?? [] as $param) {
        if ($param['name'] === 'action') $action = $param['value'];
    }
    if ($action === 'autoLoginUser') $boot = $response['data'] ?? null;
    foreach ($response['data']['item_improvements'] ?? [] as $gem) $known[(int)$gem['id']] = $gem;
}
if (!$boot) throw new RuntimeException('HAR sem resposta de login.');
$count = 0;
foreach ($boot['items'] ?? [] as $item) {
    $link = (int)($item['ts_availability_end'] ?? 0);
    if ($link >= 0) continue;
    if (!isset($known[-$link])) throw new RuntimeException('Gema não encontrada em nenhuma resposta: ' . -$link);
    $count++;
}
echo "OK: as {$count} gemas referenciadas pelos equipamentos do login existem nas respostas do HAR.\n";
echo "Verificação de contrato/fixture; não executa login contra a BD.\n";
