<?php

namespace App\Providers;

use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentHealthCheckLog;
use App\Models\PreschoolStudentHealthContact;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\PreschoolStudentMedicalProfile;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Observers\PreschoolStudentHealthObserver;
use App\Support\DatabaseSafetyGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // Disable FK constraints for SQLite in testing environment
        if (app()->environment('testing') && DB::getDriverName() === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys=OFF');
            } catch (\Exception $e) {
                // Silently fail if FK pragma not supported
            }
        }

        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(500)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            if ($user) {
                // Authenticated users get more headroom.
                return Limit::perMinute(300)->by('api:user:'.$user->id);
            }

            // Guests are more restricted.
            return Limit::perMinute(60)->by('api:ip:'.$request->ip());
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

        PreschoolStudentMedicalProfile::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentAllergy::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentVaccinationRecord::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentMedicationRecord::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentHealthIncident::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentHealthContact::observe(PreschoolStudentHealthObserver::class);
        PreschoolStudentHealthCheckLog::observe(PreschoolStudentHealthObserver::class);

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $connection = (string) config('database.default');
            $database = (string) config("database.connections.{$connection}.database", '');

            DatabaseSafetyGuard::assertCommandCanRun(
                $event->command,
                (string) config('app.env'),
                $connection,
                $database
            );
        });
    }
}
