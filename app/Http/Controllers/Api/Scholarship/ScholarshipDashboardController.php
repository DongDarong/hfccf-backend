<?php

namespace App\Http\Controllers\Api\Scholarship;

use App\Http\Controllers\Controller;
use App\Services\ScholarshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScholarshipDashboardController extends Controller
{
    public function __construct(
        private readonly ScholarshipService $scholarshipService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeScholarshipViewer($request->user())) {
            return $response;
        }

        $summary = $this->scholarshipService->dashboardSummary($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Scholarship dashboard retrieved successfully.',
            'data' => $summary,
        ], Response::HTTP_OK);
    }

    public function reviewerDashboard(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    private function authorizeScholarshipViewer(?\App\Models\User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminscholarship', 'teacher-scholarship'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
