<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolPayment;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentHealthIncident;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $isTeacher = $user->role_code === 'teacher-preschool';
        $teacherClassIds = $isTeacher
            ? PreschoolClass::query()->where('teacher_user_id', $user->id)->pluck('id')->all()
            : [];

        $classQuery = PreschoolClass::query()->whereNull('deleted_at');
        $studentQuery = PreschoolStudent::query()->whereNull('deleted_at');
        $attendanceQuery = PreschoolAttendanceRecord::query();
        $paymentQuery = PreschoolPayment::query()->whereNull('deleted_at');
        $incidentQuery = PreschoolStudentHealthIncident::query()->whereNull('deleted_at');

        if ($isTeacher) {
            $classQuery->whereIn('id', $teacherClassIds);
            $studentQuery->whereHas('classes', static function ($query) use ($teacherClassIds): void {
                $query->whereIn('preschool_classes.id', $teacherClassIds);
            });
            $attendanceQuery->whereIn('class_id', $teacherClassIds);
            $paymentQuery->whereIn('class_id', $teacherClassIds);
            $incidentQuery->whereHas('student.classes', static function ($query) use ($teacherClassIds): void {
                $query->whereIn('preschool_classes.id', $teacherClassIds);
            });
        }

        $today = now()->toDateString();

        $summary = [
            'students' => (clone $studentQuery)->count(),
            'classes' => (clone $classQuery)->where('status', 'active')->count(),
            'teachers' => User::query()
                ->whereNull('deleted_at')
                ->where('role_code', 'teacher-preschool')
                ->count(),
            'attendanceToday' => (clone $attendanceQuery)->whereDate('attendance_date', $today)->count(),
            'pendingPayments' => (clone $paymentQuery)->where('payment_status', 'pending')->count(),
            'overduePayments' => (clone $paymentQuery)->where('payment_status', 'overdue')->count(),
            'healthAlerts' => (clone $incidentQuery)->whereIn('severity', ['high', 'critical'])->whereIn('status', ['open', 'resolved'])->count(),
        ];

        $recentAttendance = (clone $attendanceQuery)
            ->with(['student', 'preschoolClass', 'recordedBy'])
            ->latest('attendance_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(static function (PreschoolAttendanceRecord $record): array {
                return [
                    'id' => $record->id,
                    'className' => $record->preschoolClass?->name,
                    'studentName' => trim(($record->student?->first_name ?? '').' '.($record->student?->last_name ?? '')),
                    'status' => $record->status,
                    'attendanceDate' => $record->attendance_date?->toDateString(),
                    'recordedByName' => $record->recordedBy?->name,
                ];
            })
            ->values()
            ->all();

        $upcomingClasses = (clone $classQuery)
            ->with('teacher')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(static function (PreschoolClass $class): array {
                return [
                    'id' => $class->id,
                    'code' => $class->code,
                    'name' => $class->name,
                    'teacherDisplayName' => $class->teacher_display_name ?: $class->teacher?->name,
                    'level' => $class->level,
                    'schedule' => $class->schedule,
                    'studentsCount' => $class->students_count,
                    'status' => $class->status,
                    'room' => $class->room,
                ];
            })
            ->values()
            ->all();

        $paymentSummary = [
            'paid' => (clone $paymentQuery)->where('payment_status', 'paid')->count(),
            'pending' => (clone $paymentQuery)->where('payment_status', 'pending')->count(),
            'overdue' => (clone $paymentQuery)->where('payment_status', 'overdue')->count(),
            'cancelled' => (clone $paymentQuery)->where('payment_status', 'cancelled')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Preschool dashboard retrieved successfully.',
            'data' => [
                'summary' => $summary,
                'recentAttendance' => $recentAttendance,
                'upcomingClasses' => $upcomingClasses,
                'paymentSummary' => $paymentSummary,
            ],
        ], Response::HTTP_OK);
    }
}
