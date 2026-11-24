<?php
declare(strict_types=1);
namespace HeroZero;

/** Associações visuais locais; ver docs/missoes/npcs.md para evidência/limitações. */
final class QuestNpcs
{
    private static ?array $map = null;

    public static function catalog(): array
    {
        return self::$map ??= json_decode((string)file_get_contents(__DIR__.'/../../data/reference/quest_npcs.json'),true,512,JSON_THROW_ON_ERROR)['missions'];
    }

    public static function identifier(string $mission, string $fallback = ''): string
    {
        return self::catalog()[$mission] ?? $fallback;
    }

    /** Corrige apenas ofertas; a condição protege contra início/retoma concorrente. */
    public static function repairOffer(int $cid, array $quest): array
    {
        if ((int)$quest['type']!==2 || (int)$quest['status']!==1) return $quest;
        $npc=self::identifier($quest['identifier'],(string)$quest['fight_npc_identifier']);
        if ($npc===$quest['fight_npc_identifier']) return $quest;
        $changed=Db::exec('UPDATE quests SET fight_npc_identifier=? WHERE id=? AND character_id=? AND type=2 AND status=1 AND identifier=? AND fight_npc_identifier=?',
            [$npc,(int)$quest['id'],$cid,$quest['identifier'],$quest['fight_npc_identifier']]);
        if ($changed===1) $quest['fight_npc_identifier']=$npc;
        else $quest=Db::row('SELECT * FROM quests WHERE id=? AND character_id=?',[(int)$quest['id'],$cid])??$quest;
        return $quest;
    }
}
