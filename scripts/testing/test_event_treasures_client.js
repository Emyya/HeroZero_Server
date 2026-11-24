'use strict';
// Contrato real do cliente, sem renderização de navegador.
const fs=require('fs'), vm=require('vm'), zlib=require('zlib'), assert=require('assert');
const {execFileSync}=require('child_process');
const root=__dirname+'/../..', src=fs.readFileSync(root+'/server-laravel/public/js/HeroZero.min.js','utf8');
const constants=JSON.parse(zlib.inflateSync(fs.readFileSync(root+'/assets/data/constants_json.data')));
class Data {
  constructor(raw={}){this.raw=raw;}
  getInt(k,f=0){return this.raw[k]===undefined?f:Number(this.raw[k]);}
  getString(k){return this.raw[k]===undefined?'':String(this.raw[k]);}
  getBoolean(k,f=false){return this.raw[k]===undefined?f:!!this.raw[k];}
  hasData(k){return Object.hasOwn(this.raw,k);}
  getTypedObject(k){return new Data(this.raw[k]||{});}
  get_rawData(){return this.raw;}
  get_keys(){return Object.keys(this.raw);}
}
const context={x:new Proxy({},{get:(_,k)=>()=>constants[k.slice(4)]}),Q:{},K:{get_now:()=>1000},p:{parseInt:parseInt},
  d:{fromJsonString:s=>new Data(JSON.parse(s))},qe:class{constructor(){this.h={};}}, Xb:class{constructor(){this.h={};}},
  Lq:class extends Data{}, ec:class{constructor(s){this.raw=JSON.parse(s);}},
  Dn:{trigger:()=>{}},kk:{EVENT_TREASURE_REWARD_COLLECTABLE:1}};
context.Lq.prototype.update=function(a){this.raw=a.get_rawData();};
context.Lq.prototype.get_currentLevel=function(){return this.getInt('current_level');};
for(const [name,key] of [['rewards','rewards'],['collectedRewards','collected_rewards'],['levels','levels']])context.Lq.prototype['get_'+name]=function(){return this.getString(key);};
function method(anchor){const start=src.indexOf(anchor);assert(start>=0,anchor);const begin=src.indexOf('function',start),open=src.indexOf('{',begin);let depth=1,pos=open+1;while(depth){if(src[pos]==='{')depth++;if(src[pos]==='}')depth--;pos++;}return vm.runInNewContext('('+src.slice(begin,pos)+')',context);}
const skip=method('Q.getQuestInstantFinishCost=function');
const cases=[1,449,450,749,750,1049,1050,1800].map(s=>[s,skip(s)]);
const php='require "server-laravel/vendor/autoload.php"; $r=json_decode($argv[1],true);foreach($r as &$v)$v[]=HeroZero\\EventTreasures::skipCost($v[0]);echo json_encode($r);';
for(const r of JSON.parse(execFileSync('php',['-r',php,JSON.stringify(cases)],{cwd:root,encoding:'utf8'})))assert.strictEqual(r[1],r[2]);
const cellUpdate=method('cX.prototype.update=function');
context.cX=class{constructor(a){cellUpdate.call(this,a);}};
for(const key of ['type','neighbours','x','y','reward','collected'])context.cX.prototype['get_'+key]=method('cX.prototype.get_'+key+'=');
const levelUpdate=method('NH.prototype.update=function');context.NH=class{constructor(a){this._cells=null;levelUpdate.call(this,a);}};
for(const key of ['createCells','getCell','get_found','get_needed','get_boniFound','get_boniAmount','get_tsUnlock','get_openedCells'])context.NH.prototype[key]=method('NH.prototype.'+key+'=');
const level=new context.NH(new Data({info:{found:1,needed:3,boni_found:1,boni_amount:1,ts_unlock:0},cells:{'0_0':{x:0,y:0,type:1},'1_1':{x:1,y:1,type:2,collected:false,reward:JSON.stringify({quest_energy:25})},'2_2':{x:2,y:2,type:3,neighbours:2}}}));
assert.strictEqual(level.getCell(0,1),null);assert.strictEqual(level.getCell(0,0).get_type(),1);assert.strictEqual(level.getCell(1,1).get_reward().raw.quest_energy,25);assert.strictEqual(level.getCell(2,2).get_neighbours(),2);assert.strictEqual(level.get_found(),1);
const update=method('Um.prototype.update=function');
class Treasure extends context.Lq {}
for(const key of ['getReward','getLevel','get_isUnassigned','isRewardCollected','get_secondsUntilFreeRevealItems'])Treasure.prototype[key]=method('Um.prototype.'+key+'=');
const t=new Treasure({current_level:1,rewards:'{"0":"{\\"item\\":42}"}',collected_rewards:'{}',levels:'{}'});
t._levels=new context.qe();t._levels.h[1]=level;t._levelDatas=new Data({1:{}});t._rewards=[new context.ec('{"item":42}')];t._collectedRewards=new Data({});t._onUpdate={dispatch:()=>{}};
update.call(t,new Data({id:9,current_level:0,rewards:'{}',collected_rewards:'{}',levels:'{}'}));
assert.strictEqual(t.getLevel(1),null,'Reset limpa quintais antigos no cliente');assert.strictEqual(t.getReward(0),null,'Reset limpa recompensas');
update.call(t,new Data({id:9,current_level:1,rewards:'{"0":"{\\"item\\":55}"}',collected_rewards:'{"0":true}',levels:JSON.stringify({1:{info:{found:0,needed:3},cells:{}}})}));
assert.strictEqual(t.getLevel(1).getCell(0,0),null,'Nova ronda começa fechada');assert.strictEqual(t.getReward(0).raw.item,55);assert(t.isRewardCollected(0));
assert.strictEqual(method('yj.prototype.get_type=function').call({get_identifier:()=> 'revenge_treasure_treasure'}),'treasure');
console.log('PASS JS real: custos PHP/JS, células/pistas/recompensas, parsing do estado, reset dos caches e tipo do hub.');
