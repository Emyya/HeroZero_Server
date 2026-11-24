# Roteamento de Assets — de onde vem cada coisa

Como o jogo carrega os assets (build HTML5 `257`, embarcado em
`server-laravel/public/index.html`). Vale para o fluxo
`beta.html (login) → / (jogo)`.

---

## 1. Mapa geral das origens

| Tipo de asset | Origem | Quem decide |
|---|---|---|
| Página do jogo | **Local** `/index.html` (rota `/` em `routes/web.php`) | servidor |
| JS do cliente e libs (`HeroZero.min.js`, Tween*, jQuery, standalone.js) | **Local** `/js/…` | `<script src>` no index.html |
| API do jogo | **Local** `POST /request.php` → `GameController::request` | `clientVars.urlRequestServer` |
| Login/registo fora do protocolo | **Local** `POST /beta-api` → `BetaApiController` | `beta.html` |
| Hora do servidor (topbar) | **Local** `GET /servertime` | `serverClock()` no index.html |
| Arte do runtime html5 (rootPath do lime/OpenFL) | **CDN oficial** `https://hz-static-2.akamaized.net/assets/html5` | `lime.embed(rootPath…)` — fora do urlCDN, vai sempre ao CDN |
| Todos os outros assets do jogo (`assets/spine/`, `assets/textures/`, `assets/i18n/`, `assets/data/`, …) | **Proxy local** `/cdn/…` → local se existir, CDN caso contrário | `clientVars.urlCDN = location.origin + "/cdn/"` |

---

## 2. Arquitectura do proxy CDN (`/cdn/`)

`clientVars.urlCDN` aponta para `http://127.0.0.1:8000/cdn/` em vez do CDN
oficial. Todos os pedidos de assets do jogo passam pela rota Laravel:

```
GET /cdn/{path}   (routes/web.php)
```

### Fluxo por pedido

```
1. Cliente pede  GET /cdn/assets/textures/7a189f07.atlas
                         ↓
2. Laravel: $local = public_path("assets/textures/7a189f07.atlas")
                         ↓
3. file_exists($local)?
      SIM → response()->file($local)
            + Cache-Control: public, max-age=604800, immutable
            ← serve do disco, sem net; browser guarda 7 dias
      NÃO → file_get_contents(..., timeout=10s)
               ↓
            CDN respondeu?
               SIM → guarda em $local (mkdir recursivo se necessário)
                     → devolve ao cliente
                     + Cache-Control: public, max-age=604800, immutable
               NÃO → abort(404)
```

### Cache no browser

Todos os assets servidos pelo proxy (locais **ou** vindos do CDN) recebem:

```
Cache-Control: public, max-age=604800, immutable
```

- **`max-age=604800`** — 7 dias. O browser não volta a pedir o ficheiro
  durante esse período, mesmo que o servidor esteja acessível.
- **`immutable`** — elimina os pedidos de re-validação
  (`If-None-Match` / `If-Modified-Since`) enquanto o `max-age` for válido.
  Sem este flag, o browser enviava um pedido condicional a cada carregamento
  mesmo que tivesse o ficheiro em cache.
- Os nomes dos assets são hashes do conteúdo (ex.: `7a189f07…atlas`), por
  isso nunca mudam — não há risco de servir uma versão desatualizada.
- Para forçar re-download de tudo: **Ctrl+Shift+R** (hard refresh) ou limpar
  o cache do browser manualmente.

### Resultado prático

- **1.ª visita**: ficheiro local → PHP serve imediatamente; ficheiro no CDN
  → proxy faz download (timeout 10 s), guarda no disco, serve.
- **Visitas seguintes (até 7 dias)**: browser usa cache local — nenhum pedido
  chega ao servidor PHP.
- **Assets removidos do CDN e sem cópia local**: 404 (o jogo pode congelar
  nesse ecrã; solução: obter o ficheiro de outra fonte e copiar para
  `public/assets/`).

---

## 3. Arranque, passo a passo

