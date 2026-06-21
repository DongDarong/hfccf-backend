<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolPaymentMethodRequest;
use App\Http\Requests\Preschool\UpdatePreschoolPaymentMethodRequest;
use App\Http\Resources\Preschool\PreschoolPaymentMethodResource;
use App\Support\PreschoolPaymentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolPaymentMethodController extends PreschoolPaymentConfigurationController
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
            'message' => 'Payment methods retrieved successfully.',
            'data' => [
                'items' => PreschoolPaymentMethodResource::collection($service->listPaymentMethods($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolPaymentMethodRequest $request, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $method = $service->createPaymentMethod($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment method created successfully.',
            'data' => [
                'payment_method' => PreschoolPaymentMethodResource::make($method)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolPaymentMethodRequest $request, string $method, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        $record = $service->updatePaymentMethod($method, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully.',
            'data' => [
                'payment_method' => PreschoolPaymentMethodResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $method, PreschoolPaymentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = $service->archivePaymentMethod($method, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment method archived successfully.',
            'data' => [
                'payment_method' => PreschoolPaymentMethodResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
