<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\English\EnglishClassController;
use App\Http\Controllers\Api\English\EnglishDashboardController;
use App\Http\Controllers\Api\English\EnglishStudentController;
use App\Http\Controllers\Api\English\EnglishSubmissionController;
use App\Http\Controllers\Api\English\EnglishTaskController;
use App\Http\Controllers\Api\English\EnglishTeacherController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Preschool\PreschoolAssessmentCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolHealthAlertController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentHealthAuditController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentHealthController;
use App\Http\Controllers\Api\Preschool\PreschoolAcademicLifecycleController;
use App\Http\Controllers\Api\Preschool\PreschoolAttendanceController;
use App\Http\Controllers\Api\Preschool\PreschoolEnrollmentController;
use App\Http\Controllers\Api\Preschool\PreschoolClassController;
use App\Http\Controllers\Api\Preschool\PreschoolClassroomReportController;
use App\Http\Controllers\Api\Preschool\PreschoolClassroomResourceController;
use App\Http\Controllers\Api\Preschool\PreschoolDashboardController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianCommunicationController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianIntegrityController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianPortalController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianGovernanceController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianRemediationController;
use App\Http\Controllers\Api\Preschool\PreschoolInstitutionalGovernanceController;
use App\Http\Controllers\Api\Preschool\PreschoolLifecycleAuditController;
use App\Http\Controllers\Api\Preschool\PreschoolExportGovernanceController;
use App\Http\Controllers\Api\Preschool\PreschoolGovernanceDiffController;
use App\Http\Controllers\Api\Preschool\PreschoolGovernanceCaseController;
use App\Http\Controllers\Api\Preschool\PreschoolReportSnapshotController;
use App\Http\Controllers\Api\Preschool\PreschoolPaymentController;
use App\Http\Controllers\Api\Preschool\PreschoolInvoiceController;
use App\Http\Controllers\Api\Preschool\PreschoolReceiptController;
use App\Http\Controllers\Api\Preschool\PreschoolProgressSummaryController;
use App\Http\Controllers\Api\Preschool\PreschoolReportPeriodController;
use App\Http\Controllers\Api\Preschool\PreschoolSettingsBackboneController;
use App\Http\Controllers\Api\Preschool\PreschoolScheduleController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentAssessmentController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentGuardianController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentReportController;
use App\Http\Controllers\Api\Preschool\PreschoolTeacherController;
use App\Http\Controllers\Api\Preschool\PreschoolTeacherScheduleController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\Scholarship\ScholarshipApplicationController;
use App\Http\Controllers\Api\Scholarship\ScholarshipDashboardController;
use App\Http\Controllers\Api\Scholarship\ScholarshipReviewController;
use App\Http\Controllers\Api\Scholarship\ScholarshipStudentController;
use App\Http\Controllers\Api\Sport\SportAdminCoachTeamAssignmentController;
use App\Http\Controllers\Api\Sport\SportAttendanceController;
use App\Http\Controllers\Api\Sport\SportApprovalController;
use App\Http\Controllers\Api\Sport\SportCoachController;
use App\Http\Controllers\Api\Sport\SportCoachTeamController;
use App\Http\Controllers\Api\Sport\SportDashboardController;
use App\Http\Controllers\Api\Sport\SportDivisionController;
use App\Http\Controllers\Api\Sport\SportPlayingStyleController;
use App\Http\Controllers\Api\Sport\SportMatchController;
use App\Http\Controllers\Api\Sport\SportMatchEventController;
use App\Http\Controllers\Api\Sport\SportMatchSquadController;
use App\Http\Controllers\Api\Sport\SportPlayerController;
use App\Http\Controllers\Api\Sport\SportPlayerLifecycleController;
use App\Http\Controllers\Api\Sport\SportTeamController;
use App\Http\Controllers\Api\Sport\SportTeamRosterController;
use App\Http\Controllers\Api\Sport\SportTournamentController;
use App\Http\Controllers\Api\Sport\SportTournamentFixtureController;
use App\Http\Controllers\Api\Sport\SportTournamentGroupController;
use App\Http\Controllers\Api\Sport\SportTournamentKnockoutController;
use App\Http\Controllers\Api\Sport\SportTournamentResultController;
use App\Http\Controllers\Api\Sport\SportTournamentStatisticsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Assessment\AssessmentFormTemplateController;
use App\Http\Controllers\Api\Assessment\AssessmentFormSectionController;
use App\Http\Controllers\Api\Assessment\AssessmentQuestionController;
use App\Http\Controllers\Api\Assessment\AssessmentScoringController;
use App\Http\Controllers\Api\Assessment\AssessmentSubmissionController;
use App\Http\Controllers\Api\Assessment\AssessmentPrintTemplateController;
use App\Http\Controllers\Api\Assessment\AssessmentQuestionTypeController;
use App\Http\Controllers\Api\Assessment\AssessmentReportController;
use App\Http\Controllers\Api\Assessment\AssessmentAuditLogController;
use App\Http\Controllers\Api\Dsam\AcademicYearController;
use App\Http\Controllers\Api\Dsam\DashboardController as DsamDashboardController;
use App\Http\Controllers\Api\Dsam\FormSectionController;
use App\Http\Controllers\Api\Dsam\FormTemplateController;
use App\Http\Controllers\Api\Dsam\OrganizationController as DsamOrganizationController;
use App\Http\Controllers\Api\Dsam\QuestionController;
use App\Http\Controllers\Api\Dsam\QuestionOptionController;
use App\Http\Controllers\Api\Dsam\QuestionTypeController;
use App\Http\Controllers\Api\Dsam\SchoolController as DsamSchoolController;
use App\Http\Controllers\Api\Dsam\StudentHistoryController;
use App\Http\Controllers\Api\Dsam\StudentProfileController;
use App\Http\Controllers\Api\Dsam\SubmissionController as DsamSubmissionController;
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
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::patch('change-password', [AuthController::class, 'changePassword']);

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

    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('roles/{role}/permissions', [RoleController::class, 'permissions']);

    Route::middleware(['permission:users:read'])->group(function (): void {
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::get('super-admin/users/{user}', [UserController::class, 'show']);
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

    /*
    |--------------------------------------------------------------------------
    | Notification Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::patch('{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('{id}/dismiss', [NotificationController::class, 'dismiss']);
        Route::patch('{id}/undismiss', [NotificationController::class, 'undismiss']);
    });

    Route::get('audit-logs', [AuditLogController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Preschool Routes
    |----------------------------------------------------------------------
    */

    // Preschool stays split into admin setup, teacher operations, assessment
    // output, and guardian/governance records. Teachers never get setup or
    // payment administration privileges here; the controllers enforce that.
    Route::prefix('preschool')->group(function (): void {
        Route::get('dashboard', [PreschoolDashboardController::class, 'index']);

        Route::get('teachers', [PreschoolTeacherController::class, 'index']);
        Route::post('teachers', [PreschoolTeacherController::class, 'store']);
        Route::get('teachers/{id}', [PreschoolTeacherController::class, 'show']);
        Route::put('teachers/{id}', [PreschoolTeacherController::class, 'update']);
        Route::delete('teachers/{id}', [PreschoolTeacherController::class, 'destroy']);
        Route::get('teacher/my-students', [PreschoolTeacherController::class, 'myStudents']);
        Route::get('teacher/my-classes', [PreschoolTeacherController::class, 'myClasses']);
        Route::get('teacher/attendance', [PreschoolTeacherController::class, 'myAttendance']);

        Route::get('classes', [PreschoolClassController::class, 'index']);
        Route::post('classes', [PreschoolClassController::class, 'store']);
        Route::get('classes/{id}', [PreschoolClassController::class, 'show']);
        Route::put('classes/{id}', [PreschoolClassController::class, 'update']);
        Route::delete('classes/{id}', [PreschoolClassController::class, 'destroy']);

        Route::get('students', [PreschoolStudentController::class, 'index']);
        Route::post('students', [PreschoolStudentController::class, 'store']);
        Route::get('students/{id}', [PreschoolStudentController::class, 'show']);
        Route::put('students/{id}', [PreschoolStudentController::class, 'update']);
        Route::delete('students/{id}', [PreschoolStudentController::class, 'destroy']);

        // Guardian records stay normalized and admin-managed while teachers
        // only read student-specific guardian/contact views.
        Route::get('guardians', [PreschoolGuardianController::class, 'index']);
        Route::post('guardians', [PreschoolGuardianController::class, 'store']);
        // Integrity checks stay ahead of the dynamic guardian route so these
        // staff-only diagnostics never get mistaken for a guardian id.
        Route::get('guardians/duplicates', [PreschoolGuardianIntegrityController::class, 'duplicates']);
        Route::get('guardians/consistency-report', [PreschoolGuardianIntegrityController::class, 'consistencyReport']);

        // Remediation routes are static and must sit before {guardian} so the
        // segment "remediation" is never resolved as a guardian model binding.
        Route::get('guardians/remediation/logs', [PreschoolGuardianRemediationController::class, 'logs']);
        Route::post('guardians/remediation/mark-reviewed', [PreschoolGuardianRemediationController::class, 'markReviewed']);
        Route::post('guardians/remediation/set-primary', [PreschoolGuardianRemediationController::class, 'setPrimary']);
        Route::post('guardians/remediation/clear-invalid-primary', [PreschoolGuardianRemediationController::class, 'clearInvalidPrimary']);
        Route::post('guardians/remediation/clear-invalid-emergency-contact', [PreschoolGuardianRemediationController::class, 'clearInvalidEmergencyContact']);
        Route::post('guardians/remediation/reconcile-legacy-fields', [PreschoolGuardianRemediationController::class, 'reconcileLegacyFields']);
        Route::post('guardians/remediation/archive-duplicate-candidate', [PreschoolGuardianRemediationController::class, 'archiveDuplicateCandidate']);
        Route::post('guardians/remediation/archive-orphan-guardian', [PreschoolGuardianRemediationController::class, 'archiveOrphanGuardian']);

        // Governance routes sit under a dedicated segment so they never collide
        // with {guardian} model binding and form a self-contained workflow area.
        Route::post('guardians/governance/sync', [PreschoolGuardianGovernanceController::class, 'sync']);
        Route::get('guardians/governance/dashboard-summary', [PreschoolGuardianGovernanceController::class, 'dashboardSummary']);
        Route::get('guardians/governance/stale-issues', [PreschoolGuardianGovernanceController::class, 'staleIssues']);
        Route::get('guardians/governance/recurring-issues', [PreschoolGuardianGovernanceController::class, 'recurringIssues']);
        Route::get('guardians/governance/issues', [PreschoolGuardianGovernanceController::class, 'index']);
        Route::get('guardians/governance/issues/{issue}', [PreschoolGuardianGovernanceController::class, 'show']);
        Route::post('guardians/governance/issues/{issue}/acknowledge', [PreschoolGuardianGovernanceController::class, 'acknowledge']);
        Route::post('guardians/governance/issues/{issue}/assign', [PreschoolGuardianGovernanceController::class, 'assign']);
        Route::post('guardians/governance/issues/{issue}/resolve', [PreschoolGuardianGovernanceController::class, 'resolve']);
        Route::post('guardians/governance/issues/{issue}/dismiss', [PreschoolGuardianGovernanceController::class, 'dismiss']);
        Route::get('guardian-communications', [PreschoolGuardianCommunicationController::class, 'index']);
        Route::get('students/{student}/guardian-communications', [PreschoolGuardianCommunicationController::class, 'studentTimeline']);
        Route::get('guardians/{guardian}/communications', [PreschoolGuardianCommunicationController::class, 'guardianTimeline']);
        Route::post('students/{student}/guardian-communications', [PreschoolGuardianCommunicationController::class, 'store']);
        Route::post('guardian-communications/{communication}/sent', [PreschoolGuardianCommunicationController::class, 'markSent']);
        Route::post('guardian-communications/{communication}/acknowledge', [PreschoolGuardianCommunicationController::class, 'acknowledge']);
        Route::post('guardian-communications/{communication}/cancel', [PreschoolGuardianCommunicationController::class, 'cancel']);
        Route::get('guardians/{guardian}', [PreschoolGuardianController::class, 'show']);
        Route::patch('guardians/{guardian}', [PreschoolGuardianController::class, 'update']);
        Route::delete('guardians/{guardian}', [PreschoolGuardianController::class, 'destroy']);

        // Guardian portal accounts are managed separately from guardian records
        // so activation and revocation do not disturb the contact history.
        Route::get('guardian-portal/accounts', [PreschoolGuardianPortalController::class, 'index']);
        Route::post('guardians/{guardian}/portal/invite', [PreschoolGuardianPortalController::class, 'invite']);
        Route::post('guardian-portal/{account}/revoke', [PreschoolGuardianPortalController::class, 'revoke']);

        Route::get('students/{student}/guardians', [PreschoolStudentGuardianController::class, 'index']);
        Route::post('students/{student}/guardians', [PreschoolStudentGuardianController::class, 'store']);
        // Pair-based routes keep the student and guardian identifiers visible so
        // staff can manage the exact relationship without relying on a hidden id.
        Route::put('students/{student}/guardians/{guardian}', [PreschoolStudentGuardianController::class, 'updateByGuardian']);
        Route::post('students/{student}/guardians/{guardian}/set-primary', [PreschoolStudentGuardianController::class, 'setPrimary']);
        Route::post('students/{student}/guardians/{guardian}/archive', [PreschoolStudentGuardianController::class, 'archiveByGuardian']);
        Route::post('students/{student}/guardians/{guardian}/restore', [PreschoolStudentGuardianController::class, 'restoreByGuardian']);
        Route::patch('student-guardians/{relationship}', [PreschoolStudentGuardianController::class, 'update']);
        Route::delete('student-guardians/{relationship}', [PreschoolStudentGuardianController::class, 'destroy']);
        Route::get('students/{student}/emergency-contacts', [PreschoolStudentGuardianController::class, 'emergencyContacts']);
        // Health and medical records stay anchored to the student route so the
        // admin dashboard, student profile, and teacher workflow all share the
        // same health domain instead of introducing a separate module shell.
        Route::prefix('students/{student}/health')->group(function () {
            Route::get('summary', [PreschoolStudentHealthController::class, 'summary']);
            Route::get('medical-profile', [PreschoolStudentHealthController::class, 'medicalProfile']);
            Route::put('medical-profile', [PreschoolStudentHealthController::class, 'upsertMedicalProfile']);
            Route::get('allergies', [PreschoolStudentHealthController::class, 'allergies']);
            Route::post('allergies', [PreschoolStudentHealthController::class, 'storeAllergy']);
            Route::put('allergies/{allergy}', [PreschoolStudentHealthController::class, 'updateAllergy']);
            Route::delete('allergies/{allergy}', [PreschoolStudentHealthController::class, 'destroyAllergy']);
            Route::get('vaccinations', [PreschoolStudentHealthController::class, 'vaccinations']);
            Route::post('vaccinations', [PreschoolStudentHealthController::class, 'storeVaccination']);
            Route::put('vaccinations/{vaccination}', [PreschoolStudentHealthController::class, 'updateVaccination']);
            Route::delete('vaccinations/{vaccination}', [PreschoolStudentHealthController::class, 'destroyVaccination']);
            Route::get('medications', [PreschoolStudentHealthController::class, 'medications']);
            Route::post('medications', [PreschoolStudentHealthController::class, 'storeMedication']);
            Route::put('medications/{medication}', [PreschoolStudentHealthController::class, 'updateMedication']);
            Route::delete('medications/{medication}', [PreschoolStudentHealthController::class, 'destroyMedication']);
            Route::get('incidents', [PreschoolStudentHealthController::class, 'incidents']);
            Route::post('incidents', [PreschoolStudentHealthController::class, 'storeIncident']);
            Route::put('incidents/{incident}', [PreschoolStudentHealthController::class, 'updateIncident']);
            Route::delete('incidents/{incident}', [PreschoolStudentHealthController::class, 'destroyIncident']);
            Route::get('emergency-contacts', [PreschoolStudentHealthController::class, 'healthContacts']);
            Route::post('emergency-contacts', [PreschoolStudentHealthController::class, 'storeHealthContact']);
            Route::put('emergency-contacts/{contact}', [PreschoolStudentHealthController::class, 'updateHealthContact']);
            Route::delete('emergency-contacts/{contact}', [PreschoolStudentHealthController::class, 'destroyHealthContact']);
        Route::get('check-logs', [PreschoolStudentHealthController::class, 'healthChecks']);
        Route::post('check-logs', [PreschoolStudentHealthController::class, 'storeHealthCheck']);
        Route::delete('check-logs/{check}', [PreschoolStudentHealthController::class, 'destroyHealthCheck']);
        });

        // Health alerts have their own lifecycle routes so the dashboard,
        // student profile, and teacher workflow can share one canonical alert
        // stack without exposing the underlying alert model directly in views.
        Route::get('health/alerts', [PreschoolHealthAlertController::class, 'alerts']);
        Route::get('health/dashboard-summary', [PreschoolHealthAlertController::class, 'dashboardSummary']);
        Route::get('health/alerts/{alert}', [PreschoolHealthAlertController::class, 'show']);
        Route::post('health/alerts/{alert}/acknowledge', [PreschoolHealthAlertController::class, 'acknowledge']);
        Route::post('health/alerts/{alert}/assign', [PreschoolHealthAlertController::class, 'assign']);
        Route::post('health/alerts/{alert}/status', [PreschoolHealthAlertController::class, 'status']);
        Route::post('health/alerts/{alert}/resolve', [PreschoolHealthAlertController::class, 'resolve']);
        Route::post('health/alerts/{alert}/close', [PreschoolHealthAlertController::class, 'close']);
        Route::get('students/{student}/health/alerts', [PreschoolHealthAlertController::class, 'studentAlerts']);
        Route::get('students/{student}/health/audit-logs', [PreschoolStudentHealthAuditController::class, 'index']);

        Route::get('attendance', [PreschoolAttendanceController::class, 'index']);
        Route::post('attendance', [PreschoolAttendanceController::class, 'store']);
        Route::put('attendance/{id}', [PreschoolAttendanceController::class, 'update']);
        // Admin-only attendance review and correction routes stay under the same
        // attendance prefix so staff can manage records and missing attendance
        // without needing a separate admin segment or duplicating the controller.
        Route::get('attendance/summary', [PreschoolAttendanceController::class, 'summary']);
        Route::get('attendance/missing', [PreschoolAttendanceController::class, 'missing']);
        Route::get('attendance/class-summary', [PreschoolAttendanceController::class, 'classSummary']);
        Route::post('attendance/{id}/review', [PreschoolAttendanceController::class, 'review']);
        Route::post('attendance/{id}/flag', [PreschoolAttendanceController::class, 'flag']);

        // Enrollment routes form a complete admission workflow: draft â†’ submitted
        // â†’ under_review â†’ approved/waitlisted/rejected â†’ enrolled. Static segments
        // (summary, {application}/submit, etc.) sit before the model binding so
        // they are never resolved as application IDs.
        Route::get('enrollments', [PreschoolEnrollmentController::class, 'index']);
        Route::post('enrollments', [PreschoolEnrollmentController::class, 'store']);
        Route::get('enrollments/summary', [PreschoolEnrollmentController::class, 'summary']);
        Route::get('enrollments/{application}', [PreschoolEnrollmentController::class, 'show']);
        Route::patch('enrollments/{application}', [PreschoolEnrollmentController::class, 'update']);
        Route::post('enrollments/{application}/submit', [PreschoolEnrollmentController::class, 'submit']);
        Route::post('enrollments/{application}/review', [PreschoolEnrollmentController::class, 'review']);
        Route::post('enrollments/{application}/approve', [PreschoolEnrollmentController::class, 'approve']);
        Route::post('enrollments/{application}/reject', [PreschoolEnrollmentController::class, 'reject']);
        Route::post('enrollments/{application}/waitlist', [PreschoolEnrollmentController::class, 'waitlist']);
        Route::post('enrollments/{application}/cancel', [PreschoolEnrollmentController::class, 'cancel']);
        Route::post('enrollments/{application}/enroll', [PreschoolEnrollmentController::class, 'enroll']);
        Route::patch('enrollments/{application}/documents/{document}', [PreschoolEnrollmentController::class, 'updateDocument']);

        // Assessment routes stay alongside the rest of Preschool CRUD so staff
        // can track progress without a separate module or report workflow.
        Route::get('assessment-categories', [PreschoolAssessmentCategoryController::class, 'index']);
        Route::get('students/{student}/assessments', [PreschoolStudentAssessmentController::class, 'index']);
        Route::post('students/{student}/assessments', [PreschoolStudentAssessmentController::class, 'store']);
        Route::put('assessments/{assessment}', [PreschoolStudentAssessmentController::class, 'update']);
        Route::post('assessments/{assessment}/finalize', [PreschoolStudentAssessmentController::class, 'finalize']);
        Route::post('assessments/{assessment}/archive', [PreschoolStudentAssessmentController::class, 'archive']);
        Route::get('students/{student}/progress-summary', [PreschoolProgressSummaryController::class, 'index']);
        // Reports stay on finalized assessment data so the frontend can render
        // stable summary screens without inventing a separate reporting store.
        Route::get('report-periods', [PreschoolReportPeriodController::class, 'index']);
        Route::post('report-periods', [PreschoolReportPeriodController::class, 'store']);
        Route::patch('report-periods/{reportPeriod}', [PreschoolReportPeriodController::class, 'update']);
        Route::patch('report-periods/{reportPeriod}/activate', [PreschoolReportPeriodController::class, 'activate']);
        Route::patch('report-periods/{reportPeriod}/finalize', [PreschoolReportPeriodController::class, 'finalize']);
        Route::patch('report-periods/{reportPeriod}/lock', [PreschoolReportPeriodController::class, 'lock']);
        Route::patch('report-periods/{reportPeriod}/archive', [PreschoolReportPeriodController::class, 'archive']);
        Route::get('lifecycle-audit-logs', [PreschoolLifecycleAuditController::class, 'index']);
        Route::get('lifecycle-audit-analytics', [PreschoolLifecycleAuditController::class, 'analytics']);
        // Snapshot archive routes stay admin-only so immutable report history can
        // be reviewed and exported without exposing a new teacher workflow.
        Route::get('report-snapshots', [PreschoolReportSnapshotController::class, 'index']);
        Route::get('report-snapshots/analytics', [PreschoolReportSnapshotController::class, 'analytics']);
        Route::get('report-snapshots/export.csv', [PreschoolReportSnapshotController::class, 'exportCsv']);
        Route::get('report-snapshots/{snapshot}', [PreschoolReportSnapshotController::class, 'show']);
        // Export governance stays admin-only so export records, comparison
        // tooling, and timeline review remain institutional audit surfaces.
        Route::get('report-exports', [PreschoolExportGovernanceController::class, 'index']);
        Route::get('report-exports/analytics', [PreschoolExportGovernanceController::class, 'analytics']);
        Route::get('report-exports/{exportRecord}', [PreschoolExportGovernanceController::class, 'show']);
        Route::get('report-exports/{exportRecord}/download.csv', [PreschoolExportGovernanceController::class, 'downloadCsv']);
        Route::get('report-comparisons/options', [PreschoolExportGovernanceController::class, 'comparisonOptions']);
        Route::post('report-comparisons', [PreschoolExportGovernanceController::class, 'compare']);
        Route::get('institutional-timeline', [PreschoolExportGovernanceController::class, 'timeline']);
        Route::get('governance-review', [PreschoolInstitutionalGovernanceController::class, 'review']);
        Route::get('governance-review/analytics', [PreschoolInstitutionalGovernanceController::class, 'analytics']);
        Route::get('institutional-reconstruction', [PreschoolInstitutionalGovernanceController::class, 'reconstruct']);
        Route::get('institutional-reconstruction/{context}', [PreschoolInstitutionalGovernanceController::class, 'show']);
        Route::get('institutional-replay', [PreschoolInstitutionalGovernanceController::class, 'replay']);
        // Governance diff and institutional integrity review stay admin-only so
        // historical comparisons can be reviewed without adding a new staff
        // write surface or duplicating reconstruction logic.
        Route::get('governance-diff/summary', [PreschoolGovernanceDiffController::class, 'summary']);
        Route::get('governance-diff', [PreschoolGovernanceDiffController::class, 'compare']);
        Route::get('integrity-review', [PreschoolGovernanceDiffController::class, 'integrityReview']);
        Route::get('integrity-review/{context}', [PreschoolGovernanceDiffController::class, 'showIntegrityReview']);
        Route::post('integrity-review/{context}', [PreschoolGovernanceDiffController::class, 'review']);
        // Governance cases turn diff and integrity findings into owned review
        // work items so admins can escalate and resolve institutional risks
        // without mutating snapshots or live report data.
        Route::get('governance-cases', [PreschoolGovernanceCaseController::class, 'index']);
        Route::get('governance-cases/assignees', [PreschoolGovernanceCaseController::class, 'assignees']);
        Route::post('governance-cases', [PreschoolGovernanceCaseController::class, 'store']);
        Route::get('governance-cases/{case}', [PreschoolGovernanceCaseController::class, 'show']);
        Route::patch('governance-cases/{case}', [PreschoolGovernanceCaseController::class, 'update']);
        Route::post('governance-cases/{case}/assign', [PreschoolGovernanceCaseController::class, 'assign']);
        Route::post('governance-cases/{case}/evidence', [PreschoolGovernanceCaseController::class, 'evidence']);
        Route::post('governance-cases/{case}/escalate', [PreschoolGovernanceCaseController::class, 'escalate']);
        Route::post('governance-cases/{case}/resolve', [PreschoolGovernanceCaseController::class, 'resolve']);
        Route::post('governance-cases/{case}/close', [PreschoolGovernanceCaseController::class, 'close']);
        Route::post('governance-cases/{case}/reopen', [PreschoolGovernanceCaseController::class, 'reopen']);
        Route::get('settings/backbone', [PreschoolSettingsBackboneController::class, 'show']);
        Route::patch('settings/backbone', [PreschoolSettingsBackboneController::class, 'update']);
        // Academic lifecycle records stay admin-only so the year/term backbone
        // can drive attendance, schedules, assignments, and reports without
        // turning settings into a monolithic write path.
        Route::get('academic-lifecycle', [PreschoolAcademicLifecycleController::class, 'index']);
        Route::post('academic-years', [PreschoolAcademicLifecycleController::class, 'storeAcademicYear']);
        Route::patch('academic-years/{academicYear}', [PreschoolAcademicLifecycleController::class, 'updateAcademicYear']);
        Route::patch('academic-years/{academicYear}/activate', [PreschoolAcademicLifecycleController::class, 'activateAcademicYear']);
        Route::patch('academic-years/{academicYear}/close', [PreschoolAcademicLifecycleController::class, 'closeAcademicYear']);
        Route::post('terms', [PreschoolAcademicLifecycleController::class, 'storeTerm']);
        Route::patch('terms/{term}', [PreschoolAcademicLifecycleController::class, 'updateTerm']);
        Route::patch('terms/{term}/activate', [PreschoolAcademicLifecycleController::class, 'activateTerm']);
        Route::patch('terms/{term}/close', [PreschoolAcademicLifecycleController::class, 'closeTerm']);
        Route::get('students/{student}/reports', [PreschoolStudentReportController::class, 'index']);
        Route::get('students/{student}/reports/{period}', [PreschoolStudentReportController::class, 'show']);
        Route::get('classes/{class}/reports', [PreschoolClassroomReportController::class, 'index']);
        Route::get('classes/{class}/reports/{period}', [PreschoolClassroomReportController::class, 'show']);

        // Weekly schedules stay isolated from attendance and reporting because
        // timetable conflicts need their own validation and read-only views.
        Route::get('schedules', [PreschoolScheduleController::class, 'index']);
        Route::post('schedules', [PreschoolScheduleController::class, 'store']);
        Route::get('schedules/{schedule}', [PreschoolScheduleController::class, 'show']);
        Route::patch('schedules/{schedule}', [PreschoolScheduleController::class, 'update']);
        Route::delete('schedules/{schedule}', [PreschoolScheduleController::class, 'destroy']);
        Route::get('classes/{class}/schedule', [PreschoolTeacherScheduleController::class, 'classSchedule']);
        Route::get('teachers/{teacher}/schedule', [PreschoolTeacherScheduleController::class, 'teacherSchedule']);
        Route::get('me/schedule', [PreschoolTeacherScheduleController::class, 'meSchedule']);

        Route::get('payments', [PreschoolPaymentController::class, 'index']);
        Route::post('payments', [PreschoolPaymentController::class, 'store']);
        Route::get('payments/{id}', [PreschoolPaymentController::class, 'show']);
        Route::put('payments/{id}', [PreschoolPaymentController::class, 'update']);
        Route::delete('payments/{id}', [PreschoolPaymentController::class, 'destroy']);
        Route::post('payments/{payment}/receipt', [PreschoolReceiptController::class, 'store']);

        Route::get('invoices', [PreschoolInvoiceController::class, 'index']);
        Route::post('invoices', [PreschoolInvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [PreschoolInvoiceController::class, 'show']);
        Route::put('invoices/{invoice}', [PreschoolInvoiceController::class, 'update']);
        Route::post('invoices/{invoice}/issue', [PreschoolInvoiceController::class, 'issue']);
        Route::post('invoices/{invoice}/cancel', [PreschoolInvoiceController::class, 'cancel']);
        Route::post('invoices/{invoice}/overdue', [PreschoolInvoiceController::class, 'overdue']);
        Route::get('invoices/{invoice}/print', [PreschoolInvoiceController::class, 'print']);
        Route::get('students/{student}/invoices', [PreschoolInvoiceController::class, 'studentInvoices']);
        Route::get('students/{student}/payment-summary', [PreschoolInvoiceController::class, 'studentPaymentSummary']);
        Route::get('receipts/{receipt}', [PreschoolReceiptController::class, 'show']);
        Route::get('receipts/{receipt}/print', [PreschoolReceiptController::class, 'print']);

        // Classroom resources are readable by all preschool staff and writable
        // by admins only â€” the controller enforces both access tiers.
        Route::get('classroom-resources', [PreschoolClassroomResourceController::class, 'index']);
        Route::post('classroom-resources', [PreschoolClassroomResourceController::class, 'store']);
        Route::get('classroom-resources/{id}', [PreschoolClassroomResourceController::class, 'show']);
        Route::put('classroom-resources/{id}', [PreschoolClassroomResourceController::class, 'update']);
        Route::delete('classroom-resources/{id}', [PreschoolClassroomResourceController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Scholarship Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('scholarship')->group(function (): void {
        Route::get('dashboard', [ScholarshipDashboardController::class, 'index']);
        Route::get('reviewer/dashboard', [ScholarshipDashboardController::class, 'reviewerDashboard']);
        Route::get('reviewer/my-applications', [ScholarshipApplicationController::class, 'reviewerApplications']);

        Route::get('students', [ScholarshipStudentController::class, 'index']);
        Route::post('students', [ScholarshipStudentController::class, 'store']);
        Route::get('students/{id}', [ScholarshipStudentController::class, 'show']);
        Route::put('students/{id}', [ScholarshipStudentController::class, 'update']);
        Route::delete('students/{id}', [ScholarshipStudentController::class, 'destroy']);

        Route::get('applications', [ScholarshipApplicationController::class, 'index']);
        Route::post('applications', [ScholarshipApplicationController::class, 'store']);
        Route::get('applications/{id}', [ScholarshipApplicationController::class, 'show']);
        Route::put('applications/{id}', [ScholarshipApplicationController::class, 'update']);
        Route::delete('applications/{id}', [ScholarshipApplicationController::class, 'destroy']);
        Route::patch('applications/{id}/approve', [ScholarshipApplicationController::class, 'approve']);
        Route::patch('applications/{id}/reject', [ScholarshipApplicationController::class, 'reject']);
        Route::patch('applications/{id}/status', [ScholarshipApplicationController::class, 'updateStatus']);

        Route::get('reviews', [ScholarshipReviewController::class, 'index']);
        Route::post('reviews', [ScholarshipReviewController::class, 'store']);
        Route::put('reviews/{id}', [ScholarshipReviewController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | English Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('english')->group(function (): void {
        Route::get('dashboard', [EnglishDashboardController::class, 'index']);
        Route::get('teacher/dashboard', [EnglishTeacherController::class, 'dashboard']);
        Route::get('teacher/classes', [EnglishTeacherController::class, 'classes']);
        Route::get('teacher/students', [EnglishTeacherController::class, 'students']);
        Route::get('teacher/tasks', [EnglishTeacherController::class, 'tasks']);

        Route::get('teachers', [EnglishTeacherController::class, 'index']);
        Route::post('teachers', [EnglishTeacherController::class, 'store']);
        Route::get('teachers/{id}', [EnglishTeacherController::class, 'show']);
        Route::put('teachers/{id}', [EnglishTeacherController::class, 'update']);
        Route::delete('teachers/{id}', [EnglishTeacherController::class, 'destroy']);

        Route::get('students', [EnglishStudentController::class, 'index']);
        Route::post('students', [EnglishStudentController::class, 'store']);
        Route::get('students/{id}', [EnglishStudentController::class, 'show']);
        Route::put('students/{id}', [EnglishStudentController::class, 'update']);
        Route::delete('students/{id}', [EnglishStudentController::class, 'destroy']);

        Route::get('classes', [EnglishClassController::class, 'index']);
        Route::post('classes', [EnglishClassController::class, 'store']);
        Route::get('classes/{id}', [EnglishClassController::class, 'show']);
        Route::put('classes/{id}', [EnglishClassController::class, 'update']);
        Route::delete('classes/{id}', [EnglishClassController::class, 'destroy']);

        Route::get('tasks', [EnglishTaskController::class, 'index']);
        Route::post('tasks', [EnglishTaskController::class, 'store']);
        Route::get('tasks/{id}', [EnglishTaskController::class, 'show']);
        Route::put('tasks/{id}', [EnglishTaskController::class, 'update']);
        Route::delete('tasks/{id}', [EnglishTaskController::class, 'destroy']);

        Route::get('submissions', [EnglishSubmissionController::class, 'index']);
        Route::post('submissions', [EnglishSubmissionController::class, 'store']);
        Route::put('submissions/{id}', [EnglishSubmissionController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sport Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('sport')->group(function (): void {
        Route::get('dashboard', [SportDashboardController::class, 'index']);
        Route::get('coach/dashboard', [SportDashboardController::class, 'coach']);

        Route::get('tournaments', [SportTournamentController::class, 'index']);
        Route::post('tournaments', [SportTournamentController::class, 'store']);
        Route::get('tournaments/{id}', [SportTournamentController::class, 'show']);
        Route::put('tournaments/{id}', [SportTournamentController::class, 'update']);
        Route::delete('tournaments/{id}', [SportTournamentController::class, 'destroy']);
        Route::post('tournaments/{id}/teams', [SportTournamentController::class, 'addTeam']);
        Route::delete('tournaments/{id}/teams/{teamId}', [SportTournamentController::class, 'removeTeam']);
        Route::get('tournaments/{id}/standings', [SportTournamentController::class, 'standings']);
        Route::post('tournaments/{id}/recalculate-standings', [SportTournamentController::class, 'recalculateStandings']);

        Route::get('tournaments/{id}/groups', [SportTournamentGroupController::class, 'index']);
        Route::post('tournaments/{id}/groups/draw', [SportTournamentGroupController::class, 'draw']);
        Route::post('tournaments/{id}/groups/finalize', [SportTournamentGroupController::class, 'finalize']);

        Route::get('tournaments/{id}/fixtures', [SportTournamentFixtureController::class, 'index']);
        Route::post('tournaments/{id}/fixtures/generate', [SportTournamentFixtureController::class, 'generate']);

        Route::get('tournaments/{id}/results', [SportTournamentResultController::class, 'index']);
        Route::get('tournaments/{id}/results/{matchId}', [SportTournamentResultController::class, 'show']);
        Route::put('tournaments/{id}/results/{matchId}', [SportTournamentResultController::class, 'update']);
        Route::post('tournaments/{id}/results/{matchId}/events', [SportTournamentResultController::class, 'storeEvent']);

        Route::get('tournaments/{id}/statistics', [SportTournamentStatisticsController::class, 'index']);

        Route::get('tournaments/{id}/knockout', [SportTournamentKnockoutController::class, 'index']);
        Route::post('tournaments/{id}/knockout/generate', [SportTournamentKnockoutController::class, 'generate']);

        Route::get('coaches', [SportCoachController::class, 'index']);
        Route::post('coaches', [SportCoachController::class, 'store']);
        Route::get('coaches/{id}', [SportCoachController::class, 'show']);
        Route::put('coaches/{id}', [SportCoachController::class, 'update']);
        Route::delete('coaches/{id}', [SportCoachController::class, 'destroy']);
        Route::get('attendance', [SportAttendanceController::class, 'index']);
        Route::post('attendance', [SportAttendanceController::class, 'store']);
        Route::put('attendance/{id}', [SportAttendanceController::class, 'update']);
        Route::get('coach/teams', [SportCoachTeamController::class, 'index']);
        Route::get('coach/teams/{team}', [SportCoachTeamController::class, 'show']);
        Route::post('coach/teams/{team}/players', [SportCoachTeamController::class, 'storePlayer']);
        Route::get('coach/matches', [SportCoachController::class, 'matches']);
        Route::post('coach/matches', [SportCoachTeamController::class, 'storeMatch']);

        Route::get('admin/coach-team-assignments', [SportAdminCoachTeamAssignmentController::class, 'index']);
        Route::post('admin/coach-team-assignments', [SportAdminCoachTeamAssignmentController::class, 'store']);
        Route::patch('admin/coach-team-assignments/{id}', [SportAdminCoachTeamAssignmentController::class, 'update']);
        Route::delete('admin/coach-team-assignments/{id}', [SportAdminCoachTeamAssignmentController::class, 'destroy']);

        Route::get('admin/pending-players', [SportApprovalController::class, 'pendingPlayers']);
        Route::post('admin/players/{id}/approve', [SportApprovalController::class, 'approvePlayer']);
        Route::post('admin/players/{id}/reject', [SportApprovalController::class, 'rejectPlayer']);
        Route::get('admin/pending-matches', [SportApprovalController::class, 'pendingMatches']);
        Route::post('admin/matches/{id}/approve', [SportApprovalController::class, 'approveMatch']);
        Route::post('admin/matches/{id}/reject', [SportApprovalController::class, 'rejectMatch']);

        Route::get('teams', [SportTeamController::class, 'index']);
        Route::post('teams', [SportTeamController::class, 'store']);
        Route::get('teams/{id}', [SportTeamController::class, 'show']);
        Route::put('teams/{id}', [SportTeamController::class, 'update']);
        Route::delete('teams/{id}', [SportTeamController::class, 'destroy']);
        Route::get('teams/{team}/roster', [SportTeamRosterController::class, 'index']);
        Route::post('teams/{team}/roster', [SportTeamRosterController::class, 'store']);
        Route::patch('roster/{membership}', [SportTeamRosterController::class, 'update']);
        Route::delete('roster/{membership}', [SportTeamRosterController::class, 'destroy']);

        Route::get('divisions', [SportDivisionController::class, 'index']);
        Route::post('divisions', [SportDivisionController::class, 'store']);
        Route::get('divisions/{id}', [SportDivisionController::class, 'show']);
        Route::put('divisions/{id}', [SportDivisionController::class, 'update']);
        Route::delete('divisions/{id}', [SportDivisionController::class, 'destroy']);

        Route::get('playing-styles', [SportPlayingStyleController::class, 'index']);
        Route::post('playing-styles', [SportPlayingStyleController::class, 'store']);
        Route::get('playing-styles/{id}', [SportPlayingStyleController::class, 'show']);
        Route::put('playing-styles/{id}', [SportPlayingStyleController::class, 'update']);
        Route::delete('playing-styles/{id}', [SportPlayingStyleController::class, 'destroy']);

        Route::get('players', [SportPlayerController::class, 'index']);
        Route::post('players', [SportPlayerController::class, 'store']);
        Route::get('players/{id}', [SportPlayerController::class, 'show']);
        Route::put('players/{id}', [SportPlayerController::class, 'update']);
        Route::delete('players/{id}', [SportPlayerController::class, 'destroy']);
        Route::get('players/{player}/history', [SportPlayerLifecycleController::class, 'history']);
        Route::patch('players/{player}/status', [SportPlayerLifecycleController::class, 'updateStatus']);
        Route::patch('players/{player}/injury', [SportPlayerLifecycleController::class, 'injury']);
        Route::patch('players/{player}/suspension', [SportPlayerLifecycleController::class, 'suspension']);
        Route::patch('players/{player}/release', [SportPlayerLifecycleController::class, 'release']);
        Route::patch('players/{player}/archive', [SportPlayerLifecycleController::class, 'archive']);

        Route::get('matches', [SportMatchController::class, 'index']);
        Route::post('matches', [SportMatchController::class, 'store']);
        Route::get('matches/{id}', [SportMatchController::class, 'show']);
        Route::put('matches/{id}', [SportMatchController::class, 'update']);
        Route::delete('matches/{id}', [SportMatchController::class, 'destroy']);
        Route::patch('matches/{id}/status', [SportMatchController::class, 'updateStatus']);

        Route::get('matches/{match}/teams/{team}/eligibility', [SportMatchSquadController::class, 'eligibility']);
        Route::get('matches/{match}/squads', [SportMatchSquadController::class, 'index']);
        Route::get('matches/{match}/teams/{team}/squad', [SportMatchSquadController::class, 'show']);
        Route::post('matches/{match}/teams/{team}/squad', [SportMatchSquadController::class, 'store']);
        Route::patch('match-squads/{squad}', [SportMatchSquadController::class, 'update']);
        Route::post('match-squads/{squad}/submit', [SportMatchSquadController::class, 'submit']);
        Route::post('match-squads/{squad}/approve', [SportMatchSquadController::class, 'approve']);
        Route::post('match-squads/{squad}/lock', [SportMatchSquadController::class, 'lock']);

        Route::get('matches/{id}/events', [SportMatchEventController::class, 'index']);
        Route::post('matches/{id}/events', [SportMatchEventController::class, 'store']);
        Route::patch('match-events/{id}', [SportMatchEventController::class, 'update']);
        Route::delete('match-events/{id}', [SportMatchEventController::class, 'destroy']);
        Route::put('events/{id}', [SportMatchEventController::class, 'update']);
        Route::delete('events/{id}', [SportMatchEventController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Assessment Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('assessment')->group(function (): void {
        // Question types (read-only reference data)
        Route::get('question-types', [AssessmentQuestionTypeController::class, 'index']);

        // Form templates
        Route::get('forms', [AssessmentFormTemplateController::class, 'index']);
        Route::post('forms', [AssessmentFormTemplateController::class, 'store']);
        Route::get('forms/{form}', [AssessmentFormTemplateController::class, 'show']);
        Route::put('forms/{form}', [AssessmentFormTemplateController::class, 'update']);
        Route::delete('forms/{form}', [AssessmentFormTemplateController::class, 'destroy']);
        Route::post('forms/{form}/publish', [AssessmentFormTemplateController::class, 'publish']);
        Route::post('forms/{form}/duplicate', [AssessmentFormTemplateController::class, 'duplicate']);
        Route::post('forms/{form}/archive', [AssessmentFormTemplateController::class, 'archive']);
        Route::post('forms/{form}/restore', [AssessmentFormTemplateController::class, 'restore']);
        Route::get('forms/{form}/versions', [AssessmentFormTemplateController::class, 'versions']);

        // Form sections
        Route::get('forms/{form}/sections', [AssessmentFormSectionController::class, 'index']);
        Route::post('forms/{form}/sections', [AssessmentFormSectionController::class, 'store']);
        Route::put('forms/{form}/sections/{section}', [AssessmentFormSectionController::class, 'update']);
        Route::delete('forms/{form}/sections/{section}', [AssessmentFormSectionController::class, 'destroy']);
        Route::post('forms/{form}/sections/reorder', [AssessmentFormSectionController::class, 'reorder']);

        // Questions
        Route::get('forms/{form}/questions', [AssessmentQuestionController::class, 'index']);
        Route::post('forms/{form}/questions', [AssessmentQuestionController::class, 'store']);
        Route::get('forms/{form}/questions/{question}', [AssessmentQuestionController::class, 'show']);
        Route::put('forms/{form}/questions/{question}', [AssessmentQuestionController::class, 'update']);
        Route::delete('forms/{form}/questions/{question}', [AssessmentQuestionController::class, 'destroy']);
        Route::post('forms/{form}/questions/{question}/duplicate', [AssessmentQuestionController::class, 'duplicate']);
        Route::post('forms/{form}/questions/reorder', [AssessmentQuestionController::class, 'reorder']);

        // Scoring
        Route::get('forms/{form}/scoring', [AssessmentScoringController::class, 'show']);
        Route::put('forms/{form}/scoring', [AssessmentScoringController::class, 'update']);

        // Print templates
        Route::get('print-templates', [AssessmentPrintTemplateController::class, 'index']);
        Route::post('print-templates', [AssessmentPrintTemplateController::class, 'store']);
        Route::post('print-templates/preview', [AssessmentPrintTemplateController::class, 'preview']);
        Route::get('print-templates/{printTemplate}', [AssessmentPrintTemplateController::class, 'show']);
        Route::put('print-templates/{printTemplate}', [AssessmentPrintTemplateController::class, 'update']);
        Route::delete('print-templates/{printTemplate}', [AssessmentPrintTemplateController::class, 'destroy']);

        // Submissions
        Route::get('submissions', [AssessmentSubmissionController::class, 'index']);
        Route::post('submissions', [AssessmentSubmissionController::class, 'store']);
        Route::get('submissions/{submission}', [AssessmentSubmissionController::class, 'show']);
        Route::put('submissions/{submission}', [AssessmentSubmissionController::class, 'update']);
        Route::post('submissions/{submission}/submit', [AssessmentSubmissionController::class, 'submit']);
        Route::post('submissions/{submission}/review', [AssessmentSubmissionController::class, 'review']);
        Route::post('submissions/{submission}/print', [AssessmentSubmissionController::class, 'print']);
        Route::delete('submissions/{submission}', [AssessmentSubmissionController::class, 'destroy']);

        // Reports
        Route::get('reports/dashboard', [AssessmentReportController::class, 'dashboard']);
        Route::get('reports/risk-distribution', [AssessmentReportController::class, 'riskDistribution']);
        Route::get('reports/submission-trend', [AssessmentReportController::class, 'submissionTrend']);
        Route::get('reports/export', [AssessmentReportController::class, 'export'])->name('assessment.reports.export');
        Route::get('reports/exports/{exportLog}', [AssessmentReportController::class, 'exportStatus'])->name('assessment.exports.status');
        Route::get('reports/exports/{exportLog}/download', [AssessmentReportController::class, 'downloadExport'])->name('assessment.exports.download');

        // Audit logs
        Route::get('audit-logs', [AssessmentAuditLogController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | DSAM â€” Dynamic Student Assessment Management
    |--------------------------------------------------------------------------
    */

    Route::prefix('dsam')->group(function (): void {
        Route::get('dashboard', [DsamDashboardController::class, 'index']);

        // Lookup
        Route::get('question-types', [QuestionTypeController::class, 'index']);

        // Organizations
        Route::apiResource('organizations', DsamOrganizationController::class);

        // Academic years
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::post('academic-years/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent']);

        // Schools
        Route::apiResource('schools', DsamSchoolController::class);

        // Form templates
        Route::get('forms', [FormTemplateController::class, 'index']);
        Route::post('forms', [FormTemplateController::class, 'store']);
        Route::get('forms/{dsamForm}', [FormTemplateController::class, 'show']);
        Route::put('forms/{dsamForm}', [FormTemplateController::class, 'update']);
        Route::delete('forms/{dsamForm}', [FormTemplateController::class, 'destroy']);
        Route::post('forms/{dsamForm}/publish', [FormTemplateController::class, 'publish']);
        Route::post('forms/{dsamForm}/archive', [FormTemplateController::class, 'archive']);
        Route::post('forms/{dsamForm}/duplicate', [FormTemplateController::class, 'duplicate']);
        Route::post('forms/{dsamForm}/new-version', [FormTemplateController::class, 'newVersion']);
        Route::get('forms/{dsamForm}/versions', [FormTemplateController::class, 'versions']);

        // Sections (nested under form)
        Route::get('forms/{dsamForm}/sections', [FormSectionController::class, 'index']);
        Route::post('forms/{dsamForm}/sections', [FormSectionController::class, 'store']);
        Route::put('forms/{dsamForm}/sections/{section}', [FormSectionController::class, 'update']);
        Route::delete('forms/{dsamForm}/sections/{section}', [FormSectionController::class, 'destroy']);
        Route::post('forms/{dsamForm}/sections/reorder', [FormSectionController::class, 'reorder']);

        // Questions (nested under section)
        Route::get('sections/{dsamSection}/questions', [QuestionController::class, 'index']);
        Route::post('sections/{dsamSection}/questions', [QuestionController::class, 'store']);
        Route::put('sections/{dsamSection}/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('sections/{dsamSection}/questions/{question}', [QuestionController::class, 'destroy']);
        Route::post('sections/{dsamSection}/questions/reorder', [QuestionController::class, 'reorder']);

        // Options (nested under question)
        Route::get('questions/{dsamQuestion}/options', [QuestionOptionController::class, 'index']);
        Route::post('questions/{dsamQuestion}/options', [QuestionOptionController::class, 'store']);
        Route::put('questions/{dsamQuestion}/options/{option}', [QuestionOptionController::class, 'update']);
        Route::delete('questions/{dsamQuestion}/options/{option}', [QuestionOptionController::class, 'destroy']);
        Route::post('questions/{dsamQuestion}/options/reorder', [QuestionOptionController::class, 'reorder']);

        // Submissions
        // Student profile & history (DSAM extensions on preschool_students)
        Route::get('students/{student}/profile', [StudentProfileController::class, 'show']);
        Route::put('students/{student}/profile', [StudentProfileController::class, 'upsert']);
        Route::get('students/{student}/histories', [StudentHistoryController::class, 'index']);
        Route::post('students/{student}/histories', [StudentHistoryController::class, 'store']);
        Route::put('students/{student}/histories/{history}', [StudentHistoryController::class, 'update']);

        // Submissions
        Route::get('submissions', [DsamSubmissionController::class, 'index']);
        Route::post('submissions', [DsamSubmissionController::class, 'store']);
        Route::get('submissions/{dsamSubmission}', [DsamSubmissionController::class, 'show']);
        Route::put('submissions/{dsamSubmission}', [DsamSubmissionController::class, 'update']);
        Route::delete('submissions/{dsamSubmission}', [DsamSubmissionController::class, 'destroy']);
        Route::post('submissions/{dsamSubmission}/submit', [DsamSubmissionController::class, 'submit']);
        Route::post('submissions/{dsamSubmission}/approve', [DsamSubmissionController::class, 'approve']);
        Route::post('submissions/{dsamSubmission}/reject', [DsamSubmissionController::class, 'reject']);
    });
});


