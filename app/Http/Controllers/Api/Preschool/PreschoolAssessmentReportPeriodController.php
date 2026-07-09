<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolAssessmentReportPeriodRequest;
use App\Http\Requests\Preschool\UpdatePreschoolAssessmentReportPeriodRequest;
use App\Http\Resources\Preschool\PreschoolAssessmentReportPeriodResource;
use App\Models\PreschoolAssessmentReportPeriod;
use App\Support\PreschoolAssessmentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentReportPeriodController extends Controller
{
    public function index(Request $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment report periods retrieved successfully.',
            'data' => [
                'items' => PreschoolAssessmentReportPeriodResource::collection($service->listReportPeriods(true, [
                    'period_type' => $request->query('period_type'),
                    'academic_year_id' => $request->query('academic_year_id'),
                    'term_id' => $request->query('term_id'),
                ]))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolAssessmentReportPeriodRequest $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $period = $service->createReportPeriod($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment report period created successfully.',
            'data' => [
                'period' => PreschoolAssessmentReportPeriodResource::make($period)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolAssessmentReportPeriodRequest $request, PreschoolAssessmentReportPeriod $period, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $period = $service->updateReportPeriod($period, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment report period updated successfully.',
            'data' => [
                'period' => PreschoolAssessmentReportPeriodResource::make($period)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolAssessmentReportPeriod $period, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $period = $service->archiveReportPeriod($period, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment report period archived successfully.',
            'data' => [
                'period' => PreschoolAssessmentReportPeriodResource::make($period)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
