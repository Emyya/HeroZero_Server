// Executa o merge do contador e a barra reais do bundle, sem browser.
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
function Lookup() { this.data = {quest_energy_spent: 0}; }
Lookup.prototype.update = function(response) { Object.assign(this.data, response); };
Lookup.prototype.getInt = function(key) { return this.data[key]; };
Lookup.prototype.get_rewardDataIsUpToDate = () => true;
Lookup.prototype.requestRewardData = () => { throw new Error('Não deve aguardar outro pedido para atualizar o contador'); };
function Character() { this.lookup = new Lookup(); }
Character.prototype.get_dailyBonusLookup = function() { return this.lookup; };
Character.prototype.get_hasTutorialFinished = () => true;
const hero = new Character();
const bar = {width: 0, tweenTo(duration, settings) { this.width = settings.width; }};
const label = {text: '', set_text(value) { this.text = value; }};
const mask = {progress: 0, set_progress(value) { this.progress = value; }};
const context = vm.createContext({Y: Character, Sy: Lookup, iG: function() {}, du: function() {},
    p: {parseInt, string: String}, q: {get_current: () => ({get_character: () => hero})},
    Sa: {get_dailyBonusQuestEnergyEnabled: () => true, get_dailyBonusDuelEnabled: () => true, get_dailyBonusLeagueEnabled: () => true, get_dailyBonusHideoutEnabled: () => true}, gp: {_isOpen: true}});
for (const method of ['Y.prototype.refreshDailyBonusLookup', 'Sy.prototype.get_questEnergySpent', 'Sy.prototype.get_duelBattleWon', 'Sy.prototype.get_leagueBattleWon', 'Sy.prototype.get_hideoutBattleWon',
    'iG.prototype.refresh', 'iG.prototype.get_currentValue', 'iG.prototype.get_maxValue',
    'du.prototype.progressBarRefreshed']) vm.runInContext(assignment(method + '=function'), context);
const progress = Object.create(context.iG.prototype);
const content = {get_visible: () => true};
Object.assign(progress, {
    _content: content, _dailyBonusLookupType: 5, _dailyBonusLookup: hero.lookup,
    _rewardData: {get_keys: () => ['100','200','300'], getTypedObject: () => ({})},
    _buttons: [], _dailyBonusRewardTooltip: null,
    get_fill: () => bar, get_background: () => ({get_width: () => 300})
});
const badge = Object.create(context.du.prototype);
Object.assign(badge, {_realContent: {txtProgress: label}, _mask: mask,
    _progressPopup: {get_progressBar: () => progress}});
progress._onRefresh = {dispatch: () => badge.progressBarRefreshed()};
// Cada resposta de missão atualiza o modelo, barra e legenda no refresh do painel.
for (const spent of [0,10,99,100,110,200,300]) {
    hero.refreshDailyBonusLookup({quest_energy_spent: spent});
    progress.refresh();
    assert(Math.abs(bar.width - spent) < 1e-9, 'Barra acompanha o contador');
    assert.strictEqual(label.text, spent + '/300');
    assert.strictEqual(mask.progress, spent / 300);
}
hero.refreshDailyBonusLookup({quest_energy_spent: 0});
progress.refresh();
assert.strictEqual(label.text, '0/300', 'Mudança de dia também atualiza a legenda');
console.log('PASS: funções reais do cliente atualizam barra, legenda e máscara com cada resposta, sem novo pedido.');

for (const [type, key, thresholds] of [[2, 'duel_battle_won', [10,20,50]], [3, 'league_battle_won', [5,10,20]], [4, 'hideout_battle_won', [5,10,20]]]) {
    progress._dailyBonusLookupType = type;
    progress._rewardData.get_keys = () => thresholds.map(String);
    const max = thresholds[2];
    for (const count of [0, thresholds[0]-1, ...thresholds, 0]) {
        hero.refreshDailyBonusLookup({[key]: count});
        progress.refresh();
        assert(Math.abs(bar.width - count / max * 300) < 1e-9);
        assert.strictEqual(label.text, count + '/' + max);
        assert.strictEqual(mask.progress, count / max);
    }
}
console.log('PASS: barras reais de duelos, liga e esconderijo acompanham patamares e reset diário.');
