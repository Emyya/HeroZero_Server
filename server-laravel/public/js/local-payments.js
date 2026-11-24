/* Compras locais: confirmar após entrega no servidor, sem iframe de pagamento. */
(function () {
    'use strict';
    window.buyCurrency = function (url) {
        window.__localPaymentDone = false;
        var local = new URL(url, window.location.href);
        if (local.origin === window.location.origin && local.pathname === '/payment-success.html') {
            // O callback manda consultar getLastPaymentStatus, que atualiza o estado do jogo.
            setTimeout(function () { window.closePaymentWindow(true); }, 0);
            return;
        }
        window.closePaymentWindow(false);
    };

    // Chave estável enquanto uma compra está pendente, incluindo repetição após falha de rede.
    // Apenas o transporte da página local muda; o bundle oficial fica intacto.
    var pending = new Map();
    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this.__localPurchaseUrl = new URL(url, window.location.href);
        return originalOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function (body) {
        var url = this.__localPurchaseUrl;
        if (url && url.origin === window.location.origin && url.pathname === '/request.php' && typeof body === 'string') {
            var params = new URLSearchParams(body);
            var action = params.get('action');
            if (action === 'initPayment' || action === 'initConsumableOfferPayment') {
                var key = body;
                var token = params.get('purchase_request_id') || pending.get(key);
                if (!token) {
                    var bytes = new Uint8Array(16);
                    window.crypto.getRandomValues(bytes);
                    token = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
                }
                pending.set(key, token);
                params.set('purchase_request_id', token);
                body = params.toString();
                this.addEventListener('load', function () {
                    // Sem JSON válido, o resultado continua incerto: retry usa o mesmo recibo.
                    try {
                        var text = this.responseType === 'json' ? null : (this.responseType === 'arraybuffer' ? new TextDecoder().decode(this.response) : this.responseText);
                        var response = this.responseType === 'json' ? this.response : JSON.parse(text);
                        if (response && 'error' in response && response.data && pending.get(key) === token) pending.delete(key);
                    } catch (e) {}
                });
            }
        }
        return originalSend.call(this, body);
    };
}());
