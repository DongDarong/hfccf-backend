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
use App\Http\Controllers\Api\GuardianPortal\GuardianPortalAuthController;
use App\Http\Controllers\Api\GuardianPortal\GuardianPortalController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Preschool\PreschoolAssessmentCategoryController;
use App\Http\Controllers\Api\Preschool\PreschoolAttendanceController;
use App\Http\Controllers\Api\Preschool\PreschoolClassController;
use App\Http\Controllers\Api\Preschool\PreschoolClassroomReportController;
use App\Http\Controllers\Api\Preschool\PreschoolDashboardController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianController;
use App\Http\Controllers\Api\Preschool\PreschoolGuardianPortalController;
use App\Http\Controllers\Api\Preschool\PreschoolPaymentController;
use App\Http\Controllers\Api\Preschool\PreschoolProgressSummaryController;
use App\Http\Controllers\Api\Preschool\PreschoolReportPeriodController;
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
use App\Http\Controllers\Api\Sport\SportApprovalController;
use App\Http\Controllers\Api\Sport\SportCoachController;
use App\Http\Controllers\Api\Sport\SportCoachTeamController;
use App\Http\Controllers\Api\Sport\SportDashboardController;
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

Route::prefix('guardian-portal')->group(function (): void {
    // The invitation activation endpoint is public so a guardian can claim
    // the portal account before the login-only routes become available.
    Route::post('activate', [GuardianPortalAuthController::class, 'activate']);

    Route::middleware(['auth:sanctum', 'throttle:api', 'guardian.portal'])->group(function (): void {
        Route::get('me', [GuardianPortalController::class, 'me']);
        Route::get('students', [GuardianPortalController::class, 'students']);
        Route::get('students/{student}', [GuardianPortalController::class, 'show']);
        Route::get('students/{student}/attendance-summary', [GuardianPortalController::class, 'attendanceSummary']);
        Route::get('students/{student}/schedule-summary', [GuardianPortalController::class, 'scheduleSummary']);
        Route::get('students/{student}/progress-summary', [GuardianPortalController::class, 'progressSummary']);
        Route::get('students/{student}/reports', [GuardianPortalController::class, 'reports']);
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

    Route::prefix('preschool')->group(function (): void {
        Route::get('dashboard', [PreschoolDashboardController::class, 'index']);

        Route::get('teachers', [PreschoolTeacherController::class, 'index']);
        Route::post('teachers', [PreschoolTeacherController::class, 'store']);
        Route::get('teachers/{id}', [PreschoolTeacherController::class, 'show']);
        Route::put('teachers/{id}', [PreschoolTeacherController::class, 'update']);
        Route::delete('teachers/{id}', [PreschoolTeacherController::class, 'destroy']);
        Route::get('teacher/my-students', [PreschoolTeacherController::class, 'myStudents']);
        Route::get('teacher/my-classes', [PreschoolTeacherController::class, 'myClasses']);
        Route::get('teacher/attendance', [PreschoolAttendanceController::class, 'teacherAttendance']);

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

        Route::get('attendance', [PreschoolAttendanceController::class, 'index']);
        Route::post('attendance', [PreschoolAttendanceController::class, 'store']);
        Route::put('attendance/{id}', [PreschoolAttendanceController::class, 'update']);

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
});
