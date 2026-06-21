<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolReportingController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'adminpreschool'];

    private const TEACHER_ALLOWED_SECTIONS = [
        'attendance',
        'attendance_class',
        'attendance_student',
        'attendance_trend',
        'assessments',
        'assessment_performance',
        'assessment_completion',
        'assessment_trend',
    ];

    private function authorizeReportAccess(Request $request, string $section, bool $allowTeacher = false): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, self::ADMIN_ROLES, true)) {
            return null;
        }

        if ($allowTeacher && $user->role_code === 'teacher-preschool' && in_array($section, self::TEACHER_ALLOWED_SECTIONS, true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }

    public function dashboard(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'dashboard')) {
            return $response;
        }

        return $this->successResponse('Preschool reports dashboard retrieved successfully.', [
            'dashboard' => $service->getOperationsDashboard($request->all()),
            'filters' => $service->getReportFilters(),
        ]);
    }

    public function attendance(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'attendance', true)) {
            return $response;
        }

        return $this->successResponse('Preschool attendance report retrieved successfully.', [
            'report' => $service->getAttendanceSummary($request->all()),
        ]);
    }

    public function attendanceClass(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'attendance_class', true)) {
            return $response;
        }

        return $this->successResponse('Preschool attendance class report retrieved successfully.', [
            'report' => $service->getAttendanceByClass($request->all()),
        ]);
    }

    public function attendanceStudent(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'attendance_student', true)) {
            return $response;
        }

        return $this->successResponse('Preschool attendance student report retrieved successfully.', [
            'report' => $service->getAttendanceByStudent($request->all()),
        ]);
    }

    public function attendanceTrend(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'attendance_trend', true)) {
            return $response;
        }

        return $this->successResponse('Preschool attendance trend retrieved successfully.', [
            'report' => $service->getAttendanceTrend($request->all()),
        ]);
    }

    public function assessments(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'assessments', true)) {
            return $response;
        }

        return $this->successResponse('Preschool assessment report retrieved successfully.', [
            'report' => $service->getAssessmentSummary($request->all()),
        ]);
    }

    public function assessmentPerformance(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'assessment_performance', true)) {
            return $response;
        }

        return $this->successResponse('Preschool assessment performance retrieved successfully.', [
            'report' => $service->getAssessmentPerformance($request->all()),
        ]);
    }

    public function assessmentCompletion(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'assessment_completion', true)) {
            return $response;
        }

        return $this->successResponse('Preschool assessment completion retrieved successfully.', [
            'report' => $service->getAssessmentCompletionRates($request->all()),
        ]);
    }

    public function health(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'health')) {
            return $response;
        }

        return $this->successResponse('Preschool health report retrieved successfully.', [
            'report' => $service->getHealthSummary($request->all()),
        ]);
    }

    public function healthIncidents(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'health_incidents')) {
            return $response;
        }

        return $this->successResponse('Preschool health incident report retrieved successfully.', [
            'report' => $service->getHealthIncidents($request->all()),
        ]);
    }

    public function healthVaccinations(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'vaccination_compliance')) {
            return $response;
        }

        return $this->successResponse('Preschool vaccination compliance retrieved successfully.', [
            'report' => $service->getVaccinationCompliance($request->all()),
        ]);
    }

    public function payments(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'payments')) {
            return $response;
        }

        return $this->successResponse('Preschool payment report retrieved successfully.', [
            'report' => $service->getPaymentSummary($request->all()),
        ]);
    }

    public function paymentsRevenue(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'payments_revenue')) {
            return $response;
        }

        return $this->successResponse('Preschool revenue report retrieved successfully.', [
            'report' => $service->getRevenueSummary($request->all()),
        ]);
    }

    public function paymentsOutstanding(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'payments_outstanding')) {
            return $response;
        }

        return $this->successResponse('Preschool outstanding balances retrieved successfully.', [
            'report' => $service->getOutstandingBalances($request->all()),
        ]);
    }

    public function enrollments(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'enrollments')) {
            return $response;
        }

        return $this->successResponse('Preschool enrollment report retrieved successfully.', [
            'report' => $service->getEnrollmentSummary($request->all()),
        ]);
    }

    public function enrollmentTrends(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'enrollment_trend')) {
            return $response;
        }

        return $this->successResponse('Preschool enrollment trend retrieved successfully.', [
            'report' => $service->getEnrollmentTrend($request->all()),
        ]);
    }

    public function guardians(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'guardians')) {
            return $response;
        }

        return $this->successResponse('Preschool guardian report retrieved successfully.', [
            'report' => $service->getGuardianSummary($request->all()),
        ]);
    }

    public function guardianIssues(Request $request, PreschoolReportingService $service): JsonResponse
    {
        if ($response = $this->authorizeReportAccess($request, 'guardian_issues')) {
            return $response;
        }

        return $this->successResponse('Preschool guardian issue report retrieved successfully.', [
            'report' => $service->getGuardianIssueReport($request->all()),
        ]);
    }

    public function export(Request $request, PreschoolReportingService $service): JsonResponse
    {
        $section = (string) $request->query('section', 'dashboard');
        $allowTeacher = in_array($section, self::TEACHER_ALLOWED_SECTIONS, true);

        if ($response = $this->authorizeReportAccess($request, $section, $allowTeacher)) {
            return $response;
        }

        $payload = $service->exportReport(
            $section,
            (string) $request->query('format', 'csv'),
            $request->all(),
        );

        return $this->successResponse('Preschool report export prepared successfully.', [
            'export' => $payload,
        ]);
    }

    private function successResponse(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], Response::HTTP_OK);
    }
}
