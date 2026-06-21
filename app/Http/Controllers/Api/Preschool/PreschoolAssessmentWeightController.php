<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\UpdatePreschoolAssessmentWeightsRequest;
use App\Http\Resources\Preschool\PreschoolAssessmentWeightResource;
use App\Support\PreschoolAssessmentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentWeightController extends Controller
{
    public function index(Request $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment weights retrieved successfully.',
            'data' => [
                'items' => PreschoolAssessmentWeightResource::collection($service->listWeights())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolAssessmentWeightsRequest $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $weights = $service->updateWeights($request->validated('weights'), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment weights updated successfully.',
            'data' => [
                'items' => PreschoolAssessmentWeightResource::collection($weights)->resolve($request),
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
