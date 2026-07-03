<?php

use App\Http\Controllers\Api\Preschool\PreschoolAutomationController;
use App\Http\Controllers\Api\Preschool\PreschoolAutomationTaskController;
use App\Http\Controllers\Api\Preschool\PreschoolNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function (): void {
    Route::get('/', [PreschoolNotificationController::class, 'index']);
    Route::get('summary', [PreschoolNotificationController::class, 'summary']);
    Route::patch('{notification}/read', [PreschoolNotificationController::class, 'markRead']);
    Route::patch('{notification}/archive', [PreschoolNotificationController::class, 'archive']);
});

Route::prefix('automation-tasks')->group(function (): void {
    Route::get('/', [PreschoolAutomationTaskController::class, 'index']);
    Route::get('summary', [PreschoolAutomationTaskController::class, 'summary']);
    Route::patch('{task}/complete', [PreschoolAutomationTaskController::class, 'complete']);
    Route::patch('{task}/cancel', [PreschoolAutomationTaskController::class, 'cancel']);
    Route::patch('{task}/assign', [PreschoolAutomationTaskController::class, 'assign']);
});

Route::post('automation/run-daily-checks', [PreschoolAutomationController::class, 'runDailyChecks']);
