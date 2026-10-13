<?php

namespace App\Providers;

use App\Services\RazorpayGateway;
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
        $this->app->singleton(RazorpayGateway::class, fn () => new RazorpayGateway(
            config('services.razorpay.key'),
            config('services.razorpay.secret'),
            config('services.razorpay.currency', 'INR'),
            config('services.razorpay.webhook_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keyed per signed-in user, falling back to IP, so one abusive account
        // cannot lock out everyone behind the same address.
        $perUser = fn (Request $request) => $request->user()?->id ?: $request->ip();

        // Opening a checkout writes an order row and calls Razorpay.
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($perUser($request)));

        // The player saves every ~15s and again on unload; 60 leaves room for
        // several tabs without letting a script hammer the endpoint.
        RateLimiter::for('progress', fn (Request $request) => Limit::perMinute(60)->by($perUser($request)));

        // Public, and the only endpoint that confirms a reference exists.
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
