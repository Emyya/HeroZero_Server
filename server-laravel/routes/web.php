<?php

use App\Http\Controllers\BetaApiController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

// API do jogo (o cliente html5_257 POSTa exatamente em /request.php).
Route::match(['post', 'options'], '/request.php', [GameController::class, 'request']);

// Boot do cliente desktop (Steam/NW.js).
Route::get('/steam.php', [GameController::class, 'steam']);

// API beta de conta (registrar/login fora do protocolo do jogo).
Route::post('/beta-api', [BetaApiController::class, 'handle'])
    ->middleware('throttle:auth');

// Logs do cliente (dev).
Route::post('/clientlog', [GameController::class, 'clientlog']);

// Debug: hora do servidor (GET = ler, POST add_days/add_hours/reset)
Route::match(['get', 'post'], '/servertime', function () {
    $req = request();
    if ($req->isMethod('post')) {
        if ($req->has('add_days'))    \HeroZero\GameTime::addDays((int)$req->input('add_days'));
        if ($req->has('add_hours'))   \HeroZero\GameTime::addHours((int)$req->input('add_hours'));
        if ($req->has('add_seconds')) \HeroZero\GameTime::setOffset(\HeroZero\GameTime::offset() + (int)$req->input('add_seconds'));
        if ($req->has('reset'))       \HeroZero\GameTime::reset();
        \HeroZero\Tournament::active();
    }
    $now = \HeroZero\GameTime::now();
    $tz  = \HeroZero\Live::gameTimezone();
    return response()->json([
        'server_time'   => $now,
        'offset_seconds'=> \HeroZero\GameTime::offset(),
        'offset_days'   => round(\HeroZero\GameTime::offset() / 86400, 2),
        'formatted'     => (new \DateTimeImmutable('@'.$now))->setTimezone($tz)->format('Y-m-d H:i:s T'),
        'real_time'     => time(),
        'game_day'      => \HeroZero\GameTime::gameDay(),
        'uptime_seconds'=> \HeroZero\GameTime::uptime(),
        'epoch'         => \HeroZero\GameTime::epoch(),
    ]);
});

// Proxy de assets do CDN: serve localmente se existir, senão repassa ao CDN.
Route::get('/cdn/{path}', function (string $path) {
    $local = public_path($path);
    if (file_exists($local)) {
        return response()->file($local, ['Cache-Control' => 'public, max-age=604800, immutable']);
    }
    $upstream = 'https://hz-static-2.akamaized.net/' . $path;
    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $body = @file_get_contents($upstream, false, $ctx);
    if ($body === false) {
        abort(404);
    }
    @mkdir(dirname($local), 0755, true);
    @file_put_contents($local, $body);
    $mime = 'application/octet-stream';
    foreach (['json'=>'application/json','atlas'=>'application/json',
              'webp'=>'image/webp','ktx2'=>'application/octet-stream',
              'png'=>'image/png','fnt'=>'text/plain','data'=>'application/octet-stream'] as $ext => $m) {
        if (str_ends_with($path, '.' . $ext)) { $mime = $m; break; }
    }
    return response($body, 200)->header('Content-Type', $mime)
                               ->header('Cache-Control', 'public, max-age=604800, immutable');
})->where('path', '.*');

// Raiz -> cliente do jogo (le ?uid=&sid= e faz auto-login).
Route::get('/', fn () => response()->file(public_path('index.html'), [
    'Cache-Control' => 'no-store, no-cache, must-revalidate',
    'Pragma'        => 'no-cache',
]));
