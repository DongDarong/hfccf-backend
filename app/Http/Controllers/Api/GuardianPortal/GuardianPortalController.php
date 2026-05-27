<?php

namespace App\Http\Controllers\Api\GuardianPortal;

use App\Http\Controllers\Controller;
use App\Models\PreschoolStudent;
use App\Support\PreschoolGuardianPortalSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuardianPortalController extends Controller
{
    /**
     * Legacy compatibility only: the public guardian portal is disabled, so
     * these endpoints now fail closed instead of exposing student summaries to
     * an authenticated parent account.
     */
    public function me(Request $request, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function students(Request $request, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function show(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function attendanceSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function scheduleSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function progressSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }

    public function reports(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Guardian portal access is disabled.',
            'data' => null,
        ], Response::HTTP_GONE);
    }
}
