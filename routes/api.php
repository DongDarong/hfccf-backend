<?php

use App\Http\Controllers\Api\Preschool\PreschoolHealthCheckCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthIncidentCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthSettingsController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthSeverityLevelController;
use App\Http\Controllers\Api\Preschool\PreschoolSettingsDashboardController;
use App\Http\Controllers\Api\Preschool\PreschoolVaccinationCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('preschool/settings')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::get('health', [PreschoolHealthSettingsController::class, 'show']);
        Route::put('health', [PreschoolHealthSettingsController::class, 'update']);

        Route::get('health/severity-levels', [PreschoolHealthSeverityLevelController::class, 'index']);
        Route::post('health/severity-levels', [PreschoolHealthSeverityLevelController::class, 'store']);
        Route::get('health/severity-levels/{severity}', [PreschoolHealthSeverityLevelController::class, 'show']);
        Route::put('health/severity-levels/{severity}', [PreschoolHealthSeverityLevelController::class, 'update']);
        Route::post('health/severity-levels/{severity}/archive', [PreschoolHealthSeverityLevelController::class, 'archive']);

        Route::get('health/incident-categories', [PreschoolHealthIncidentCategoryController::class, 'index']);
        Route::post('health/incident-categories', [PreschoolHealthIncidentCategoryController::class, 'store']);
        Route::get('health/incident-categories/{category}', [PreschoolHealthIncidentCategoryController::class, 'show']);
        Route::put('health/incident-categories/{category}', [PreschoolHealthIncidentCategoryController::class, 'update']);
        Route::post('health/incident-categories/{category}/archive', [PreschoolHealthIncidentCategoryController::class, 'archive']);

        Route::get('health/vaccination-categories', [PreschoolVaccinationCategoryController::class, 'index']);
        Route::post('health/vaccination-categories', [PreschoolVaccinationCategoryController::class, 'store']);
        Route::get('health/vaccination-categories/{category}', [PreschoolVaccinationCategoryController::class, 'show']);
        Route::put('health/vaccination-categories/{category}', [PreschoolVaccinationCategoryController::class, 'update']);
        Route::post('health/vaccination-categories/{category}/archive', [PreschoolVaccinationCategoryController::class, 'archive']);

        Route::get('health/check-categories', [PreschoolHealthCheckCategoryController::class, 'index']);
        Route::post('health/check-categories', [PreschoolHealthCheckCategoryController::class, 'store']);
        Route::get('health/check-categories/{category}', [PreschoolHealthCheckCategoryController::class, 'show']);
        Route::put('health/check-categories/{category}', [PreschoolHealthCheckCategoryController::class, 'update']);
        Route::post('health/check-categories/{category}/archive', [PreschoolHealthCheckCategoryController::class, 'archive']);

        Route::get('dashboard', [PreschoolSettingsDashboardController::class, 'show']);
    });
