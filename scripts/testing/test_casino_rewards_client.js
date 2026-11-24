// Parser real e agregação real do diálogo do casino; gráficos simulados.
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

const cp = require('child_process'), path = require('path');
Entry.fromBooster = (id, days) => Object.assign(new Entry(8, 1), {id,days});
for (const getter of ['questEnergy','boosterId','boosterDays'])
    vm.runInContext(assignment('ec.prototype.get_'+getter+'=function'), context);
function reward(raw) {
    const r = new Reward();
    r._reward = {getInt: key => Number(raw[key] || 0), getTypedObject: key => raw[key] ? {
        getString: field => raw[key][field], getInt: field => raw[key][field]
    } : null};
    return r;
}
const legacy = [...Array(7)].map(() => ({quest_energy:20}));
legacy.push({stat_points:2},{stat_points:20},{booster:{booster_id:'booster_quest3',days:14}});
const php = "require 'server-laravel/vendor/autoload.php'; echo json_encode(HeroZero\\Casino::clientRewards(json_decode(stream_get_contents(STDIN),true)));";
const wire = JSON.parse(cp.execFileSync('php',['-r',php], {
    cwd:path.resolve(__dirname,'../..'), input:JSON.stringify(legacy.map(JSON.stringify)),encoding:'utf8'
})).map(JSON.parse);
assert(wire.every(r => !('stat_points' in r)));
const ctor=assignment('eD.prototype._hx_constructor=function');
const start=ctor.indexOf('a=[];c=0;');
const end=ctor.indexOf('this._rewardArea=new sF',start);
vm.runInContext('function casinoEntries(){var a,c,e,m,d,D;'+ctor.slice(start,end)+'return a;}',context);
const oldEntries=context.casinoEntries.call({_rewards:legacy.map(reward)});
const entries=context.casinoEntries.call({_rewards:wire.map(reward)});
assert.strictEqual(oldEntries.length,8);
assert.strictEqual(entries.length,10);
assert.deepStrictEqual(Array.from(entries.filter(e=>e.type===5),e=>e.amount),[2,20]);
assert.strictEqual(entries.find(e=>e.type===8).days,14);
console.log('PASS: normalização PHP e parser/agregação reais do casino mostram dez prémios, incluindo habilidades 2/20 e energético 14 dias.');