1. **`beta.html`** — login/registo faz `POST /beta-api`; com sucesso guarda
   `{user_id, session_id, name}` em `localStorage.hz_session` e redireciona
   para `/?uid=..&sid=..&name=..`.
2. **`/`** — `routes/web.php` serve `public/index.html` com
   `response()->file()` + `Cache-Control: no-store` (nunca fica em cache no
   browser).
3. **Identidade** (`hzIdentity`, index.html): prioridade
   query string `?uid&sid&name` → `localStorage.hz_session` → convidado
   skelletonx (user 25328).
4. **Scripts locais**: jQuery, standalone.js, TweenLite/TweenMax,
   TimelineLite/TimelineMax e `HeroZero.min.js` (async, onload →
   `embedGame()`).
5. **Embarque**: `lime.embed("HeroZero.min", "appClient", w, h, {rootPath:
   appCDNUrl + "assets/html5", parameters: clientVars})` — o runtime
   OpenFL/lime vai buscar os recursos do build ao CDN oficial via `rootPath`
   (fora do nosso proxy); `clientVars.urlCDN` aponta para `/cdn/`.
6. **Boot do jogo**: `POST /request.php` (`autoLoginUser`) → respostas locais.
   Assets subsequentes passam todos pelo proxy `/cdn/`.

### clientVars relevantes (index.html)

```js
appCDNUrl        = "https://hz-static-2.akamaized.net/"
urlRequestServer = location.origin + "/request.php"
urlSocketServer  = ""
urlCDN           = location.origin + "/cdn/"   // proxy local
```

---

## 4. Estrutura física dos assets locais

```
server-laravel/public/
└── assets/
    ├── artifacts/  → symlink para HeroZero_Server/assets/artifacts
    ├── data/       → symlink para HeroZero_Server/assets/data
    ├── i18n/pt_BR/ (directório real)
    ├── textures/   (espelho do CDN — pré-descarregados + auto-cache)
    └── spine/      (criado automaticamente pelo proxy ao 1.º pedido)
```

- Qualquer ficheiro colocado em `public/assets/` é automaticamente
  priorizado sobre o CDN — sem tocar em listas nem em código.
- O proxy cria subpastas (`mkdir -p`) conforme necessário.
- Os symlinks `artifacts` e `data` são seguidos pelo `php artisan serve`.

---

## 5. Por que não o patch XHR nem Service Worker

Foram tentadas duas abordagens anteriores que não funcionaram:

**Patch `XMLHttpRequest.prototype.open`** (bloco `hzLocalTextures` no
index.html): o lime/OpenFL faz o carregamento de texturas dentro de um
**Web Worker**, cujo escopo é isolado do thread principal — o patch ao
prototype não chega lá.

**Service Worker**: em `127.0.0.1` alguns browsers não o activam; mesmo
quando registado, o SW fica em estado "waiting" na primeira carga e não
intercepta os pedidos a tempo.

A solução do proxy no servidor contorna ambos os problemas: independente do
mecanismo de rede do cliente (XHR, fetch, Worker), o pedido chega sempre ao
nosso servidor primeiro.

---

## 6. Troubleshooting rápido

| Sintoma | Causa provável | Remédio |
|---|---|---|
| Asset dá 404 em `/cdn/…` sem net | Ficheiro não está local e CDN inacessível | Obter o ficheiro e copiar para `public/assets/<path>` |
| Asset dá 404 em `/cdn/…` com net | Ficheiro removido do CDN | Idem; ou encontrar versão alternativa |
| `assets/html5` falha | rootPath ainda vai ao CDN oficial | Espelhar `assets/html5/` localmente e ajustar `rootPath` no index.html |
| Ícones de troféu 404 | Symlink `public/assets/artifacts` em falta | Recriar: `ln -s /home/laliene/HeroZero_Server/assets/artifacts public/assets/artifacts` |
| `constants_json.data` corrompido | É ZLIB: usar `gzuncompress`, não `gzdecode` | Ver docs dos troféus |
