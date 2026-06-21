<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\UpdatePreschoolHealthSettingRequest;
use App\Http\Resources\Preschool\PreschoolHealthSettingResource;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolHealthSettingsController extends PreschoolHealthConfigurationController
{
    public function show(Request $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool health settings retrieved successfully.',
            'data' => [
                'settings' => PreschoolHealthSettingResource::make($service->getSettings())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolHealthSettingRequest $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $settings = $service->updateSettings($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool health settings updated successfully.',
            'data' => [
                'settings' => PreschoolHealthSettingResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
