<?php

namespace App\Providers;

use App\Support\LanAwareVite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Vite::class, fn () => new LanAwareVite);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('board-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                throw ValidationException::withMessages([
                    'login_id' => '試行回数が多すぎます。しばらく待ってから再度お試しください。',
                ]);
            });
        });
    }
}
