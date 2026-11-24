<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Logged-in API usage, per user (or IP before login).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Login/register: slow down password guessing, per email + IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
