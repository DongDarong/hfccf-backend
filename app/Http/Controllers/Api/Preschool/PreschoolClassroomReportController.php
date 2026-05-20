<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolClass;
use App\Models\User;
use App\Support\PreschoolClassroomReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolClassroomReportController extends Controller
{
    /**
     * Classroom reports rely on the same finalized assessment history as
     * student reports, which keeps class summaries and detail views aligned.
     */
    public function index(Request $request, PreschoolClass $class, PreschoolClassroomReportService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $bundle = $service->bundle($request->user(), $class);

        return response()->json([
            'success' => true,
            'message' => 'Preschool classroom report retrieved successfully.',
            'data' => $bundle,
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolClass $class, string $period, PreschoolClassroomReportService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $bundle = $service->bundle($request->user(), $class, $period);
        $bundle['report'] = $service->classroomReportForPeriod($request->user(), $class, $period);

        return response()->json([
            'success' => true,
            'message' => 'Preschool classroom report retrieved successfully.',
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
