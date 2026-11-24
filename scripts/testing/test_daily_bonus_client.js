// Offline: executa as funções reais do bundle com datas simuladas, incluindo DST.
process.env.TZ = 'Europe/Lisbon';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(__dirname + '/../../server-laravel/public/js/HeroZero.min.js', 'utf8').replace(/=\s+function/g, '=function');
function assignment(prefix) {
    const start = source.indexOf(prefix);
    assert(start >= 0, 'Função ausente: ' + prefix);
    const body = source.indexOf('{', start);
    let depth = 1, end = body + 1;
    while (depth) { if (source[end] === '{') depth++; if (source[end] === '}') depth--; end++; }
    return source.slice(start, end) + ';';
}
const real = Date.parse('2026-10-04T10:00:00+01:00');
class FrozenDate extends Date {
    constructor(...args) { super(...(args.length ? args : [real])); }
    static now() { return real; }
}
const settings = {seen: false};
function DataObject(data) { this.data = data; }
DataObject.prototype.getInt = function(key) { return this.data[key]; };
DataObject.prototype.getNumber = DataObject.prototype.getInt;
function BonusInfo(data) { this.data = data; }
BonusInfo.prototype.hasData = function(key) { return this.data[key] != null; };
BonusInfo.prototype.getArray = function(key) { return this.data[key]; };
const context = vm.createContext({Date: FrozenDate, p: {parseInt, string: String},
    K: {_timestampCorrection: 0, _serverTimestampOffset: 0, get_now: () => real / 1000}, Sb: {_bonusData: null},
    J: DataObject, jga: BonusInfo, ka: {newInstance: (type,args) => Reflect.construct(type,args)},
    y: {__cast: value => value}, Fl: Boolean, ja: {},
    q: {get_current: () => ({getSettingValue: () => settings.seen})}});
// Tipos e conversão reais: get_bonusDatas devolve od, não um array JavaScript.
for (const prefix of ['var od=function', 'var QE=function', 'var v0=function']) {
    vm.runInContext(assignment(prefix), context);
}
vm.runInContext('Object.setPrototypeOf(v0.prototype, J.prototype)', context);
for (const name of ['od.prototype.sort','od.prototype.iterator','jga.prototype.get_bonusDatas',
    'v0.prototype.get_date','v0.prototype.get_type','v0.prototype.get_value',
    'Sb.reset','Sb.update','Sb.updateData','Sb.sortByDateType']) {
    vm.runInContext(assignment(name+'=function'), context);
}
vm.runInContext(assignment('var Oc=function(a)'), context);
for (const method of ['get_localTimestampOffset','get_timestampMilliseconds','set_timestamp','get_date','get_month','get_year','isToday']) {
    vm.runInContext(assignment('Oc.prototype.' + method + '=function'), context);
}
for (const name of ['Oc.get_utcLocalTimestampOffset','Oc.fromTimestamp','K.get_serverTimestampOffset','K.get_timestampCorrection','K.get_serverDateTime','Sb.get_needsDisplay']) {
    vm.runInContext(assignment(name + '=function'), context);
}
for (const date of ['2026-11-06T00:00:01+00:00','2026-07-02T00:00:01+01:00','2027-03-29T00:00:01+01:00']) {
    const timestamp = Date.parse(date);
    context.K._timestampCorrection = Math.round((timestamp - real) / 1000);
    context.K._serverTimestampOffset = -new Date(timestamp).getTimezoneOffset() * 60;
    const result = vm.runInContext('K.get_serverDateTime()', context);
    assert.strictEqual(result.get_date(), new Date(timestamp).getDate(), 'Dia do cliente: ' + date);
    assert.strictEqual(result._date.getHours(), 0, 'Meia-noite do cliente: ' + date);
    assert(result.isToday(Math.round(timestamp / 1000)), 'Reconhece hoje: ' + date);
}
assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context), false, 'Sem dados não abre janela');
vm.runInContext('Sb.update(new jga({bonus_datas:[]}))', context);
assert(vm.runInContext('Sb._bonusData instanceof od', context), 'Usa DataObjectArray real');
assert.strictEqual(vm.runInContext('Sb._bonusData.a.length', context), 0);
assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context), false, 'Sem evento não abre janela vazia');
vm.runInContext('Sb.get_settingsIdentifier=function(){return "bin_test"}; Sb.update(new jga({bonus_datas:[{date:Math.round((Date.now()+1E3*K._timestampCorrection)/1000),type:7,value:2}]}))', context);
assert.strictEqual(vm.runInContext('Sb._bonusValueQuestXp', context), 2, 'Evento activo aplica XP duplo');
assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context), true, 'Evento com dias pode abrir janela');
settings.seen = true;
assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context), false, 'Evento visto não reabre');
vm.runInContext('Sb.update(new jga({bonus_datas:[]}))', context);
settings.seen = false;
assert.strictEqual(vm.runInContext('Sb._bonusValueQuestXp', context), 0, 'Evento terminado limpa o multiplicador');
assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context), false, 'Evento terminado não abre janela vazia');
const start = Date.parse('2027-03-27T12:00:00+00:00') / 1000;
const next = Date.parse('2027-03-28T12:00:00+01:00') / 1000;
assert(source.includes('Math.round((D[0].get_date()-c)/86400)+1'));
assert.strictEqual(Math.round((next-start)/86400)+1, 2, 'Dia 2 num dia com 23 horas');
console.log('OK: funções reais do cliente — meia-noite, mudanças de hora, dia 2 e calendário vazio.');

if (process.argv[2]) {
    const har = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
    let checked = 0;
    for (const entry of har.log.entries) {
        const content = entry.response.content;
        let text = content.text || '';
        if (content.encoding === 'base64') text = Buffer.from(text, 'base64').toString('utf8');
        let response;
        try { response = JSON.parse(text); } catch { continue; }
        const bonus = response.data && response.data.bonus_info;
        if (!bonus) continue;
        context.harBonus = bonus;
        settings.seen = false;
        vm.runInContext('Sb.update(new jga(harBonus))', context);
        assert.strictEqual(vm.runInContext('Sb.get_needsDisplay()', context),
            Array.isArray(bonus.bonus_datas) && bonus.bonus_datas.length > 0, 'Calendário do HAR');
        checked++;
    }
    assert(checked > 0, 'HAR sem calendário de bónus');
    console.log('OK: ' + checked + ' calendários do HAR convertidos pelo tipo real do cliente.');
}
