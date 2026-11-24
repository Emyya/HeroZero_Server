#!/usr/bin/env python3
"""Gera documentação a partir dos assets; não acede à BD nem altera o jogo."""
import argparse
import collections
import hashlib
import json
from pathlib import Path
import re
import zlib

ROOT = Path(__file__).resolve().parents[2]


def compressed(path):
    return json.loads(zlib.decompress(path.read_bytes()))


def inline(value):
    return json.dumps(value, ensure_ascii=False, separators=(',', ':'))


def cell(value):
    return str(value).replace('|', '\\|').replace('\n', '<br>')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'docs/sidekicks/sidekicks_info.md')
    args = parser.parse_args()
    source = ROOT / 'assets/data/constants_json.data'
    public = ROOT / 'server-laravel/public/assets/data/constants_json.data'
    texts_path = ROOT / 'server-laravel/public/assets/i18n/pt_BR/text.data'
    data = compressed(source)
    texts = compressed(texts_path)
    pets = {key: value for key, value in data['item_templates'].items() if value.get('type') == 9}
    skills = data['sidekick_skills']
    fixed = collections.defaultdict(list)
    signatures = collections.defaultdict(list)
    for sid, skill in skills.items():
        listed = skill.get('fixed_sidekick') or []
        if isinstance(listed, str):
            listed = [listed]
        for pet in listed:
            fixed[pet].append(sid)
        # Correspondência nominal usada pelo cliente/backend; não é uma fonte de obtenção.
        pet = 'sidekick_' + skill['identifier']
        if skill['stage'] == 0 and pet in pets:
            signatures[pet].append(sid)

    refs = collections.defaultdict(list)
    ignored = {'item_templates', 'sidekick_data', 'sidekick_skills', 'profanity'}

    def scan(value, path, contexts):
        if isinstance(value, dict):
            for key, child in value.items():
                scan(child, path + [str(key)], contexts + [(path, value)])
        elif isinstance(value, list):
            for index, child in enumerate(value):
                scan(child, path + [str(index)], contexts)
        elif isinstance(value, str):
            # Também captura reward_data com JSON embutido.
            identifiers = set(re.findall(r'sidekick_[A-Za-z0-9_]+', value)) & pets.keys()
            for pet in sorted(identifiers):
                context = {}
                for _, record in contexts:
                    for key, field in record.items():
                        if isinstance(field, (str, int, float, bool)) or field is None:
                            if any(tag in key for tag in ('reward', 'chance', 'cost', 'token', 'date', 'required', 'value', 'active')):
                                if not isinstance(field, str) or len(field) < 500:
                                    context[key] = field
                refs[pet].append(('.'.join(path), context))

    for key, section in data.items():
        if key not in ignored:
            scan(section, [key], [])

    php = (ROOT / 'server-laravel/app/HeroZero/Sidekick.php').read_text()
    block = php.split('private const STATIC_SOURCES = [', 1)[1].split('\n    ];', 1)[0]
    configured = {pet: re.findall(r"'([^']+)'", values)
                  for pet, values in re.findall(r"'(sidekick_[^']+)'\s*=>\s*\[([^\]]*)\]", block)}
    # Política efetiva do backend: referências explícitas substituem fontes manuais.
    prefixes = {'event_quests': 'event_', 'event_draw_pools': 'event_draw_',
                'event_treasures': 'event_treasure_', 'hidden_object_event_quests': 'hidden_object_event_',
                'tower_event_objectives': 'tower_event_', 'legendary_dungeons': 'legendary_dungeon_'}
    effective = {}
    for pet in pets:
        derived = []
        for path, _ in refs[pet]:
            parts = path.split('.')
            if parts[0] == 'goals':
                source_key = 'goal_' + parts[1] + '_' + parts[3]
            elif parts[0] == 'seasons':
                source_key = ('season_premium_' if 'premium_' in parts[-1] else 'season_free_') + parts[1]
            elif parts[0] in prefixes:
                source_key = prefixes[parts[0]] + parts[1]
            else:
                continue
            if source_key not in derived:
                derived.append(source_key)
        effective[pet] = derived or configured.get(pet, [])
    source_counts = collections.Counter(path.split('.')[0] for rows in refs.values() for path, _ in rows)
    name = lambda pet: texts.get('sidekick/' + pet + '/name', pet)
    skname = lambda skill: texts.get('sidekick_skill/' + skill['identifier'] + '/name', skill['identifier'])
    effects = data['battle_effects']
    lines = []
    add = lines.append
    add('# Sidekicks — catálogo, habilidades especiais e obtenção\n')
    add('Documento gerado a partir das constantes locais oficiais e dos textos pt_BR. '
        'As fontes confirmadas abaixo são referências presentes nos dados; não significam '
        'que todos esses eventos estejam ativos ou implementados neste servidor.\n')
    add(f'**{len(pets)} pets · {len(skills)} habilidades · {sum(bool(refs[p]) for p in pets)} pets com referências de obtenção nas constantes.**\n')
    add('## Fontes e reprodução\n')
    for path in (source, public, texts_path):
        add(f'- `{path.relative_to(ROOT)}` — SHA-256 `{hashlib.sha256(path.read_bytes()).hexdigest()}`.')
    add('\nAs constantes são JSON comprimido com zlib. Os nomes e descrições vêm dos '
        'textos oficiais pt_BR, preservados como estão; os identificadores e números vêm '
        'das constantes. Os totais antigos 214/292 foram substituídos pela contagem real '
        'do ficheiro atual.\n')
    add('Cópias de constantes na raiz e pública: **' + ('conteúdo igual' if data == compressed(public) else 'conteúdo diferente; este documento usa a cópia da raiz') + '**.\n')
    add('```bash\npython3 scripts/testing/document_sidekicks.py\n# Gerar uma cópia para revisão sem substituir o documento:\npython3 scripts/testing/document_sidekicks.py --output=/tmp/sidekicks_info.md\n```\n')
    add('O gerador lê assets e `STATIC_SOURCES`; escreve apenas o Markdown indicado. '
        'Não consulta a BD, não modifica `Sidekick.php` e não altera pools de prémios.\n')
    add('## Como interpretar as fontes e habilidades\n')
    add('- **Obtenção nas constantes:** caminho exato para o pet num registo de recompensa. '
        'Inclui conquistas, passes, eventos, sorteios, tesouros, objetos escondidos, torres e covis lendários.')
    add('- **Sem origem explícita:** não foi encontrada uma referência de recompensa no ficheiro. '
        'Não prova que o pet venha da loja ou do vilão, nem que seja impossível obtê-lo.')
    add('- **Fonte local estática:** associação manual em `Sidekick::STATIC_SOURCES`, '
        'mostrada separadamente. Não é evidência extraída das constantes.')
    add('- **Habilidade fixa:** associação explícita através de `sidekick_skills.<id>.fixed_sidekick`. '
        'O estágio está indicado na própria habilidade; não se inventam habilidades para estágios ausentes.')
    add('- **Assinatura de estágio 0:** correspondência nominal `sidekick_` + identificador da habilidade. '
        'É a convenção usada pelo código para os pets de temporada, distinta de `fixed_sidekick`.')
    add('- Sem habilidades fixas, os estágios genéricos dependem do pet concreto em runtime. '
        'O catálogo não fornece as três habilidades sorteadas para cada exemplar.')
    add('- `value1..3` são valores brutos. Só são percentagens quando o texto/efeito assim o indicar. '
        '`chance`, tipos e fatores de recompensa são reproduzidos sem converter enums ou pesos em probabilidades presumidas.')
    add('- `charges = -1` representa cargas ilimitadas na interpretação do motor. '
        '`once_per_group_battle` e `legendary` são flags da habilidade, não fontes de obtenção.')
    add('- Datas de disponibilidade e níveis do template não garantem que a fonte de recompensa esteja '
        'ativa. Datas antigas/de referência são preservadas, sem inferir calendário atual.\n')
    add('### Secções com referências de obtenção\n')
    add('| Secção | Referências encontradas |\n|---|---:|')
    for section, count in sorted(source_counts.items()):
        add(f'| `{section}` | {count} |')
    add('\nA contagem é de referências, não de pets distintos. Um pet pode estar em vários eventos '
        'ou aparecer em vários patamares. Todos os caminhos encontrados estão na sua ficha.\n')
    add('### Caso confirmado: Kylie\n')
    assert any(path == 'goals.different_sidekick_collected.values.140.reward_identifier' for path, _ in refs['sidekick_peacock'])
    add('Kylie (`sidekick_peacock`) aparece em '
        '`goals.different_sidekick_collected.values.140.reward_identifier`: recompensa da conquista '
        'de 140 pets diferentes. A antiga associação local `shop_general` não vinha dessa referência. '
        'O backend foi corrigido para enviar `goal_different_sidekick_collected_140`, com texto que indica o limiar de 140, em vez do brechó.\n')
    add('## Índice dos pets\n')
    add('| Pet | Identificador | Qualidade | Habilidades associadas | Obtenção explícita |\n|---|---|---:|---:|---|')
    for pet, tpl in sorted(pets.items()):
        categories = sorted({path.split('.')[0] for path, _ in refs[pet]})
        add(f'| [{cell(name(pet))}](#{pet.replace("_", "-")}) | `{pet}` | {tpl["quality"]} | '
            f'{len(set(fixed[pet] + signatures[pet]))} | {", ".join(categories) or "Não indicada"} |')
    add('\n## Fichas de todos os pets\n')
    for pet, tpl in sorted(pets.items()):
        add(f'### {pet.replace("_", "-")}\n')
        add(f'**{name(pet)}** — `{pet}`\n')
        info = data.get('sidekick_data', {}).get(pet, {})
        add(f'- Qualidade: `{tpl["quality"]}`; nível requerido no template: `{tpl.get("required_level")}`.')
        add(f'- Flags do catálogo: especial `{inline(info.get("special_sidekick"))}`; '
            f'licenciado `{inline(info.get("licence_sidekick"))}`; premium-only `{inline(tpl.get("premium_only"))}`; '
            f'evento `{inline(tpl.get("is_event"))}`.')
        add(f'- Disponibilidade do template: `{tpl.get("ts_availability_start")}` a '
            f'`{tpl.get("ts_availability_end")}`; desbloqueio `{tpl.get("ts_unlock")}`.\n')
        add('**Onde é obtido — referências nas constantes:**\n')
        if not refs[pet]:
            add('Nenhuma fonte de recompensa explícita encontrada. Não classificar automaticamente como loja/vilão.\n')
        for path, context in refs[pet]:
            label = ''
            if path.startswith('goals.'):
                parts = path.split('.')
                label = f'Conquista `{parts[1]}`, limiar `{parts[3]}`. '
            elif path.startswith('seasons.'):
                parts = path.split('.')
                label = f'Temporada `{parts[1]}`, patamar `{parts[3]}`, ' + ('faixa premium. ' if 'premium_' in path else 'faixa normal. ')
            add(f'- {label}`{path}`')
            if context:
                add(f'  - Contexto bruto: `{inline(context)}`')
        add('\n**Configuração local estática, não oficial:** ' +
            (', '.join(f'`{item}`' for item in configured[pet]) if pet in configured else 'sem entrada em `STATIC_SOURCES`') + '.\n')
        add('**Fontes efetivas em `all_sidekicks.sources`:**\n')
        if not effective[pet]:
            add('Lista vazia: não há origem derivada nem fallback configurado.\n')
        for source_key in effective[pet]:
            add('- `' + source_key + '` — ' + texts.get('sidekick_source/' + source_key, 'texto indisponível'))
        add('\nFontes explícitas prevalecem sobre o fallback manual; não se anunciam ambas quando são incompatíveis.\n')
        add('**Habilidades especiais/fixas:**\n')
        ids = sorted(set(fixed[pet] + signatures[pet]), key=lambda sid: (skills[sid]['stage'], int(sid)))
        if not ids:
            add('Sem habilidade fixa/assinatura associada nas constantes; os estágios genéricos são dados de runtime.\n')
        for sid in ids:
            skill = skills[sid]
            association = '`fixed_sidekick` explícito' if sid in fixed[pet] else 'assinatura por correspondência nominal, estágio 0'
            add(f'- [{skname(skill)}](#habilidade-{sid}) — `{skill["identifier"]}`, '
                f'ID `{sid}`, estágio `{skill["stage"]}`; {association}.')
            short = texts.get('sidekick_skill/' + skill['identifier'] + '/short_description', '')
            if short:
                add('  - ' + short)
            add('  - Valores: ' + ', '.join(f'`value{i}={inline(skill.get("value" + str(i)))}`' for i in range(1, 4)) + '.')
        season_id = pet.removeprefix('sidekick_season_') if pet.startswith('sidekick_season_') else None
        if season_id in data.get('seasons', {}):
            season = data['seasons'][season_id]
            add('\n**Efeito do passe nos patamares da temporada:**\n')
            add('Efeito bruto: `' + str(season.get('sidekick_battle_effect_id')) + '`. '
                'Os valores dos patamares abaixo não são somados automaticamente aos valores da skill.\n')
            add('| Pontos | value_1 | value_2 | value_3 |\n|---:|---|---|---|')
            for points, row in season.get('season_points', {}).items():
                add('| ' + str(points) + ' | ' + ' | '.join(inline(row.get('sidekick_battle_effect_value_' + str(i))) for i in range(1, 4)) + ' |')
        add('\n<details>\n<summary>Template completo e dados visuais do pet</summary>\n')
        add('```json\n' + json.dumps({'item_template': tpl, 'sidekick_data': info}, ensure_ascii=False, indent=2) + '\n```\n</details>\n')
    add('## Catálogo completo das habilidades\n')
    add('Inclui habilidades genéricas, fixas e assinaturas. Descrições oficiais mantêm '
        'os placeholders `%1`, `%2`, `%3` e condicionais de género; usar os valores brutos '
        'do registo e o formatador do cliente para os substituir. A presença no catálogo '
        'não demonstra que todos os efeitos estejam implementados no backend.\n')
    for sid, skill in sorted(skills.items(), key=lambda row: int(row[0])):
        add(f'### Habilidade {sid}\n')
        add(f'**{skname(skill)}** — `{skill["identifier"]}`\n')
        for suffix in ('short_description', 'description'):
            value = texts.get('sidekick_skill/' + skill['identifier'] + '/' + suffix)
            if value:
                add(value + '\n')
        add('Registo completo em `sidekick_skills.' + sid + '`:\n')
        add('```json\n' + json.dumps(skill, ensure_ascii=False, indent=2) + '\n```\n')
        mapped = {eid: effect for eid, effect in effects.items()
                  if skill.get('sidekick_battle_effect_id') in (effect.get('sidekick_battle_effect_ids') or [])}
        if mapped:
            add('Mapeamento em `battle_effects` (inclui flags de aplicação):\n')
            add('```json\n' + json.dumps(mapped, ensure_ascii=False, indent=2) + '\n```\n')
        else:
            add('Sem mapeamento deste efeito em `battle_effects`; não foi inventada uma interpretação adicional.\n')
    add('## Progressão, custos e outras referências\n')
    add('Escalares `sidekick_*` e `item_sidekick_*` existentes nas constantes:\n')
    add('| Chave | Valor bruto |\n|---|---|')
    for key, value in sorted(data.items()):
        if key.startswith(('sidekick_', 'item_sidekick_')) and not isinstance(value, (dict, list)):
            add(f'| `{key}` | `{inline(value)}` |')
    add('\nTabela oficial de XP acumulado por nível:\n')
    add('| Nível | XP |\n|---:|---:|')
    for level, row in sorted(data['sidekick_levels'].items(), key=lambda entry: int(entry[0])):
        add(f'| {level} | {row["xp"]} |')
    add('\nDocumentação complementar: [scaling](scaling.md), '
        '[habilidades implementadas](skills_implementadas.md), '
        '[protocolo](extract/01_protocolo_acoes.md), [combate](extract/03_combate.md), '
        '[interface e ciclo de vida](extract/04_ui_ciclo_vida.md).\n')
    add('## Geração atual no brechó em época normal\n')
    normal_pets = [pet for pet in pets if 'shop_general' in effective[pet]]
    add(f'A geração atual do brechó usa apenas fonte `shop_general`: {len(normal_pets)} pets elegíveis. '
        'Pets de conquistas, eventos, ofertas, desbloqueio e covis ficam fora. A loja desportiva '
        'não tem candidatos na interseção com o pool normal e não gera pets por agora. '
        'A alteração aplica-se às novas ofertas, sem remover pets já possuídos. Os atributos '
        'são sorteados na geração, guardados no preview e preservados na compra, sem novo sorteio. Ver '
        '[regras das lojas](../shop_items/lojas.md).\n')
    add('## Limites da extração e configuração local\n')
    add('A extração percorre recursivamente todas as secções e procura identificadores '
        'em valores, incluindo JSON embutido em `reward_data`. Exclui definições de '
        'template, dados visuais e associações de skills como fontes de obtenção. '
        'Não deduz fontes a partir do nome do pet, de imagens, de texto comercial '
        'ou de uma associação manual no backend.\n')
    add('O catálogo local `Sidekick::official()` deriva as oito famílias de fontes e usa '
        '`STATIC_SOURCES` apenas quando não existe referência explícita. O nome do método '
        'não torna oficiais os fallbacks manuais. Habilidades e propriedade não são '
        'alteradas por esta correção das fontes. Novos identificadores de fonte precisam '
        'de texto pt_BR; usar `sync_sidekick_source_texts.py` após atualizar os assets.\n')
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text('\n'.join(lines), encoding='utf-8')
    print(f'Gerado: {args.output}; {len(pets)} pets, {len(skills)} habilidades, '
          f'{sum(len(rows) for rows in refs.values())} referências de obtenção.')


if __name__ == '__main__':
    main()
