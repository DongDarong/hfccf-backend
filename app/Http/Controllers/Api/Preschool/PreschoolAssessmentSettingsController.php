<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\UpdatePreschoolAssessmentSettingsRequest;
use App\Http\Resources\Preschool\PreschoolAssessmentSettingsResource;
use App\Support\PreschoolAssessmentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentSettingsController extends Controller
{
    public function show(Request $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment settings retrieved successfully.',
            'data' => [
                'settings' => PreschoolAssessmentSettingsResource::make($service->getSettings())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolAssessmentSettingsRequest $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $settings = $service->updateSettings($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment settings updated successfully.',
            'data' => [
                'settings' => PreschoolAssessmentSettingsResource::make($settings)->resolve($request),
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
