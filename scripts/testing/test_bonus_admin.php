<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/admin-laravel/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/admin-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void { fwrite(STDERR, $e->getMessage()."\n"); exit(1); });
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "OK: $label\n"; }
$controller = new App\Http\Controllers\BonusController();
$session = $app['session']->driver('array'); $session->start();
$app->instance('session.store', $session);
$app['view']->share('errors', new Illuminate\Support\ViewErrorBag());
$request = Request::create('/bonuses'); $request->setLaravelSession($session);
$app->instance('request', $request);
$middleware = new App\Http\Middleware\AdminAuth();
$response = $middleware->handle($request, fn() => response('permitido'));
check($response->getStatusCode() === 302, 'sem sessão admin é redireccionado');
$session->put('is_admin', true);
check($middleware->handle($request, fn() => response('permitido'))->getStatusCode() === 200, 'sessão admin permite gestão');
DB::beginTransaction();
try {
    DB::table('bonus_event_days')->delete(); DB::table('bonus_events')->delete();
    $view = $controller->index();
    check(str_contains($view->render(), 'Vantagens combináveis'), 'página renderiza com relógio do jogo');
    $today = HeroZero\BonusEvents::today();
    $request = Request::create('/bonuses', 'POST', ['name'=>'Teste admin rollback','start'=>$today,
        'duration'=>3,'multiplier'=>2,'types'=>[7,8]]);
    $request->setLaravelSession($session); $app->instance('request', $request);
    $response = $controller->store($request);
    check($response->getStatusCode() === 302 && DB::table('bonus_event_days')->count() === 6, 'admin cria três dias de XP e moedas duplos');
    $controller->store($request);
    check(DB::table('bonus_events')->count() === 1 && DB::table('bonus_event_days')->count() === 6, 'sobreposição no admin reverte criação inteira');
    $id = (int)DB::table('bonus_events')->value('id'); $controller->cancel($id);
    check(DB::table('bonus_event_days')->count() === 0 && DB::table('bonus_events')->value('enabled') == 0, 'admin cancela evento');
    echo "Integração do admin concluída com rollback.\n";
} finally { DB::rollBack(); }
