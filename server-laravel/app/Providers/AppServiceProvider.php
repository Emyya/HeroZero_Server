<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * A API beta de contas mantém um limite próprio. O tráfego do jogo
     * partilha um único IP no Docker, por isso /request.php não usa limite por IP.
     */
    private function configureRateLimiting(): void
    {
        $authPerMin = (int) (getenv('HZ_RATE_AUTH_PER_MIN') ?: 20);

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute($authPerMin)->by($request->ip()));
    }
}
