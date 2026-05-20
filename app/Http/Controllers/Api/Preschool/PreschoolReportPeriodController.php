<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolReportPeriodController extends Controller
{
    /**
     * Report periods are derived from finalized assessments so the frontend can
     * build stable period selectors without creating fake term records.
     */
    public function index(Request $request, PreschoolReportService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $student = $this->resolveStudent($request);
        $class = $this->resolveClass($request);

        $periods = $service->reportPeriods($request->user(), $student, $class)->values();

        return response()->json([
            'success' => true,
            'message' => 'Preschool report periods retrieved successfully.',
            'data' => [
                'periods' => $periods,
            ],
        ], Response::HTTP_OK);
    }

    private function resolveStudent(Request $request): ?PreschoolStudent
    {
        $studentId = trim((string) $request->query('student_id', ''));
        if ($studentId === '') {
            return null;
        }

        return PreschoolStudent::query()->findOrFail($studentId);
    }

    private function resolveClass(Request $request): ?PreschoolClass
    {
        $classId = trim((string) $request->query('class_id', ''));
        if ($classId === '') {
            return null;
        }

        return PreschoolClass::query()->findOrFail($classId);
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
