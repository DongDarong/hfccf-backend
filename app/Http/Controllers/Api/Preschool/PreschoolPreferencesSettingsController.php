<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\UpdatePreschoolPreferencesRequest;
use App\Http\Resources\Preschool\PreschoolPreferencesResource;
use App\Support\PreschoolPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolPreferencesSettingsController extends PreschoolPreferencesConfigurationController
{
    public function show(Request $request, PreschoolPreferencesService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool preferences retrieved successfully.',
            'data' => [
                'settings' => PreschoolPreferencesResource::make($service->getPreferences())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolPreferencesRequest $request, PreschoolPreferencesService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $settings = $service->updatePreferences($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool preferences updated successfully.',
            'data' => [
                'settings' => PreschoolPreferencesResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
