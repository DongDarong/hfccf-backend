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
        RateLimiter::for('api', function (Request $request) {
            $userId = $request->user()?->id;
            $identity = $userId ?: $request->ip();

            // General API protection: enough for normal dashboard usage, but still blocks noisy clients.
            return Limit::perMinute(120)->by('api:'.$identity);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));

            // Login is stricter because repeated attempts are usually credential guessing.
            return [
                Limit::perMinute(5)->by('login:email:'.$email),
                Limit::perMinute(20)->by('login:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-otp', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));

            // OTP endpoints are intentionally low to prevent code brute force and resend abuse.
            return [
                Limit::perMinute(3)->by('otp:email:'.$email),
                Limit::perMinute(10)->by('otp:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-password-reset', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));

            return [
                Limit::perMinute(3)->by('reset:email:'.$email),
                Limit::perMinute(10)->by('reset:ip:'.$request->ip()),
            ];
        });
    }
}
