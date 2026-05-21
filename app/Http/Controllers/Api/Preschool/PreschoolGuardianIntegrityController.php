<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PreschoolGuardianConsistencyService;
use App\Support\PreschoolGuardianDuplicateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianIntegrityController extends Controller
{
    /**
     * Integrity checks stay staff-only and read-only so the normalized guardian
     * records can be audited without introducing any portal-style behavior.
     */
    public function duplicates(Request $request, PreschoolGuardianDuplicateService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $report = $service->report($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian duplicates retrieved successfully.',
            'data' => $report,
        ], Response::HTTP_OK);
    }

    public function consistencyReport(Request $request, PreschoolGuardianConsistencyService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $report = $service->report($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool guardian consistency report retrieved successfully.',
            'data' => $report,
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
