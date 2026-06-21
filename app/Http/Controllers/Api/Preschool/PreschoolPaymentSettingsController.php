<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\UpdatePreschoolPaymentSettingRequest;
use App\Http\Resources\Preschool\PreschoolPaymentSettingResource;
use App\Support\PreschoolPaymentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolPaymentSettingsController extends PreschoolPaymentConfigurationController
{
    public function show(Request $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $settings = $service->getSettings();

        return response()->json([
            'success' => true,
            'message' => 'Payment settings retrieved successfully.',
            'data' => [
                'settings' => PreschoolPaymentSettingResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolPaymentSettingRequest $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $settings = $service->updateSettings($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment settings updated successfully.',
            'data' => [
                'settings' => PreschoolPaymentSettingResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
