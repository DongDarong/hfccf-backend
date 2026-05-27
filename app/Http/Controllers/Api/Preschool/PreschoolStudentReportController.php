<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolStudentReportController extends Controller
{
    /**
     * Student reports stay thin at the controller layer so the frontend can
     * swap between latest and period-specific data without changing contracts.
     */
    public function index(Request $request, PreschoolStudent $student, PreschoolReportService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $bundle = $service->bundle($request->user(), $student);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student report retrieved successfully.',
            'data' => $bundle,
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolStudent $student, string $period, PreschoolReportService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $bundle = $service->bundle($request->user(), $student, $period);
        $bundle['report'] = $service->studentReportForPeriod($request->user(), $student, $period);

        return response()->json([
            'success' => true,
            'message' => 'Preschool student report retrieved successfully.',
            'data' => $bundle,
        ], Response::HTTP_OK);
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
