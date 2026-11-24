'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const src = fs.readFileSync(__dirname + '/../../server-laravel/public/js/HeroZero.min.js', 'utf8');
const start = src.indexOf('F.prototype.show=function(a)');
const end = src.indexOf('F.prototype.updateSidekickAPosition', start);
assert(start >= 0 && end > start);
const method = src.slice(start, end);
function run(code) {
  function Panel() {}
  let attackId = 1, battleId = 101, active = true, renders = 0, shows = 0;
  const stop = new Error('stop before graphics');
  const context = vm.createContext({
    F: Panel,
    Ja: {prototype: {show() { shows++; }}},
    Dn: {set_muted() { renders++; throw stop; }}
  });
  Panel.get_isActiveWorldbossAttack = () => active;
  Panel.get_activeWorldbossAttack = () => ({
    get_id: () => attackId,
    get_nextBattle: () => battleId === null ? null : {get_id: () => battleId}
  });
  vm.runInContext(code, context);
  const panel = new Panel();
  function show() { try { panel.show(); } catch (error) { if (error !== stop) throw error; } }
  show(); show();
  return {panel, show, get renders() {return renders;}, get shows() {return shows;},
    next(id) {battleId = id;}, attack(id) {attackId = id;}, normal() {active = false;}};
}
// Reproduzir o reinício anterior usando o mesmo método sem a proteção nova.
const oldMethod = method.replace(/if\(F\.get_isActiveWorldbossAttack\(\)\)\{var hzAttack=.*?else this\._worldbossVisualizationKey=null;/, '');
assert.notStrictEqual(oldMethod, method);
assert.strictEqual(run(oldMethod).renders, 2, 'antes: duas chamadas iniciavam duas renderizações');
const test = run(method);
assert.strictEqual(test.renders, 1, 'o mesmo ataque/combate só inicia uma renderização');
assert.strictEqual(test.shows, 2, 'a chamada ao painel base mantém-se');
test.panel._currentRoundIndex = 7;
test.show();
assert.strictEqual(test.panel._currentRoundIndex, 7, 'repetir show não reinicia os rounds');
test.next(102); test.show(); test.show();
test.next(103); test.show(); test.show();
assert.strictEqual(test.renders, 3, 'as três batalhas de um triplo continuam a iniciar uma vez cada');
test.attack(2); test.show();
assert.strictEqual(test.renders, 4, 'um novo ataque pode iniciar');
test.next(null); test.show();
assert.strictEqual(test.renders, 4, 'sem batalha pendente não reinicia a anterior');
test.normal(); test.show(); test.show();
assert.strictEqual(test.renders, 6, 'missões/duelos mantêm o percurso de renderização');
const resetStart = src.indexOf('F.prototype.reset=function()');
const resetEnd = src.indexOf('F.prototype.', resetStart + 20);
const resetCode = src.slice(resetStart, resetEnd);
function ResetPanel() {}
ResetPanel.resetBattleData = () => {};
vm.runInNewContext(resetCode, {F: ResetPanel});
const reset = new ResetPanel();
reset._worldbossVisualizationKey = '1:101';
reset._btnSkip = {set_visible() {}};
reset.reset();
assert.strictEqual(reset._worldbossVisualizationKey, null, 'reset permite voltar a abrir um combate');
console.log('PASS: reproduz reinício anterior; proteção contra chamadas repetidas, triplo, novo ataque, fim, reset e percurso de missões/duelos. Gráficos não executados.');
