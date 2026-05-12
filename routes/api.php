<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::post('login', [AuthController::class, 'login'])
        ->middleware(['throttle:api', 'throttle:auth-login']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware(['throttle:api', 'throttle:auth-otp']);

    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware(['throttle:api', 'throttle:auth-otp']);

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware(['throttle:api', 'throttle:auth-password-reset']);

    /*
    |--------------------------------------------------------------------------
    | Protected Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

        Route::get('me', [AuthController::class, 'me']);

        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected User Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | User Read Permissions
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:users:read'])->group(function (): void {

        Route::get('users', [UserController::class, 'index']);

        Route::get('users/{user}', [UserController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Write Permissions
    |--------------------------------------------------------------------------
    */

    Route::middleware(['permission:users:write'])->group(function (): void {

        Route::post('users', [UserController::class, 'store']);

        Route::put('users/{user}', [UserController::class, 'update']);

        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });
});
