#!/usr/bin/env python3
"""Acrescenta traduções pt_BR das fontes de pet derivadas das constantes, sem acesso à BD."""
import argparse
import json
from pathlib import Path
import zlib

ROOT = Path(__file__).resolve().parents[2]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--dry-run', action='store_true', help='Mostra quantidades sem escrever assets.')
    args = parser.parse_args()
    constants = json.loads(zlib.decompress((ROOT / 'assets/data/constants_json.data').read_bytes()))
    for path in [ROOT / 'assets/i18n/pt_BR/text.data', ROOT / 'server-laravel/public/assets/i18n/pt_BR/text.data']:
        texts = json.loads(zlib.decompress(path.read_bytes()))
        additions = {}
        for goal_id, goal in constants.get('goals', {}).items():
            for target, row in goal.get('values', {}).items():
                if not str(row.get('reward_identifier', '')).startswith('sidekick_'):
                    continue
                key = 'sidekick_source/goal_' + goal_id + '_' + target
                if goal_id == 'different_sidekick_collected':
                    label = f'ao colecionar {target} pets diferentes (recompensa de ato heroico)'
                elif goal_id == 'dungeon_hardmode_quest_completed':
                    label = f'ao completar {target} missões de dungeon em modo difícil (recompensa de ato heroico)'
                else:
                    label = texts.get('sidekick_source/goal', 'como recompensa de um ato heroico') + f' ({goal_id}: {target})'
                additions[key] = label
        for section, prefix, label in [
            ('event_quests', 'event_', 'como recompensa do evento'),
            ('event_draw_pools', 'event_draw_', 'no sorteio do evento'),
            ('event_treasures', 'event_treasure_', 'como recompensa do tesouro do evento'),
            ('hidden_object_event_quests', 'hidden_object_event_', 'como recompensa do evento de objetos escondidos'),
            ('tower_event_objectives', 'tower_event_', 'como recompensa da Academia Heroica'),
            ('legendary_dungeons', 'legendary_dungeon_', 'como recompensa do covil lendário'),
        ]:
            for identifier, definition in constants.get(section, {}).items():
                if 'sidekick_' not in json.dumps(definition):
                    continue
                name = next((texts[key] for key in [f'event/{identifier}/name', f'event_quest/{identifier}/name',
                                                    f'event_quest/{identifier}/title', f'season/{identifier}/name'] if key in texts), identifier)
                additions['sidekick_source/' + prefix + identifier] = label + ' “' + name + '”'
        for season_id, season in constants.get('seasons', {}).items():
            for row in season.get('season_points', {}).values():
                for field, prefix, label in [('premium_reward_identifier', 'season_premium_', 'como recompensa do passe premium'),
                                             ('reward_identifier', 'season_free_', 'como recompensa gratuita da temporada')]:
                    if str(row.get(field, '')).startswith('sidekick_'):
                        additions['sidekick_source/' + prefix + season_id] = label + ' “' + texts.get('season/' + season_id + '/name', season_id) + '”'
        new = {key: value for key, value in additions.items() if key not in texts}
        print(f'{path.relative_to(ROOT)}: {len(new)} novas traduções' + (' (simulação)' if args.dry_run else ''))
        if not args.dry_run and new:
            texts.update(new)
            path.write_bytes(zlib.compress(json.dumps(texts, ensure_ascii=False, separators=(',', ':')).encode()))


if __name__ == '__main__':
    main()
