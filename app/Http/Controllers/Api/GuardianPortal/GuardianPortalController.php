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
     * Portal views are read-only and are always scoped to active guardian
     * relationships so unrelated student data never leaks into the portal.
     */
    public function me(Request $request, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal profile retrieved successfully.',
            'data' => $service->me($request->user()),
        ], Response::HTTP_OK);
    }

    public function students(Request $request, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal students retrieved successfully.',
            'data' => [
                'items' => $service->visibleStudents($request->user())->all(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal student summary retrieved successfully.',
            'data' => $service->studentBundle($request->user(), $student),
        ], Response::HTTP_OK);
    }

    public function attendanceSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal attendance summary retrieved successfully.',
            'data' => $service->attendanceSummary($request->user(), $student),
        ], Response::HTTP_OK);
    }

    public function scheduleSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal schedule summary retrieved successfully.',
            'data' => $service->scheduleSummary($request->user(), $student),
        ], Response::HTTP_OK);
    }

    public function progressSummary(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal progress summary retrieved successfully.',
            'data' => $service->progressSummary($request->user(), $student),
        ], Response::HTTP_OK);
    }

    public function reports(Request $request, PreschoolStudent $student, PreschoolGuardianPortalSummaryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guardian portal reports retrieved successfully.',
            'data' => $service->reportsSummary($request->user(), $student),
        ], Response::HTTP_OK);
    }
}
