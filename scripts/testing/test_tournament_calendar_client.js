// Offline: setters/getters reais do bundle e o bloco real que recebe datas.
process.env.TZ='Europe/Lisbon';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(__dirname+'/../../server-laravel/public/js/HeroZero.min.js','utf8').replace(/=\s+function/g,'=function');
function assignment(prefix){const start=source.indexOf(prefix);assert(start>=0,prefix);let end=source.indexOf('{',start),depth=1;while(depth){end++;if(source[end]==='{')depth++;if(source[end]==='}')depth--;}return source.slice(start,end+1)+';';}
const ctx=vm.createContext({Nn:{},iF:{},K:{get_localDateTime:()=>({isToday:()=>false})},wb:{getFormattedDateTime:ts=>new Date(ts*1000).toLocaleString('pt-PT',{timeZone:'Europe/Lisbon'})}});
for(const name of ['Nn.get_tournamentEndTimestamp','Nn.set_tournamentEndTimestamp','Nn.get_tournamentEndDateTime','iF.get_globalTournamentEndTimestamp','iF.set_globalTournamentEndTimestamp','iF.get_tournamentEndDateTime'])vm.runInContext(assignment(name+'=function'),ctx);
const start=source.indexOf('if(c.hasData("tournament_end_timestamps",!0))');assert(start>=0);const end=source.indexOf('c.hasData("global_tournament_end_timestamp"',start);assert(end>start);const receive=source.slice(start,end);
const xp=Date.parse('2026-12-13T21:00:00Z')/1000,stats=Date.parse('2026-12-16T21:00:00Z')/1000,pvp=Date.parse('2026-12-15T21:00:00Z')/1000;
ctx.c={hasData:key=>key==='tournament_end_timestamps',getTypedObject:()=>({getInt:key=>key==='1'?xp:stats})};vm.runInContext(receive,ctx);ctx.xp=xp;ctx.pvp=pvp;
vm.runInContext('Nn.set_tournamentEndTimestamp(xp);iF.set_globalTournamentEndTimestamp(pvp)',ctx);
for(const type of [1,2,1,2]){assert.equal(vm.runInContext('Nn.get_tournamentEndTimestamp('+type+')',ctx),type===1?xp:stats);assert(vm.runInContext('Nn.get_tournamentEndDateTime('+type+')',ctx).includes(type===1?'13/12/2026':'16/12/2026'));}
assert.equal(vm.runInContext('iF.get_globalTournamentEndTimestamp()',ctx),pvp);assert(vm.runInContext('iF.get_tournamentEndDateTime()',ctx).includes('15/12/2026'));
assert(source.includes('Nn.get_tournamentEndDateTime(1)'));assert(source.includes('Nn.get_tournamentEndDateTime(this.get_selectedTournamentType())'));
ctx.c={hasData:()=>false};vm.runInContext(receive,ctx);assert.equal(vm.runInContext('Nn.get_tournamentEndTimestamp(2)',ctx),stats,'resposta parcial sem mapa conserva cache');
console.log('PASS: cliente real recebe datas e alterna XP/atributos sem trocar os prazos; PvP separado.');

// Linhas reais dos dois layouts do ranking, com UiSeasonPointReward real.
const locations=JSON.parse(require('zlib').inflateSync(fs.readFileSync(__dirname+'/../../assets/data/constants_json.data'))).season_locations;
function element(){const data={};return new Proxy(data,{get:(o,key)=>{
    if(key in o)return o[key];
    if(key==='getChildByName')return name=>o[name]||(o[name]=element());
    if(key==='get_onClick')return ()=>({add:()=>{}});
    if(key.startsWith('set_'))return value=>{o[key.slice(4)]=value;return value;};
    if(key.startsWith('get_'))return ()=>o[key.slice(4)]||30;
    if(key==='gotoAndStop'||key==='setUriSprite')return ()=>{};
    return o[key]=element();
}});}
const season={isActive:()=>true,get_tsEnd:()=>season.end,get_iconURL:()=>'',hasSeasonPointLocation:id=>!!locations[id]?.gods_1,getSeasonPointLocationValue:id=>locations[id]?.gods_1||0};season.end=xp+86400;
function component(){this.refresh=()=>{};this.dispose=()=>{};this.set_text=()=>{};this.set_surpriseBox=()=>{};this.set_visible=()=>{};}
function Base(){}Base.prototype._hx_constructor=function(){};Base.prototype.refresh=function(item){this._item=item;};Base.prototype.get_onClick=()=>({add:()=>{}});Base.prototype.get_onDoubleClick=Base.prototype.get_onClick;Base.prototype.set_useHandCursor=()=>{};
Object.assign(ctx,{SU:function(){},RB:function(){},hA:function(){},V:Base,Hw:function(){},h:function(){},y:{__cast:v=>v},ja:{},Zh:component,vv:component,ha:component,Xe:component,S:component,l8:component,
    n:{info:{get_isTouchScreen:()=>true}},k:()=>()=>{},q:{get_current:()=>({get_character:()=>({get_seasonProgress:()=>season})})},f:{current:{text:()=>'',formatHugeNumber:String}},X:{cutLength:String},Yb:{exists:id=>!!locations[id]},x:{get_xp_tournament_reward_reward_box_identifier_top_1:()=>'',get_xp_tournament_reward_reward_box_identifier_top_5:()=>''}});
