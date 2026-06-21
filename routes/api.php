<?php

use App\Http\Controllers\Api\Preschool\PreschoolHealthCheckCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthIncidentCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthSettingsController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthSeverityLevelController;
use App\Http\Controllers\Api\Preschool\PreschoolPreferencesSettingsController;
use App\Http\Controllers\Api\Preschool\PreschoolBillingRuleController;
use App\Http\Controllers\Api\Preschool\PreschoolFeeTypeController;
use App\Http\Controllers\Api\Preschool\PreschoolPaymentMethodController;
use App\Http\Controllers\Api\Preschool\PreschoolPaymentSettingsController;
use App\Http\Controllers\Api\Preschool\PreschoolReportingController;
use App\Http\Controllers\Api\Preschool\PreschoolSettingsDashboardController;
use App\Http\Controllers\Api\Preschool\PreschoolVaccinationCategoryController;
use App\Http\Controllers\Api\Governance\EnterpriseGovernanceController;
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

        Route::get('payments', [PreschoolPaymentSettingsController::class, 'show']);
        Route::put('payments', [PreschoolPaymentSettingsController::class, 'update']);

        Route::get('payments/fee-types', [PreschoolFeeTypeController::class, 'index']);
        Route::post('payments/fee-types', [PreschoolFeeTypeController::class, 'store']);
        Route::put('payments/fee-types/{feeType}', [PreschoolFeeTypeController::class, 'update']);
        Route::post('payments/fee-types/{feeType}/archive', [PreschoolFeeTypeController::class, 'archive']);

        Route::get('payments/payment-methods', [PreschoolPaymentMethodController::class, 'index']);
        Route::post('payments/payment-methods', [PreschoolPaymentMethodController::class, 'store']);
        Route::put('payments/payment-methods/{method}', [PreschoolPaymentMethodController::class, 'update']);
        Route::post('payments/payment-methods/{method}/archive', [PreschoolPaymentMethodController::class, 'archive']);

        Route::get('payments/billing-rules', [PreschoolBillingRuleController::class, 'index']);
        Route::put('payments/billing-rules', [PreschoolBillingRuleController::class, 'update']);

        Route::get('preferences', [PreschoolPreferencesSettingsController::class, 'show']);
        Route::put('preferences', [PreschoolPreferencesSettingsController::class, 'update']);

        Route::get('dashboard', [PreschoolSettingsDashboardController::class, 'show']);
    });

Route::prefix('preschool/reports')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::get('dashboard', [PreschoolReportingController::class, 'dashboard']);

        Route::get('attendance', [PreschoolReportingController::class, 'attendance']);
        Route::get('attendance/class', [PreschoolReportingController::class, 'attendanceClass']);
        Route::get('attendance/student', [PreschoolReportingController::class, 'attendanceStudent']);
        Route::get('attendance/trend', [PreschoolReportingController::class, 'attendanceTrend']);

        Route::get('assessments', [PreschoolReportingController::class, 'assessments']);
        Route::get('assessments/performance', [PreschoolReportingController::class, 'assessmentPerformance']);
        Route::get('assessments/completion', [PreschoolReportingController::class, 'assessmentCompletion']);

        Route::get('health', [PreschoolReportingController::class, 'health']);
        Route::get('health/incidents', [PreschoolReportingController::class, 'healthIncidents']);
        Route::get('health/vaccinations', [PreschoolReportingController::class, 'healthVaccinations']);

        Route::get('payments', [PreschoolReportingController::class, 'payments']);
        Route::get('payments/revenue', [PreschoolReportingController::class, 'paymentsRevenue']);
        Route::get('payments/outstanding', [PreschoolReportingController::class, 'paymentsOutstanding']);

        Route::get('enrollments', [PreschoolReportingController::class, 'enrollments']);
        Route::get('enrollments/trends', [PreschoolReportingController::class, 'enrollmentTrends']);

        Route::get('guardians', [PreschoolReportingController::class, 'guardians']);
        Route::get('guardians/issues', [PreschoolReportingController::class, 'guardianIssues']);

        Route::get('export', [PreschoolReportingController::class, 'export']);
    });

Route::prefix('governance')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::get('dashboard', [EnterpriseGovernanceController::class, 'dashboard']);
        Route::get('audit-logs', [EnterpriseGovernanceController::class, 'auditLogs']);
        Route::get('audit-logs/{id}', [EnterpriseGovernanceController::class, 'auditLog']);
        Route::get('security-events', [EnterpriseGovernanceController::class, 'securityEvents']);
        Route::get('security-events/{id}', [EnterpriseGovernanceController::class, 'securityEvent']);
        Route::post('security-events/{id}/resolve', [EnterpriseGovernanceController::class, 'resolveSecurityEvent']);
        Route::get('configuration-history', [EnterpriseGovernanceController::class, 'configurationHistory']);
        Route::get('risk-dashboard', [EnterpriseGovernanceController::class, 'riskDashboard']);
        Route::get('at-risk-students', [EnterpriseGovernanceController::class, 'atRiskStudents']);
        Route::get('investigations', [EnterpriseGovernanceController::class, 'investigations']);
    });
