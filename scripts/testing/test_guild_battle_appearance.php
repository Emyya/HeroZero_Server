<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/server-laravel/vendor/autoload.php';
use HeroZero\{Db, GuildBattles};

function appearanceCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
try {
    $raw = ['162' => ['name' => 'laliene', 'missiles' => [], 'missiles1' => '',
        'missiles2' => null, 'missiles3' => 'missiles_slotmachine_set1', 'stamina' => 123]];
    $normalized = json_decode(GuildBattles::clientProfiles(json_encode($raw)), true);
    appearanceCheck($normalized[162]['missiles'] === null, 'Lista vazia não normalizada');
    appearanceCheck($normalized[162]['missiles1'] === null, 'String vazia não normalizada');
    appearanceCheck($normalized[162]['missiles3'] === 'missiles_slotmachine_set1', 'Identificador válido alterado');
    appearanceCheck($normalized[162]['stamina'] === 123, 'Atributos alterados');
    appearanceCheck(GuildBattles::clientProfiles('') === '', 'Snapshot ausente alterado');
    appearanceCheck(GuildBattles::clientProfiles('{}') === '{}', 'Mapa vazio alterado');
    $method = new ReflectionMethod(GuildBattles::class, 'guildSideProfiles');
    $side = $method->invoke(null, [['_cid' => 162, 'missiles' => [], 'strength' => 10, 'weapondamage' => 5]]);
    appearanceCheck($side[162]['missiles'] === null && $side[162]['damage_bonus'] === 15,
        'Novas batalhas não usam aparência válida');
    echo "PASS offline: arremessos ausentes, identificador equipado, restantes campos e novas batalhas.\n";
    if (in_array('--mysql', $argv, true)) {
        $app = require dirname(__DIR__, 2).'/server-laravel/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $checked = 0;
        foreach (Db::rows('SELECT attacker_character_profiles,defender_character_profiles FROM guild_battle WHERE status=3') as $battle) {
            foreach ($battle as $json) {
                $profiles = json_decode(GuildBattles::clientProfiles((string)$json), true) ?: [];
                foreach ($profiles as $profile) {
                    foreach (['missiles', 'missiles1', 'missiles2', 'missiles3', 'missiles4'] as $field) {
                        if (!array_key_exists($field, $profile)) continue;
                        appearanceCheck($profile[$field] === null || (is_string($profile[$field]) && $profile[$field] !== ''),
                            'Tipo inválido numa batalha histórica');
                    }
                    $checked++;
                }
            }
        }
        echo "PASS MySQL: {$checked} perfis históricos normalizados em leitura; sem alterar a BD.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
