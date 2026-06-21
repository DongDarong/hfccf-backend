<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolGradingScaleRequest;
use App\Http\Requests\Preschool\UpdatePreschoolGradingScaleRequest;
use App\Http\Resources\Preschool\PreschoolAssessmentGradingScaleResource;
use App\Models\PreschoolAssessmentGradingScale;
use App\Support\PreschoolAssessmentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentGradingScaleController extends Controller
{
    public function index(Request $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool grading scale retrieved successfully.',
            'data' => [
                'items' => PreschoolAssessmentGradingScaleResource::collection($service->getGradingScale())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolGradingScaleRequest $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $band = $service->createGradeBand($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool grading band created successfully.',
            'data' => [
                'band' => PreschoolAssessmentGradingScaleResource::make($band)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolGradingScaleRequest $request, PreschoolAssessmentGradingScale $band, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $band = $service->updateGradeBand($band, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool grading band updated successfully.',
            'data' => [
                'band' => PreschoolAssessmentGradingScaleResource::make($band)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, PreschoolAssessmentGradingScale $band, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $service->deleteGradeBand($band);

        return response()->json([
            'success' => true,
            'message' => 'Preschool grading band deleted successfully.',
            'data' => null,
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
