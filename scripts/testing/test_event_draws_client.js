'use strict';
// Métodos reais do bundle, sem navegador ou alterações à BD.
const fs=require('fs'),vm=require('vm'),zlib=require('zlib'),assert=require('assert');
const {execFileSync}=require('child_process');
const root=__dirname+'/../..',src=fs.readFileSync(root+'/server-laravel/public/js/HeroZero.min.js','utf8');
const constants=JSON.parse(zlib.inflateSync(fs.readFileSync(root+'/assets/data/constants_json.data')));
class Data {
 constructor(raw={}){this.raw=raw;}
 getInt(k){return Number(this.raw[k]||0);}
 getBoolean(k){return !!this.raw[k];}
 getString(k){return String(this.raw[k]??'');}
 hasData(k){return Object.hasOwn(this.raw,k);}
 get_rawData(){return this.raw;}
 update(a){Object.assign(this.raw,a.get_rawData());}
}
const context={QA:Data,ec:class{constructor(s){this.raw=JSON.parse(s);}},po:{fromId:id=>({
 get_eventDrawPoolRewardIds:()=>constants.event_draw_pools[id].reward_index.map((_,i)=>i),
 getEventDrawPoolReward:i=>({get_chance:()=>constants.event_draw_pools[id].reward_index[i].chance,get_costPremiumCurrency:()=>constants.event_draw_pools[id].reward_index[i].cost_premium_currency})
})}};
function method(anchor){const start=src.indexOf(anchor);assert(start>=0,anchor);const begin=src.indexOf('function',start),open=src.indexOf('{',begin);let pos=open+1,depth=1;while(depth){if(src[pos]==='{')depth++;if(src[pos]==='}')depth--;pos++;}return vm.runInNewContext('('+src.slice(begin,pos)+')',context);}
class Draw extends Data{constructor(raw){super(raw);this._changes=[];this._isComplete=false;this._currentCost=0;}}
for(const key of ['get_id','get_identifier','get_status'])Draw.prototype[key]=method('QA.prototype.'+key+'=');
for(const key of ['refresh','get_isUnassigned','get_hasCollectableRewards','isRewardDrawn','isRewardCollectable','isRewardCollected','getReward','getRewardChance','get_isComplete','get_currentCost','get_isStarted','get_isFinished','get_isAborted','get_isAbortedRewardsCollected'])Draw.prototype[key]=method('mm.prototype.'+key+'=');
const rows=[];
for(const id of Object.keys(constants.event_draw_pools)) {
 const raw={id:10,identifier:id,status:1},draw=new Draw(raw),drawn={};
 for(let i=0;i<15;i++){raw['ts_reward'+i+'_drawn']=0;raw['reward'+i+'_collected']=false;raw['reward'+i]='{}';}
 for(let n=0;n<=15;n++) {
  draw.refresh();const chances=Array.from({length:15},(_,i)=>draw.getRewardChance(i));
  rows.push({id,drawn:{...drawn},cost:draw.get_currentCost(),chances});
  assert.strictEqual(draw.get_isComplete(),n===15);
  if(n<15){const i=14-n;raw['ts_reward'+i+'_drawn']=100;drawn[i]=100;assert(draw.isRewardCollectable(i));raw['reward'+i+'_collected']=true;assert(!draw.isRewardCollectable(i));}
 }
}
const php='require "server-laravel/vendor/autoload.php"; $rows=json_decode(stream_get_contents(STDIN),true);foreach($rows as &$r){$r["php_cost"]=HeroZero\\EventDraws::cost($r["id"],$r["drawn"]);$r["php_chances"]=HeroZero\\EventDraws::chances($r["id"],$r["drawn"]);}echo json_encode($rows);';
const actual=JSON.parse(execFileSync('php',['-r',php],{cwd:root,input:JSON.stringify(rows),encoding:'utf8',maxBuffer:4*1024*1024}));
for(const r of actual){assert.strictEqual(r.php_cost,r.cost,r.id);for(let i=0;i<15;i++)assert(Math.abs(r.php_chances[i]-r.chances[i])<1e-12,r.id+' chance '+i);}
const assigned=new Draw({id:11,identifier:'beetle_draw_event',status:3,ts_reward0_drawn:100,reward0_collected:false,reward0:'{"item":99}'});
assert(assigned.get_hasCollectableRewards());assert.strictEqual(assigned.getReward(0).raw.item,99);assert(assigned.get_isAborted());assigned.raw.reward0_collected=true;assigned.raw.status=4;assert(!assigned.get_hasCollectableRewards());assert(assigned.get_isAbortedRewardsCollected());
const invite=new Draw({identifier:'beetle_draw_event'});assert(invite.get_isUnassigned());
assert.strictEqual(method('yj.prototype.get_type=function').call({get_identifier:()=> 'beetle_draw_event_draw'}),'draw');
console.log('PASS JS real: '+rows.length+' estados PHP/JS dos 55 temas, custos/pesos, pendências/resgates, parsing de prémios, convite e hub.');
