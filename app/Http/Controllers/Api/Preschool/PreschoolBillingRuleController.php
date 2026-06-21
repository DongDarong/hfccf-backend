<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\UpdatePreschoolBillingRulesRequest;
use App\Http\Resources\Preschool\PreschoolBillingRuleResource;
use App\Support\PreschoolPaymentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolBillingRuleController extends PreschoolPaymentConfigurationController
{
    public function index(Request $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Billing rules retrieved successfully.',
            'data' => [
                'items' => PreschoolBillingRuleResource::collection($service->listBillingRules())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolBillingRulesRequest $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $rules = $service->updateBillingRules($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Billing rules updated successfully.',
            'data' => [
                'items' => PreschoolBillingRuleResource::collection($rules)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
