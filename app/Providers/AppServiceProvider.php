<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('orders-store', function (Request $request) {
            return Limit::perMinute(60)->by('orders-store|'.$this->throttleUser($request));
        });

        RateLimiter::for('orders-status', function (Request $request) {
            return Limit::perMinute(30)->by('orders-status|'.$this->throttleUser($request));
        });
    }

    /**
     * Resolve the per-user bucket key, falling back to the IP address for
     * unauthenticated requests. Kasir sharing a network must not share a
     * bucket, so the authenticated user ID is preferred over the IP.
     */
    private function throttleUser(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip());
    }
}
