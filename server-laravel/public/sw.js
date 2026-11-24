/* Service Worker — redireciona assets do CDN para o espelho local.
   Cobre XHR do thread principal E de Web Workers (onde o prototype patch nao chega). */

var MIRRORED = {
    "05cbdab7f6af27d85a1dcb91bf20d1ee.ktx2":1,"0765c49d20236f94f051292b8abd594d.atlas":1,
    "09d9511ac097d806e2ec0466dca1051e.atlas":1,"0bc28b5c6c5ae28afc3f62621dd0a084.atlas":1,
    "0d6688999ca9689c6b9bd58f7ddcfa61.ktx2":1,"1f40d5e433c7ebdbc394cf1ebb108053.ktx2":1,
    "200020b92f1afbf9465c1c721480776c.atlas":1,"2dd92e894bf7008dbb6ebe1658b9b8bf.atlas":1,
    "31e84aa157085439849e6b9a5614c33d.png":1,"3644f95ee4e105ed6a4f248e3efdff04.ktx2":1,
    "47ed9cd83bfbc20685506fb2ee1e21f1.ktx2":1,"494e05c831d7fd39443557eddd6c9086.fnt":1,
    "498312e6eae222c9d2ecbb8844d909da.atlas":1,"5ce66d548d270364d69d3ea07f482bd1.fnt":1,
    "6160523b92c9e7938c477b47a88fb416.fnt":1,"66cc83ac285ab0e4428e56d215ebf65e.fnt":1,
    "676477d777abfb9e99f4750ef24950a7.ktx2":1,"6956d714e01ea4c3affb0f7d40867ece.ktx2":1,
    "6bf85baf7f1df5a67bb1e9c62c718a1f.atlas":1,"7772762d25ff5e0b8306620748257267.atlas":1,
    "7a189f07c062fef542f4d25ac8d4c04a.atlas":1,"7a189f07c062fef542f4d25ac8d4c04a.ktx2":1,
    "83a359106b41a3aa4671976a8d0f64b6.ktx2":1,"872b837a2b25e111ed9d1cb2b521c635.fnt":1,
    "8bd28888bdf6e13ab0784656de983ee7.atlas":1,"a04119626c24ba111fa1a0f06c04dcc1.ktx2":1,
    "bba40d1f18a0b2bb3e67a0807db22803.atlas":1,"bfef7b3b5d4cea999226c296e217c4f5.ktx2":1,
    "c16fdf9a97cea97c0b4b1db3708d8832.ktx2":1,"c71954334c08214691ca2507c9e1c022.ktx2":1,
    "cb6c84097d80759bb654337b6f48e64f.png":1
};

self.addEventListener('install', function() { self.skipWaiting(); });
self.addEventListener('activate', function(e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('fetch', function(e) {
    var u = e.request.url;

    /* textures: so redireciona se estiver na lista MIRRORED */
    var m = u.match(/^https:\/\/hz-static-2\.akamaized\.net\/assets\/textures\/([^?]+)(\?.*)?$/);
    if (m && MIRRORED[m[1]]) {
        e.respondWith(fetch('/assets/textures/' + m[1] + (m[2] || '')));
        return;
    }

    /* i18n e data: sempre local */
    var t = u.match(/^https:\/\/hz-static-2\.akamaized\.net\/assets\/(i18n\/.+|data\/.+)$/);
    if (t) {
        e.respondWith(fetch('/assets/' + t[1]));
        return;
    }
});
