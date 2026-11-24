// Funções reais do bundle; componentes gráficos simulados, sem browser/BD.
const fs=require('fs'), vm=require('vm'), assert=require('assert'), path=require('path');
const source=fs.readFileSync(path.resolve(__dirname,'../../server-laravel/public/js/HeroZero.min.js'),'utf8').replace(/=\s+function/g,'=function');
function assignment(prefix) {
 const start=source.indexOf(prefix);assert(start>=0,prefix);
 let end=source.indexOf('{',start),depth=1;
 while(depth) {end++;if(source[end]==='{')depth++;if(source[end]==='}')depth--;}
 return source.slice(start,end+1)+';';
}
let now=1000,pending=0,event;
const constants={casino_event_start_timestamp:1000,casino_event_end_timestamp:1000+3*86400};
function graphic() {return {set_visible(v){this.visible=v;}};}
function machine() {this.shows=0;this.hides=0;this.show=()=>this.shows++;this.hide=()=>this.hides++;}
const ctx=vm.createContext({YW:function(){},dn:function(){},uv:machine,nu:machine,ou:machine,
 vha:function(){},Xx:function(){},KT:function(){},Aj:function(){},
 K:{get_now:()=>now},T:{current:{getInt:key=>constants[key]??0}},
 q:{get_current:()=>({get_character:()=>({get_currentCasinoSpin:()=>pending})})},
 Tm:{get_currentEvent:()=>event},Tt:{_possibleRewardSections:['old']},k:()=>()=>{}});
for(const method of ['YW.prototype.get_isAvailable','YW.prototype.get_endTimestamp','YW.prototype.get_remainingSeconds','YW.prototype.get_baseIdentifier',
 'dn.prototype.syncCasinoMachine','vha.prototype.setSymbol','Xx.prototype.setSymbol','Aj.prototype.handleRequests']) vm.runInContext(assignment(method+'=function'),ctx);
const ev=new ctx.YW();ev.get_identifier=()=>'casino_99_go_crazy';ev.get_endTimestamp=ctx.YW.prototype.get_endTimestamp;
assert.strictEqual(ev.get_baseIdentifier(),'go_crazy');
now=999;assert.strictEqual(ev.get_isAvailable(),false);
now=1000;assert.strictEqual(ev.get_isAvailable(),true);assert.strictEqual(ev.get_remainingSeconds(),3*86400);
now=constants.casino_event_end_timestamp;assert.strictEqual(ev.get_isAvailable(),false);assert.strictEqual(ev.get_remainingSeconds(),0);
now+=30*86400;assert.strictEqual(ev.get_isAvailable(),false);
pending=1;assert.strictEqual(ev.get_isAvailable(),true);pending=0;
constants.casino_event_start_timestamp=0;constants.casino_event_end_timestamp=0;assert.strictEqual(ev.get_isAvailable(),false);
const panel={_vo:{slotmachine:graphic(),luckyWheel:graphic(),slotmachineSpine:graphic()},onClickSpin(){}};
for(const [type,id] of [[1,'casino_1_default'],[2,'casino_2_go_crazy'],[3,'casino_3_christmas'],[1,'casino_4_only_items']]) {
 event={get_machineType:()=>type,get_identifier:()=>id};ctx.dn.prototype.syncCasinoMachine.call(panel);
 assert.strictEqual(panel._casinoType,type);assert.strictEqual(panel._casinoIdentifier,id);
 assert.strictEqual(panel._vo.slotmachine.visible,type===1);assert.strictEqual(panel._vo.luckyWheel.visible,type===2);assert.strictEqual(panel._vo.slotmachineSpine.visible,type===3);
 const shows=panel._casinoMachine.shows;ctx.dn.prototype.syncCasinoMachine.call(panel);assert.strictEqual(panel._casinoMachine.shows,shows,'refresh idempotente');
}
const icons=['coins','item','booster','sidekick','statPoints','xp','energy','training','slotmachine_hideout_glue','slotmachine_hideout_stone','slotmachine_optical_change','slotmachine_item_improvement',
 'slotmachine_event_item','slotmachine_story_dungeon_item','slotmachine_herobook_item','slotmachine_guild_competition_item','slotmachine_surprisebox','slotmachine_season_points'];
for(let type=1;type<=12;type++) {
 const rewardIcon=Object.fromEntries(icons.map(key=>[key,graphic()]));const slice={_content:{rewardIcon}};ctx.vha.prototype.setSymbol.call(slice,type);
 assert.strictEqual(rewardIcon[icons[type-1]].visible,true);assert.strictEqual(Object.values(rewardIcon).filter(x=>x.visible).length,1);
 const reel={...Object.fromEntries(icons.map(key=>[key,graphic()])),base:{gotoAndStop(){}}};ctx.Xx.prototype.setSymbol.call({_frame:1},reel,type);
 assert.strictEqual(reel[icons[type-1]].visible,true);
}
const cache=source.match(/c\.hasData\("casino_identifier",!0\)&&\(\(.*?\)\);/);assert(cache,'invalidação de catálogo');
ctx.c={hasData:()=>true,get_casino_identifier:()=>'casino_2_go_crazy'};ctx.Tm.currentIdentifier='casino_1_default';ctx.Tm._currentEvent={old:true};
vm.runInContext(cache[0],ctx);assert.strictEqual(ctx.Tt._possibleRewardSections,null);assert.strictEqual(ctx.Tm._currentEvent,null);
ctx.Aj.prototype.handleRequests.call({_timer:{stop(){throw Error('Polling desligado');}}},{action:'addUserToCasinoRoom',error:''});
assert(source.includes('wa.SIDEKICK_PATTERN.match(this.get_value1())'),'mensagens de pets reconhecidas');
ctx.Y=function(){};
for(const method of ['Y.prototype.get_casinoSpinCountLeftToday','Y.prototype.get_hasDailyCasinoSpinLimitReached','dn.prototype.refreshMultispinToggle']) vm.runInContext(assignment(method+'=function'),ctx);
ctx.x={get_casino_max_daily_spins:()=>0,get_resource_free_casino_spin_usage_amount:()=>1,
 get_resource_casino_jeton_usage_amount:()=>10,get_casino_multi_spin_amount_5:()=>5,
 get_casino_multi_spin_amount_10:()=>10,get_casino_premium_currency_amount:()=>1};
const hero=new ctx.Y();hero.get_casinoSpinCount=()=>100014;hero.getUnusedResourceCount=()=>0;
ctx.q.get_current=()=>({get_character:()=>hero,get_premiumCurrency:()=>100});
assert.strictEqual(hero.get_casinoSpinCountLeftToday(),Number.MAX_SAFE_INTEGER);
assert.strictEqual(hero.get_hasDailyCasinoSpinLimitReached(),false);
const toggle={visible:false,set_visible(v){this.visible=v;},get_visible(){return this.visible;},
 set_multiSpin10Allowed(v){this.ten=v;},get_spinAmount:()=>10};
ctx.dn.prototype.refreshMultispinToggle.call({_btnMultiSpinOnOff:toggle});
assert.strictEqual(toggle.visible,true);assert.strictEqual(toggle.ten,true,'cinco/dez giros disponíveis acima de 99999');
console.log('PASS: calendário/pedidos pendentes, troca entre três máquinas, 12 símbolos, cache por evento e polling do histórico.');
console.log('PASS: casino ilimitado mantém os lotes de cinco/dez acima de 99999 giros.');
