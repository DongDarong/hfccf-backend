<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\English\EnglishClassController;
use App\Http\Controllers\Api\English\EnglishDashboardController;
use App\Http\Controllers\Api\English\EnglishSubmissionController;
use App\Http\Controllers\Api\English\EnglishStudentController;
use App\Http\Controllers\Api\English\EnglishTaskController;
use App\Http\Controllers\Api\English\EnglishTeacherController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Scholarship\ScholarshipApplicationController;
use App\Http\Controllers\Api\Scholarship\ScholarshipDashboardController;
use App\Http\Controllers\Api\Scholarship\ScholarshipReviewController;
use App\Http\Controllers\Api\Scholarship\ScholarshipStudentController;
use App\Http\Controllers\Api\Preschool\PreschoolAttendanceController;
use App\Http\Controllers\Api\Preschool\PreschoolClassController;
use App\Http\Controllers\Api\Preschool\PreschoolDashboardController;
use App\Http\Controllers\Api\Preschool\PreschoolPaymentController;
use App\Http\Controllers\Api\Preschool\PreschoolStudentController;
use App\Http\Controllers\Api\Preschool\PreschoolTeacherController;
use App\Http\Controllers\Api\RoleController;
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

        Route::get('attendance', [PreschoolAttendanceController::class, 'index']);
        Route::post('attendance', [PreschoolAttendanceController::class, 'store']);
        Route::put('attendance/{id}', [PreschoolAttendanceController::class, 'update']);

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
});
