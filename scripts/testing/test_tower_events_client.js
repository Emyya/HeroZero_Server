'use strict';
// Executa os métodos reais do bundle com modelos de UI mínimos, sem navegador.
const fs=require('fs'),vm=require('vm'),assert=require('assert'),zlib=require('zlib');
const {execFileSync}=require('child_process'),root=__dirname+'/../..';
const src=fs.readFileSync(root+'/server-laravel/public/js/HeroZero.min.js','utf8');
const constants=JSON.parse(zlib.inflateSync(fs.readFileSync(root+'/assets/data/constants_json.data')));
class Data {
  constructor(raw={}){this.raw=raw instanceof Data?raw.raw:raw;}
  hasData(k){return Object.hasOwn(this.raw,k);}
  getTypedObject(k){return new Data(this.raw[k]);}
  get_rawData(){return this.raw;}
  get_asArray(){return Object.values(this.raw||{}).map(x=>new Data(x));}
  getData(k){return this.raw[k];}
  getNumber(k){return Number(this.raw[k]||0);}
  getInt(k){return this.getNumber(k);}
  getString(k){return String(this.raw[k]||'');}
  update(d){Object.assign(this.raw,d.raw);}
  set(k,v){this.raw[k]=v;}
}
const methods={};
function method(anchor,context){const start=src.indexOf(anchor);assert(start>=0,anchor);const begin=src.indexOf('function',start),open=src.indexOf('{',begin);let depth=1,pos=open+1;while(depth){if(src[pos]==='{')depth++;if(src[pos]==='}')depth--;pos++;}return vm.runInNewContext('('+src.slice(begin,pos)+')',context);}
// Contrato de evento real: elegibilidade, progresso e resgate.
// O refresh parcial não deve ler um occurrence_id ausente.
let resets=0;function MockRa(a){this.data=a;}MockRa.reset=()=>{resets++;};MockRa.get_current=()=>null;
const refreshContext={ra:MockRa,Data};
const refresh=method('Y.prototype.refreshTowerEventData=',refreshContext);
const oldEvent=new Data({}),newEvent=new Data({});
refresh.call({_towerEventData:oldEvent},newEvent);assert.strictEqual(resets,0,'refresh parcial sem occurrence_id');
refresh.call({_towerEventData:new Data({occurrence_id:1})},new Data({occurrence_id:2}));assert.strictEqual(resets,1,'evento novo limpa a instância antiga');
const basic={};
const started=method('Fo.prototype.get_isStarted=function',basic);
assert.strictEqual(started.call({get_isUnassigned:()=>false,get_isExpired:()=>false}),true);
const complete=method('Hx.prototype.get_isObjectiveCompleted=function',basic);
assert(complete.call({get_event:()=>({get_currentTokenItemAmount:()=>150}),get_requiredTokenAmount:()=>150}));
const objective=method('Lo.prototype.check=function',basic);
let completeFlag=false;
const objectiveModel={_tower:null,get_isCompleted:()=>false,get_totalLevelRequired:()=>1,get_floorIndexRequired:()=>2,get_type:()=>2,get_levelRequired:()=>50};
const realTower={get_currentUnlockedFloor:()=>({get_index:()=>2}),get_totalLevel:()=>100,getFloor:()=>({get_level:()=>49})};objectiveModel._tower=realTower;objective.call(objectiveModel,realTower);assert(!objectiveModel._isCompleted);realTower.getFloor=()=>({get_level:()=>50});objective.call(objectiveModel,realTower);assert(objectiveModel._isCompleted);
const shape=JSON.parse(execFileSync('php',['-r','require "server-laravel/vendor/autoload.php"; $p=HeroZero\\TowerEvents::fresh("earth_towerevent",1000); echo json_encode(HeroZero\\TowerEvents::clientState("earth_towerevent",["id"=>1,"enabled"=>1,"ts_end"=>9999],$p));'],{cwd:root,encoding:'utf8'}));
assert.strictEqual(shape.towers[0].floors.length,30);assert.strictEqual(shape.events.events_data.earth_towerevent.objectives['25'].index,25);
assert(Array.isArray(shape.inventory.tower_items));assert.strictEqual(shape.areas[0].id,1);
// Estado real do cliente: início zero fecha a região apesar de ts_unlocked.
const regionState=method('ij.prototype.get_state=',{});
function regionModel(raw){const definition=constants.tower_event_regions[raw.index];return {
 get_unlockStartTime:()=>raw.ts_unlock_started,get_unlockEndTime:()=>raw.ts_unlock_end,
 get_unlockTimer:()=>({get_remainingSeconds:()=>Math.max(0,raw.ts_unlock_started+definition.unlock_time-1000),get_isCompleted:()=>raw.ts_unlock_started+definition.unlock_time<=1000})
};}
const initialRegion=shape.towers[0].regions[0];
assert.strictEqual(regionState.call(regionModel({...initialRegion,ts_unlock_started:0})),1,'snapshot antigo esconde botão');
assert.strictEqual(regionState.call(regionModel(initialRegion)),4,'primeira região aberta para o cliente');
// A UI real tem de mostrar o botão, preenchê-lo com 8 e permitir a compra.
function visible(){return {value:false,set_visible(v){this.value=v;},get_visible(){return this.value;}};}
const lockedContent={...visible(),btnUnlockGameCurrency:visible(),btnUnlockPremiumCurrency:visible(),tower_icon_new_floor:visible()};
const floorUi={_content:lockedContent,_btnUnlockPremiumCurrency:{set_reactivePrice(){}},_btnUnlockGameCurrency:{set_price(v){this.price=v;},set_priceEnable(v){this.enabled=v&&shape.areas[0].bank_amount>=this.price;}}};
const firstFloor={get_isLocked:()=>true,get_isNewRegion:()=>true,get_region:()=>({get_isUnlocked:()=>regionState.call(regionModel(initialRegion))===4}),get_isNext:()=>true,get_index:()=>1,get_unlockCost:()=>constants.tower_event_floor_levels[1].levels[1].cost,get_unlockCostPremium:()=>0};
method('e3.prototype.refresh=',{}).call(floorUi,firstFloor);
assert(lockedContent.value&&lockedContent.btnUnlockGameCurrency.value&&lockedContent.tower_icon_new_floor.value,'UI mostra construção');
assert.strictEqual(floorUi._btnUnlockGameCurrency.price,8);assert(floorUi._btnUnlockGameCurrency.enabled,'saldo inicial permite compra');
assert(!lockedContent.btnUnlockPremiumCurrency.value,'primeira sala não exige donuts');
let clicked=false,executed=false;
const clickAction=function(floor,premium){assert.strictEqual(floor,firstFloor);assert.strictEqual(premium,false);this.get_onSuccess=()=>({add:()=>{clicked=true;}});};
method('e3.prototype.handleUnlockClickGameCurrencyClick=',{sX:clickAction,vh:{execute:()=>{executed=true;}},n:{audio:{} }}).call(floorUi,{});
assert(clicked&&executed,'clique chega à ação de construção');
class Manager extends Data {
 get_id(){return this.raw.id;}get_assignedTarget(){return this.target||null;}set_assignedTarget(t){this.target=t;}get_type(){return this.raw.type||1;}
}
class Pool extends Data {
 constructor(){super({});this.managers=[];}
 update(data){this.managers=data.raw.managers.map(raw=>{let m=this.managers.find(m=>m.get_id()===raw.id)||new Manager();m.update(new Data(raw));return m;});}
 get_managers(){return this.managers;}
}
class Target extends Data {
 constructor(){super({});this.pool=new RealPool();}
 get_managerPool(){return this.pool;}get_index(){return this.raw.index;}
 get_manager(){return this.pool.managers.find(m=>m.get_id()===this.raw.manager_id)||null;}
}
// Reproduzir o listener real do pool: snapshots HTTP contêm JSON simples.
class Signal {add(){} remove(){}}
function PoolBase(){}
PoolBase.prototype._hx_constructor=function(data){this.raw=data.raw;this.listeners={};};
const poolCtor=method('rk.prototype._hx_constructor=',{Ib:PoolBase,Ka:Signal});
const syncVector=method('Ib.prototype.syncVectorFromArray=',{ka:{newInstance:(_type,args)=>new RealManager(args[0])},ia:{remove:(a,m)=>{a.splice(a.indexOf(m),1);}}});
const syncManagers=method('rk.prototype.syncManagers=',{ge:function(){},Kga:{},k:(self,fn)=>fn.bind(self)});
class RealManager extends Manager {get_onAssignedAndReady(){return this.signal||(this.signal=new Signal());}}
class RealPool extends Data {
 constructor(){super();this._onAnyManagerAssignedAndReady=new Signal();poolCtor.call(this,{},new Data({manager_buy_index:1,managers:[]}));}
 onValueUpdated(key,fn){this.listeners[key]=fn;}
 syncManagers(items){syncManagers.call(this,items);}
 syncVectorFromArray(...args){return syncVector.call(this,...args);}
 handleManagerAssignedAndReady(){}
 update(data){const previous=this.raw.managers;Object.assign(this.raw,data.raw);if(Object.hasOwn(data.raw,'managers'))this.listeners.managers(previous,data.raw.managers);}
 get_managers(){return this._managers;}
 get managers(){return this._managers;}
}
for(const kind of ['floor','elevator','vault']) {
 const pool=new RealPool();const first={id:1,name:'Gestor 1',effect_id:2,rarity:1,ts_last_used:0,buy_cost:5};
 pool.update(new Data({manager_buy_index:2,managers:[first]}));
 assert.strictEqual(pool.get_managers().length,1,kind+' contratação JSON sem hasData error');
 const same=pool.get_managers()[0];
 pool.update(new Data({manager_buy_index:3,managers:[{...first,ts_last_used:1000},{...first,id:2}]}));
 assert.strictEqual(pool.get_managers()[0],same,kind+' preserva identidade');assert.strictEqual(same.raw.ts_last_used,1000);assert.strictEqual(pool.get_managers().length,2);
 pool.update(new Data({manager_buy_index:3,managers:[{...first,id:2}]}));assert.strictEqual(pool.get_managers().length,1,kind+' remoção');
 pool.update(new Data({manager_buy_index:3,managers:[]}));assert.strictEqual(pool.get_managers().length,0,kind+' pool vazio');
}
const floorPool=new RealPool(),floors=Array.from({length:30},()=>new Target()),elevator=new Target(),vault=new Target(),regions=Array.from({length:6},()=>new Data());
const tower=new Data();Object.assign(tower,{get_floorManagerPool:()=>floorPool,get_elevator:()=>elevator,get_vault:()=>vault,getFloor:i=>floors[i-1],getRegionByFloor:i=>regions[Math.floor((i-1)/5)]});
let bank=0,requests=[],response,callback=false;
const game={get_towers:()=>({getTower:()=>tower,get_activeTowerId:()=>206}),get_economy:()=>({get_area:()=>({get_bankAmount:()=>({set_value:n=>{bank=n;}})}),get_boosts:()=>({set:()=>{}})})};
const context={d:Data,ra:{get_current:()=>game},Lk:{fromId:i=>({get_fromIndex:()=>constants.tower_event_regions[i].from_index})},Sg:{updateEconomyId:()=>{}},gz:{},Dw:{},q:{},A:{},
 Rq:{prototype:{addBankAmount:function(amount){bank+=amount;}}},
 Re:{sendActionRequest:(action,args,ok)=>{requests.push({action,args});ok({get_typedData:()=>new Data(response)});}}};
