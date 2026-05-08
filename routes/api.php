<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-otp');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-password-reset');

        Route::middleware('auth.token')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });
});
