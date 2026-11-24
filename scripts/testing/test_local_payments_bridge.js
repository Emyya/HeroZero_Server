'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const crypto = require('crypto').webcrypto;
class XHR {
    constructor() { this.listeners = {}; this.responseType = ''; }
    open(method, url) { this.url = url; }
    send(body) { this.body = body; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    complete(data, type = '') {
        this.responseType = type;
        if (type === 'arraybuffer') this.response = new TextEncoder().encode(JSON.stringify(data)).buffer;
        else if (type === 'json') this.response = data;
        else this.responseText = JSON.stringify(data);
        this.listeners.load.call(this);
    }
}
let callback = [];
const window = {location: new URL('http://127.0.0.1:8000/'), crypto, closePaymentWindow: ok => callback.push(ok)};
const context = {window, XMLHttpRequest: XHR, URL, URLSearchParams, Uint8Array, TextDecoder, Map, setTimeout: fn => fn()};
vm.runInNewContext(fs.readFileSync('server-laravel/public/js/local-payments.js', 'utf8'), context);
const body = 'action=initConsumableOfferPayment&offer_identifier=full_speedserver_int2_offer_with_sidekick&amount=207.90';
function request(value = body, path = '/request.php') { const xhr = new XHR(); xhr.open('POST', path); xhr.send(value); return xhr; }
const first = request();
const token = new URLSearchParams(first.body).get('purchase_request_id');
assert.match(token, /^[a-f0-9]{32}$/);
assert.equal(new URLSearchParams(request().body).get('purchase_request_id'), token, 'network retry reuses token');
first.complete({data: {payment_id: 1}, error: ''}, 'arraybuffer');
const next = request();
assert.notEqual(new URLSearchParams(next.body).get('purchase_request_id'), token, 'next click after success is a new purchase');
next.complete({data: {}, error: 'errInventoryNoEmptySlot'}, 'json');
const retryAfterError = request();
assert.notEqual(retryAfterError.body, next.body, 'confirmed rejection allows a new attempt');
assert.equal(request('action=startQuest&quest_id=12').body, 'action=startQuest&quest_id=12', 'normal actions unchanged');
assert.equal(request(body, 'https://example.com/request.php').body, body, 'other origins unchanged');
window.buyCurrency('/payment-success.html?payment_id=1');
assert.deepEqual(callback, [true], 'local credit completes without loading iframe');
window.buyCurrency('https://example.com/payment');
assert.deepEqual(callback, [true, false], 'external payment is not automatically confirmed');
console.log('PASS bridge: retry, next click, arraybuffer/JSON, normal actions and immediate local callback.');