const begin=src.indexOf('/* HZ_TOWER_SERVER_BRIDGE_BEGIN'),end=src.indexOf('/* HZ_TOWER_SERVER_BRIDGE_END */');vm.createContext(context);vm.runInContext(src.slice(begin,end),context);
let raw=structuredClone(shape.towers[0]);raw.floors[0].level=3;raw.floors[0].manager_id=1;raw.manager_pool.managers=[{id:1,effect_id:2,rarity:1,name:'Gestor 1',ts_last_used:0,buy_cost:5}];
response={tower_authoritative:{tower:raw,bank:123,boosts:[]}};
context.gz.levelUpFloor({}, {get_index:()=>1},2,()=>{callback=true;},()=>{});
assert(callback);assert.strictEqual(requests[0].action,'towerCommand');assert.strictEqual(requests[0].args.count,2);assert.strictEqual(bank,123);assert.strictEqual(floors[0].raw.level,3);assert.strictEqual(floorPool.managers[0].get_assignedTarget(),floors[0]);
// Contratação pela ponte também usa os pools reais nos três componentes.
for(const [kind,type,index,pool,target] of [['floor',1,1,floorPool,floors[0]],['vault',2,0,vault.pool,vault],['elevator',3,0,elevator.pool,elevator]]) {
 const manager={id:1,effect_id:type===1?2:type===2?8:5,rarity:1,name:'Gestor 1',ts_last_used:0,buy_cost:5};
 const poolData={manager_buy_index:2,managers:[manager]};
 if(kind==='floor'){raw.manager_pool=poolData;raw.floors[0].manager_id=1;}else {raw[kind].manager_pool=poolData;raw[kind].manager_id=1;}
 response={tower_authoritative:{tower:raw,bank:123,boosts:[]}};
 let hired=false;context.Dw.hireManager({}, {get_type:()=>type},target,()=>{hired=true;},()=>{});
 assert(hired);assert.strictEqual(requests.at(-1).args.command,'hire');assert.strictEqual(requests.at(-1).args.kind,kind);assert.strictEqual(requests.at(-1).args.index,index);
 assert.strictEqual(pool.get_managers()[0].get_assignedTarget(),target,kind+' funcionário atribuído após snapshot real');
}
context.gz.unlockTowerFloor({}, {get_index:()=>1},()=>{},()=>{});
assert.strictEqual(requests.at(-1).action,'towerCommand');assert.strictEqual(requests.at(-1).args.command,'unlock');assert.strictEqual(requests.at(-1).args.index,1);
const balanceBeforeVisual=bank;
context.Rq.prototype.addBankAmount(500,'vaultWorker',206);assert.strictEqual(bank,balanceBeforeVisual,'ciclo visual não duplica entrega do servidor');
context.Rq.prototype.addBankAmount(5,'other',206);assert.strictEqual(bank,balanceBeforeVisual+5,'outras entradas preservadas');
// Callback oficial soma renda; estado autoritativo deve ser aplicado depois, sem duplicação.
context.Re.sendActionRequest('collectTowerIdleReward',{},()=>{bank+=200;},()=>{});assert.strictEqual(bank,123);
assert(!context.HZTowerServer.applying);
console.log('PASS JS real: primeira região/sala, botão de custo 8 e clique, estado PHP, objetivos, comandos, contratação JSON nos três pools e saldo sem duplicação.');
