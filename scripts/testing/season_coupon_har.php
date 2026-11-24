<?php
declare(strict_types=1);

/** Apenas dados de prémios; nunca devolve tokens/pedidos completos do HAR. */
function seasonCouponHar(string $path): array
{
    if (!is_file($path)) throw new RuntimeException('HAR não encontrado.');
    $har=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $catalog=array_column(HeroZero\Season::rewardCatalog(),null,'id');
    $before=null;
    $missing=[];
    $wrong=[];
    $cid=null;
    foreach ($har['log']['entries'] ?? [] as $entry) {
        if (!str_contains($entry['request']['url'] ?? '', 'request.php')) continue;
        $params=[];
        foreach ($entry['request']['postData']['params'] ?? [] as $p) $params[$p['name']]=$p['value'];
        $text=$entry['request']['postData']['text'] ?? '';
        if ($text !== '') {
            $json=json_decode($text,true);
            if (is_array($json)) $params=array_merge($params,$json);
            else { parse_str($text,$form); $params=array_merge($params,$form); }
        }
        if (isset($params['data'])) $params=array_merge($params,json_decode((string)$params['data'],true) ?? []);
        $content=$entry['response']['content'] ?? [];
        $text=$content['text'] ?? '';
        if (($content['encoding'] ?? '') === 'base64') $text=base64_decode($text,true) ?: '';
        $response=json_decode($text,true);
        if (!is_array($response) || !empty($response['error'])) continue;
        $data=$response['data'] ?? $response;
        $rows=$data['user_vouchers'] ?? null;
        if (!is_array($rows)) continue;
        $current=array_column($rows,null,'id');
        if (($params['action'] ?? '') === 'claimSeasonReward' && $before !== null) {
            $id=(int)($params['season_reward_id'] ?? 0);
            if ((int)($data['season_reward']['id'] ?? 0) !== $id || (int)($data['season_reward']['status'] ?? 0) !== 2) throw new RuntimeException('HAR com resgate inconsistente.');
            $definition=json_decode($catalog[$id]['rewards'] ?? '{}',true);
            $type=(int)($definition['reward_type'] ?? 0);
            $cidHere=(int)($data['character']['id'] ?? 0);
            if ($cidHere <= 0 || ($cid !== null && $cid !== $cidHere)) throw new RuntimeException('HAR sem herói único.');
            $cid=$cidHere;
            $old=json_decode($data['season_reward']['rewards'] ?? '{}',true);
            $added=array_diff_key($current,$before);
            // Prova concreta da versão antiga: prémio devolvido como tipo 8/XP
            // sem criação de qualquer cupão. Não compensar descartes explícitos.
            $discard=filter_var($params['discard_item'] ?? false,FILTER_VALIDATE_BOOLEAN);
            if ($type === 8 && !$discard && (int)($old['reward_type'] ?? 0) === 8
                && (int)($old['reward_factor'] ?? 0) === (int)$definition['reward_factor'] && !$added) {
                $missing[$id]=['reward_id'=>$id,'amount'=>(int)$definition['reward_factor']];
            }
            if ($type === 12 && !$discard && isset($old['quest_energy'])) {
                foreach ($added as $v) if (json_decode($v['rewards'],true) === ['quest_energy'=>$old['quest_energy']]) {
                    $wrong[(int)$v['id']]=['voucher_id'=>(int)$v['id'],'reward_id'=>$id,'old_rewards'=>$v['rewards'],
                        'new_rewards'=>HeroZero\Season::clientRewardJson($catalog[$id]['rewards'])];
                }
            }
        }
        $before=$current;
    }
    return ['character_id'=>$cid,'missing_energy'=>array_values($missing),'wrong_resources'=>array_values($wrong)];
}
