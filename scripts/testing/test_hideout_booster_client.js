const fs=require('fs'),vm=require('vm'),assert=require('assert');
const src=fs.readFileSync(__dirname+'/../../server-laravel/public/js/HeroZero.min.js','utf8');
function method(name){const start=src.indexOf('Vf.prototype.'+name+'=function');assert(start>=0,name);const body=src.indexOf('{',start);let n=1,end=body+1;while(n){if(src[end]==='{')n++;if(src[end]==='}')n--;end++;}return src.slice(start,end)+';';}
let now=1000;function Room(){};const context=vm.createContext({Vf:Room,K:{get_now:()=>now}});
for(const name of ['get_hideoutProductionWindow','get_hideoutProductionFactor','get_resourceAmountPerMinute','get_currentCalculatedResourceAmount','get_secondsTillActivityFinished','get_hash']){
 // O primeiro getter numérico é original: procurar o último override quando houver dois.
 const key='Vf.prototype.'+name+'=function';const start=src.lastIndexOf(key);const body=src.indexOf('{',start);let n=1,end=body+1;while(n){if(src[end]==='{')n++;if(src[end]==='}')n--;end++;}vm.runInContext(src.slice(start,end)+';',context);
}
const room=new Room();let window={amount:50,start:1000,end:1600};let kind='glue_production';
Object.assign(room,{hasData:()=>true,get_isOwnHideoutRoom:()=>true,get_identifier:()=>kind,getAdditionalValue:()=>window,
 _baseResourceAmountPerMinute:()=>10,_baseCalculatedResourceAmount:()=>0,_baseSecondsTillActivityFinished:()=>99,_baseProductionHash:()=>'',
 get_currentResourceAmount:()=>0,get_maxResourceAmount:()=>1000,get_isProducing:()=>true,get_tsLastResourceChange:()=>1000});
assert.strictEqual(room.get_resourceAmountPerMinute(),15);
now=1300;assert.strictEqual(room.get_currentCalculatedResourceAmount(),75);
const activeHash=room.get_hash();now=1600;assert.strictEqual(room.get_resourceAmountPerMinute(),10);assert.strictEqual(room.get_currentCalculatedResourceAmount(),150);assert.notStrictEqual(room.get_hash(),activeHash);
now=2200;assert.strictEqual(room.get_currentCalculatedResourceAmount(),250);assert.strictEqual(room.get_secondsTillActivityFinished(),4500);
window={amount:25,start:1300,end:-1};assert.strictEqual(room.get_currentCalculatedResourceAmount(),237);assert.strictEqual(room.get_resourceAmountPerMinute(),12.5);
kind='main_building';assert.strictEqual(room.get_resourceAmountPerMinute(),10);assert.strictEqual(room.get_currentCalculatedResourceAmount(),0);
console.log('PASS: getters reais mostram +25/+50%, preservam capacidade, aplicam apenas a janela ativa e refrescam hash na expiração.');

// Pré-visualizações da lista de construção não têm hideout_id/metadados.
vm.runInContext(method('get_hash').replace('Vf.prototype.get_hash','Vf.prototype._baseProductionHash'),context);
for(const identifier of ['generator','glue_production','stone_production']) {
 const preview=new Room();
 Object.assign(preview,{hasData:key=>key==='identifier',get_identifier:()=>identifier,
  get_isOwnHideoutRoom:()=>{throw new Error('unknown field: hideout_id');},
  getAdditionalValue:()=>{throw new Error('unknown field: additional_value_2');},
  get_level:()=>1,get_currentGeneratorLevel:()=>0,get_tsActivityEnd:()=>0,
  get_status:()=>0,get_tsLastResourceChange:()=>0,get_currentBrainFactor:()=>1,get_currentGuildSynergyFactor:()=>1,
  _baseResourceAmountPerMinute:()=>10,_baseCalculatedResourceAmount:()=>0,_baseSecondsTillActivityFinished:()=>0,
  get_isProducing:()=>false});
 assert.strictEqual(preview.get_hideoutProductionWindow(),null);
 assert.strictEqual(preview.get_hideoutProductionFactor(),1);
 assert.strictEqual(preview.get_hash(),preview._baseProductionHash());
 assert.strictEqual(preview.get_resourceAmountPerMinute(),10);
 assert.strictEqual(preview.get_currentCalculatedResourceAmount(),0);
 assert.strictEqual(preview.get_secondsTillActivityFinished(),0);
}
console.log('PASS: previews de construção de gerador/cola/pedra calculam hash e tooltips sem ler campos ausentes.');