for(const name of ['SU','RB','hA']){
    if(name==='SU')vm.runInContext(assignment('var SU=function'),ctx);
    else Object.setPrototypeOf(ctx[name].prototype,Base.prototype);
}
for(const name of ['SU.prototype.refresh','SU.prototype.getSeasonPointsAmount','RB.prototype._hx_constructor','RB.prototype.set_tournamentType','RB.prototype.refresh','hA.prototype._hx_constructor','hA.prototype.refresh'])vm.runInContext(assignment(name+'=function'),ctx);
ctx.hA.prototype.getRewardAmountForRank=()=>0;ctx.hA.prototype.updateTooltips=()=>{};
const item=new Proxy({}, {get:(_,key)=>key==='get_rank'?()=>1:key==='get_name'||key==='get_guildName'?()=> 'teste':()=>0});
for(const [name,type] of [['hA',1],['RB',1],['RB',2]]){
    const row=new ctx[name]();row._hx_constructor(element());if(name==='RB')row.set_tournamentType(type);
    row.refresh(item,0);assert.equal(row._seasonPointReward._seasonPointsAmount,type===1?30:0,'elegibilidade pela data do próprio torneio');
    season.end=stats+86400;row.refresh(item,0);assert.equal(row._seasonPointReward._seasonPointsAmount,30,'pontos reaparecem quando temporada cobre o fim');
    season.end=xp+86400;
}
console.log('PASS: linhas reais XP/habilidades mostram pontos do passe e usam o prazo de cada torneio.');

// O diálogo lê a recompensa do passe fixada pelo servidor.
function Data(data){this.data=data;}Data.prototype.hasData=function(key){return this.data[key]!=null;};Data.prototype.getTypedObject=function(key){return new Data(this.data[key]);};Data.prototype.getInt=function(key){return this.data[key]||0;};Data.prototype.getString=function(key){return this.data[key]||'';};
const payload={1:{rank:1,value:100,reward:{type:1,amount:75,type_2:3,amount_2:10}},season_reward:{amount:30,identifier:'gods_1'}};
function Reward(){this.get_rewardData=()=>new Data(payload);this.hasTournamentData=key=>!!payload[key];this.getRank=key=>payload[key]?.rank||0;this.getItemIds=()=>[];}
function Window(){}Window.prototype._hx_constructor=function(){this._content=element();this._btnClose=element();};
Object.assign(ctx,{fz:Reward,tD:function(){},w:Window,Ssa:function(){},bba:function(){},d:function(data){Object.assign(this,data);},ea:function(type,amount,data){Object.assign(this,{type,amount,data});},Kf:function(entries){ctx.dialogEntries=entries;}});
ctx.ea.fromSeasonPoints=()=>{throw new Error('Não deve recalcular pontos fixados pelo servidor.');};
ctx.tD.prototype.addCloseButton=()=>{};ctx.tD.prototype.get_character=()=>({get_seasonProgress:()=>season});
for(const name of ['getRewardType','getRewardType2','getRewardAmount','getRewardAmount2','getTotalRewardAmount'])vm.runInContext(assignment('fz.prototype.'+name+'=function'),ctx);
vm.runInContext(assignment('tD.prototype._hx_constructor=function'),ctx);vm.runInContext('tD.prototype._hx_constructor.call(tD.prototype,{})',ctx);
assert.deepStrictEqual(JSON.parse(JSON.stringify(ctx.dialogEntries.map(e=>[e.type,e.amount]))),[[0,75],[5,10],[42,30]]);
console.log('PASS: diálogo real mostra os 30 pontos do passe enviados pelo servidor.');
