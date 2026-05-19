<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolProgressSummaryResource;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolProgressSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolProgressSummaryController extends Controller
{
    /**
     * Progress summaries intentionally reuse finalized assessment data so the
     * future Preschool reports feature can build on the same foundation.
     */
    public function index(Request $request, PreschoolStudent $student, PreschoolProgressSummaryService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $summary = $service->forStudent($request->user(), $student);

        return response()->json([
            'success' => true,
            'message' => 'Preschool progress summary retrieved successfully.',
            'data' => PreschoolProgressSummaryResource::make($summary)->resolve($request),
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
