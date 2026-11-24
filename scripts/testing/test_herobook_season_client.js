// Offline: funções reais do cliente, constantes servidas pelo backend e cinco cartões.
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const path = require('path'), {execFileSync} = require('child_process');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'server-laravel/public/js/HeroZero.min.js'), 'utf8')
    .replace(/=\s+function/g, '=function');
const locations = JSON.parse(execFileSync('php', ['-r',
    "require $argv[1]; echo json_encode(HeroZero\\Herobook::constants()['season_locations']);",
    path.join(root, 'server-laravel/vendor/autoload.php')], {encoding: 'utf8'}));
function assignment(prefix) {
    const start = source.indexOf(prefix);
    assert(start >= 0, prefix);
    let end = source.indexOf('{', start), depth = 1;
    while (depth) { end++; if (source[end] === '{') depth++; if (source[end] === '}') depth--; }
    return source.slice(start, end + 1) + ';';
}
function element() {
    return new Proxy({}, {get: (o, key) => {
        if (key in o) return o[key];
        if (key.startsWith('set_')) return value => {o[key.slice(4)] = value;};
        if (key.startsWith('get_')) return () => o[key.slice(4)] ?? 30;
        if (['setTexture', 'applySettings', 'hide', 'refresh'].includes(key)) return () => {};
        return o[key] = element();
    }});
}
function icon() {
    return {set_rewardEntry(value) {this.entry = value;}, get_rewardEntry() {return this.entry;}};
}
function Entry(type, amount, data) {Object.assign(this, {type, amount, data});}
Entry.prototype.get_rewardAmountText = function () {return String(this.amount);};
Entry.prototype.get_type = function () {return this.type;};
const season = {isActive: () => season.active, active: true, get_identifier: () => season.identifier};
const context = vm.createContext({
    ea: Entry, Ze: function () {}, eq: function () {}, d: function (data) {Object.assign(this, data);},
    Yb: {exists: id => !!locations[id], fromId: id => ({getInt: name => locations[id][name] ?? 0})},
    q: {get_current: () => ({get_character: () => ({get_seasonProgress: () => season})})},
    Qj: {fromId: () => ({get_color: () => 0})}, n: {info: {get_isTouchScreen: () => false}},
    x: {get_herobook_daily_objective_instant_finish_amount: () => 9,
        get_herobook_weekly_objective_instant_finish_amount: () => 19},
    f: {current: {text: () => ''}}
});
for (const name of ['Ze.seasonHasSeasonPointLocation', 'Ze.prototype.hasSeasonPointLocation',
    'Ze.prototype.getSeasonPointLocationValue', 'ea.fromSeasonPoints', 'eq.prototype.refresh']) {
    vm.runInContext(assignment(name + '=function'), context);
}
season.hasSeasonPointLocation = context.Ze.prototype.hasSeasonPointLocation;
season.getSeasonPointLocationValue = context.Ze.prototype.getSeasonPointLocationValue;
const card = {_content: element(), _timer: element(), _btnCollect: element(), _btnRight: element(),
    _btnInstantFinish: element(), _btnRenew: element(), _seasonPointRewardIcon: icon(),
    _rewardIcon: icon(), changeVisibleityOfAllElements: () => {}};
function objective(duration) {
    return new Proxy({}, {get: (_, key) => {
        if (key === 'get_durationType') return () => duration;
        if (key === 'get_remainingSeconds') return () => 1000;
        if (key === 'get_isStarted') return () => true;
        if (key === 'get_reward') return () => ({get_genericRewards: () => [new Entry(0, 100)]});
        return () => 0;
    }});
}
for (const identifier of Object.keys(locations[14])) {
    season.identifier = identifier;
    for (const [index, duration] of [1, 1, 1, 2, 2].entries()) {
        context.eq.prototype.refresh.call(card, objective(duration), index, duration === 2);
        assert.strictEqual(card._content.seasonPointReward.visible, true, identifier + ': ícone visível');
        assert.strictEqual(card._seasonPointRewardIcon.entry.amount, duration === 1 ? 1 : 5);
        assert.strictEqual(card._content.seasonPointReward.txtRewardAmount.text, duration === 1 ? '1' : '5');
    }
}
season.active = false;
context.eq.prototype.refresh.call(card, objective(1), 0, false);
assert.strictEqual(card._content.seasonPointReward.visible, false, 'passe inactivo esconde pontos');
console.log('PASS: cinco cartões reais mostram 1/5 pontos nos 80 temas; passe inactivo esconde o ícone.');
