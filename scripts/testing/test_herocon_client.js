// Offline: compara as funções reais do cliente com o cálculo PHP de HeroCon.
const fs = require('fs'), path = require('path'), vm = require('vm'), assert = require('assert');
const {execFileSync} = require('child_process');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'server-laravel/public/js/HeroZero.min.js'), 'utf8').replace(/=\s+function/g, '=function');
const backend = JSON.parse(execFileSync('php', ['-r',
    "require $argv[1]; $out=['constants'=>HeroZero\\HeroCon::constants(),'solo'=>[],'teams'=>[]]; foreach(range(1,301) as $r)$out['solo'][]=HeroZero\\HeroCon::soloStats($r); foreach([1,4,100] as $n)foreach(range(1,100) as $r)$out['teams'][]=['rank'=>$r,'guilds'=>$n]+HeroZero\\HeroCon::teamReward($r,$n); echo json_encode($out);",
    path.join(root, 'server-laravel/vendor/autoload.php')], {encoding: 'utf8'}));
function assignment(name) {
    const prefix = name + '=function', start = source.indexOf(prefix); assert(start >= 0, name);
    let end = source.indexOf('{', start), depth = 1;
    while (depth) {end++; if (source[end] === '{') depth++; if (source[end] === '}') depth--;}
    return source.slice(start, end + 1) + ';';
}
const context = vm.createContext({lt: function(){}, jy: function(){},
    x: new Proxy({}, {get: (_, name) => () => backend.constants[name.slice(4)]})});
for (const name of ['lt.prototype.getGuildRewardForRank', 'lt.prototype.getStatPointsRewardForRank', 'jy.prototype.getStatPointsRewardForRank'])
    vm.runInContext(assignment(name), context);
for (const r of backend.teams) {
    assert.strictEqual(context.lt.prototype.getGuildRewardForRank(r.rank, r.guilds), r.booster);
    assert.strictEqual(context.lt.prototype.getStatPointsRewardForRank(r.rank, r.guilds), r.statPoints);
}
for (const [i, amount] of backend.solo.entries())assert.strictEqual(context.jy.prototype.getStatPointsRewardForRank(i+1), amount);
console.log('PASS cliente: 300 combinações de prémios de equipa e 301 posições individuais coincidem com o PHP.');
