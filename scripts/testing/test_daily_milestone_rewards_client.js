// Parser e diálogo reais do bundle: pontos livres aparecem junto da temporada.
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync(__dirname + '/../../server-laravel/public/js/HeroZero.min.js', 'utf8').replace(/=\s+function/g, '=function');
function assignment(prefix) {
    const start = source.indexOf(prefix);
    assert(start >= 0, prefix);
    const body = source.indexOf('{', start);
    let depth = 1, end = body + 1;
    while (depth) { if (source[end] === '{') depth++; if (source[end] === '}') depth--; end++; }
    return source.slice(start, end) + ';';
}
function Reward() {}
function Entry(type, amount) { this.type = type; this.amount = amount; }
Entry.fromSeasonPoints = () => new Entry(42, 1);
const context = vm.createContext({ec: Reward, ea: Entry, gp: function() {},
    f: {current: {text: () => ''}}, Kf: function(entries) { this.entries = entries; }});
const parser = assignment('ec.prototype.get_genericRewards=function');
// Isola os outros prémios/serviços; o parser, getter de pontos e diálogo são reais.
for (const name of new Set([...parser.matchAll(/this\.(get_\w+)\(/g)].map(m => m[1])))
    Reward.prototype[name] = () => name === 'get_boosterId' || name === 'get_item' || name.includes('Identifier') || name === 'get_opticalChange' || name === 'get_dungeonKey' || name.includes('legendaryDungeonModification') || name === 'get_legendaryDungeonKey' ? null : 0;
vm.runInContext(assignment('ec.prototype.get_statPoints=function'), context);
vm.runInContext(parser, context);
vm.runInContext(assignment('gp.prototype.refresh=function'), context);
for (const type of [2,3,4,5]) for (const amount of [5,10,20]) {
    const reward = new Reward();
    reward._reward = {getInt: key => key === 'statPoints' ? amount : 0};
    const dialog = Object.create(context.gp.prototype);
    Object.assign(dialog, {
        _reward: {get_type: () => type, get_reward: () => reward},
        _content: {txtDesciption: {set_text() {}}, rewardArea: {}},
        get_character: () => ({get_seasonProgress: () => ({isActive: () => true})})
    });
    dialog.refresh();
    assert.deepStrictEqual(Array.from(dialog._rewardLine.entries, e => [e.type,e.amount]), [[5,amount],[42,1]]);
}
console.log('PASS: diálogo real mostra 5/10/20 pontos livres e temporada em duelos, liga, esconderijo e energia.');
