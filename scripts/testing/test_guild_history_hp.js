// Offline: executa o método real do bundle, sem carregar o jogo.
// Opcional: node scripts/testing/test_guild_history_hp.js /tmp/battle.json
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../server-laravel/public/js/HeroZero.min.js'), 'utf8');
const start = source.indexOf('se.prototype.endHitPoints=function(a,b)');
const end = source.indexOf(';se.prototype.get_ownGuildJoinedMemberCount', start);
assert(start >= 0 && end > start);
const context = {se: {prototype: {}}};
vm.runInNewContext(source.slice(start, end), context);
const hp = context.se.prototype.endHitPoints;
const hero = (id, health) => ({get_id: () => String(id), get_hitPoints: () => health});
const round = r => ({
    get_attacker: () => String(r.a), get_attacked: () => String(r.d),
    get_damage: () => r.v || 0, get_dotDamage: () => r.dt || 0,
    get_bonusDamage: () => r.bd || 0, get_backfireDamage: () => r.bf || 0,
    get_healingAttacked: () => r.hd || 0, get_healingAttacker: () => r.ha || 0,
});
const calculate = (h, rounds, limit) => hp.call({get_battleRounds: () => rounds.map(round)}, h, limit);
const rounds = [{a: 2,d: 1,v: 80}, {a: 1,d: 2,v: 100},
                {a: 3,d: 1,v: 40}, {a: 1,d: 3,v: 100,bf: 5,ha: 2}];
assert.equal(calculate(hero(1,100),rounds),57, 'reinicia ao mudar de adversário');
assert.equal(calculate(hero(1,100),rounds,2),20, 'limite global antes da mudança');
assert.equal(calculate(hero(1,100),rounds,3),60, 'limite global após a mudança');
assert.equal(calculate(hero(1,100),rounds,0),100);
assert.equal(calculate(hero(9,100),rounds),100, 'herói que não entrou');
assert.equal(calculate(hero(1,100),[{a:2,d:1,v:60,dt:10,bd:5,hd:4}]),29);
assert.equal(calculate(hero(1,100),[{a:2,d:1,v:200}]),0, 'sem HP negativo');
assert.equal(calculate(hero(1,100),[{a:1,d:2,v:10},{a:2,d:1,v:20}]),80, 'troca de iniciativa não reinicia');
if (process.argv[2]) {
    const battle = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
    const profiles = [...Object.values(JSON.parse(battle.attacker_character_profiles)),
                      ...Object.values(JSON.parse(battle.defender_character_profiles))];
    const actualRounds = JSON.parse(battle.rounds);
    const result = Object.fromEntries(profiles.map(p => [p.name, calculate(hero(p.profile, p.stamina*10), actualRounds)]));
    assert.equal(result.TripeiroUrbano_1992,254);
    assert.equal(result.NoobZero_2008,0);
    assert.equal(result.laliene,13160);
    console.log('Batalha #8:', JSON.stringify(result));
}
console.log('OK: vida no histórico e reprodução parcial da guilda');
