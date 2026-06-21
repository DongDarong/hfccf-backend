<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolHealthCheckCategoryRequest;
use App\Http\Requests\Preschool\UpdatePreschoolHealthCheckCategoryRequest;
use App\Http\Resources\Preschool\PreschoolHealthCheckCategoryResource;
use App\Models\PreschoolHealthCheckCategory;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolHealthCheckCategoryController extends PreschoolHealthConfigurationController
{
    public function index(Request $request, PreschoolHealthConfigurationService $service): JsonResponse
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
            'message' => 'Health check categories retrieved successfully.',
            'data' => [
                'items' => PreschoolHealthCheckCategoryResource::collection($service->listHealthCheckCategories($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolHealthCheckCategoryRequest $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $category = $service->createHealthCheckCategory($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Health check category created successfully.',
            'data' => [
                'category' => PreschoolHealthCheckCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $category): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = PreschoolHealthCheckCategory::query()->withTrashed()->findOrFail($category);

        return response()->json([
            'success' => true,
            'message' => 'Health check category retrieved successfully.',
            'data' => [
                'category' => PreschoolHealthCheckCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolHealthCheckCategoryRequest $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $record = $service->updateHealthCheckCategory($category, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Health check category updated successfully.',
            'data' => [
                'category' => PreschoolHealthCheckCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = $service->archiveHealthCheckCategory($category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Health check category archived successfully.',
            'data' => [
                'category' => PreschoolHealthCheckCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
