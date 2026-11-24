'use strict';
// Executa os métodos reais do bundle, sem alterar o cliente nem precisar de navegador.
const fs = require('fs'), vm = require('vm'), zlib = require('zlib'), assert = require('assert');
const {execFileSync} = require('child_process');
const root = __dirname + '/../..';
const src = fs.readFileSync(root + '/server-laravel/public/js/HeroZero.min.js', 'utf8');
const constants = JSON.parse(zlib.inflateSync(fs.readFileSync(root + '/assets/data/constants_json.data')));
const levels = JSON.parse(fs.readFileSync(root + '/server-laravel/data/levels_xp.json'));
function method(anchor) {
  const start = src.indexOf(anchor);assert(start >= 0, anchor);
  const begin = src.indexOf('function', start), open = src.indexOf('{', begin);
  let depth=1, pos=open+1;
  // Estes métodos não contêm chavetas em strings ou comentários.
  while (depth) {if(src[pos]==='{')depth++;if(src[pos]==='}')depth--;pos++;}
  return vm.runInNewContext('('+src.slice(begin,pos)+')', context);
}
const context = {
  x: new Proxy({}, {get: (_, key) => () => constants[key.slice(4)]}),
  Fg: {fromId: level => ({get_xp: () => levels[level]})},
  ua: {roundDecimal: (value, digits) => Math.round(value*10**digits)/10**digits}, Q: {}
};
context.Q.gameCurrencyPerTime=method('Q.gameCurrencyPerTime=function');
const amount=method('Q.getEventQuestObjectiveRewardAmount=function');
const cases=[];
for(const level of [5,15,100,300,constants.max_level])for(const type of [1,2,3,4])for(const factor of [.3,.4,.5,.6,1,5]) {
  cases.push([type,factor,level,amount(type,factor,level)]);
}
const php = 'require "server-laravel/vendor/autoload.php"; $rows=json_decode($argv[1],true); foreach($rows as &$r)$r[]=HeroZero\\EventQuests::amount($r[0],$r[1],$r[2]); echo json_encode($rows);';
const actual=JSON.parse(execFileSync('php',['-r',php,JSON.stringify(cases)],{cwd:root,encoding:'utf8'}));
for(const row of actual)assert.strictEqual(row[4], row[3], 'fórmula PHP/JS: '+row.slice(0,3));
const collectable=method('il.prototype.get_isCollectable=function');
const collected=method('il.prototype.get_isCollected=function');
for(const value of [24,25,26]) {
  const o={get_currentValue:()=>value,get_value:()=>25};
  assert.strictEqual(collectable.call(o),value===25);assert.strictEqual(collected.call(o),value>25);
}
const unassigned=method('Ae.prototype.get_isUnassigned=function');
assert(unassigned.call({hasData:()=>false}));assert(!unassigned.call({hasData:()=>true}));
const selected=method('Ao.prototype.get_selectedRewardItemId=function');
const dialog={_eventQuest:{isCompleted:()=>true,get_rewardItem1Id:()=>101,get_rewardItem2Id:()=>102,get_rewardItem3Id:()=>-3},
  _selectableReward1:{get_selected:()=>false},_selectableReward2:{get_selected:()=>false},_selectableReward3:{get_selected:()=>true}};
assert.strictEqual(selected.call(dialog),-3,'opção numérica mantém seleção não zero');
const getType=method('yj.prototype.get_type=function');
assert.strictEqual(getType.call({get_identifier:()=> 'xmas_event_1_eventQuest'}),'eventQuest');
console.log('PASS: '+cases.length+' fórmulas reais PHP/JS, estados de objetivo, convite, seleção numérica e tipo do hub.');
