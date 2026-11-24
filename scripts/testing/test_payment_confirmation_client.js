'use strict';
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('server-laravel/public/js/HeroZero.min.js', 'utf8');
const start = source.indexOf('Sd.handlePurchaseComplete=function');
const end = source.indexOf(';\ng["com.playata.application.ui.elements.shop_new.UiShop"]', start);
assert(start >= 0 && end > start);
const dialogs = [], updates = [], events = [];
const context = {
    Sd: {}, f: {current: {text: key => key}},
    B: {showInfoDialog: (title, text) => dialogs.push(text), appPlatform: {hideAdvertisment() {}}},
    Ra: {current: {get_info: () => ({get_isIOS: () => false})}},
    n: {application: {updateData: data => updates.push(data)}, billing: {resetProducts() {}}},
    eb: {get_tracking: () => ({onPayment() {}})},
    A: {get_instance: () => ({get_baseUserPanel: () => ({syncGame() {}})})},
    na: {dispatch: event => events.push(event)}, sc: function(name) {this.name = name;}, Ry: {}
};
vm.runInNewContext(source.slice(start, end) + ';', context);
for (const name of ['Exclusiva Maleta de Fichas', 'Passe da temporada', 'Pacote de equipamento', '30 donuts']) {
    const data = {local_payment_message: 'Compra concluída: ' + name, hideout: {max_worker_count: 2}, season_progress: {}};
    context.Sd.handlePurchaseComplete(true, data, false, true, false, true, false);
    assert.equal(dialogs.at(-1), data.local_payment_message);
    assert.equal(updates.at(-1), data, 'estado e coleções continuam atualizados');
}
assert(!events.some(e => e.name === 'HideoutMessage.workerBought'), 'outros produtos não notificam compra de robô');
context.Sd.handlePurchaseComplete(false, {}, false, true, false, true, false);
assert.equal(dialogs.at(-1), 'dialog/buy_donuts/payment_failed/text');
console.log('PASS cliente real: mensagens específicas, atualização do estado, ausência de falso robô e rejeição.');
