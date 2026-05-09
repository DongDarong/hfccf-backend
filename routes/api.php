<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:api', 'throttle:auth-login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware(['throttle:api', 'throttle:auth-otp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware(['throttle:api', 'throttle:auth-otp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware(['throttle:api', 'throttle:auth-password-reset']);

    Route::middleware(['auth.token', 'throttle:api'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth.token', 'throttle:api', 'permission:users:write'])->group(function (): void {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
});
