<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolFeeTypeRequest;
use App\Http\Requests\Preschool\UpdatePreschoolFeeTypeRequest;
use App\Http\Resources\Preschool\PreschoolFeeTypeResource;
use App\Models\PreschoolFeeType;
use App\Support\PreschoolPaymentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolFeeTypeController extends PreschoolPaymentConfigurationController
{
    public function index(Request $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:active,archived'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fee types retrieved successfully.',
            'data' => [
                'items' => PreschoolFeeTypeResource::collection($service->listFeeTypes($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolFeeTypeRequest $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $feeType = $service->createFeeType($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fee type created successfully.',
            'data' => [
                'fee_type' => PreschoolFeeTypeResource::make($feeType)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolFeeTypeRequest $request, string $feeType, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $record = $service->updateFeeType($feeType, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fee type updated successfully.',
            'data' => [
                'fee_type' => PreschoolFeeTypeResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $feeType, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = $service->archiveFeeType($feeType, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Fee type archived successfully.',
            'data' => [
                'fee_type' => PreschoolFeeTypeResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
