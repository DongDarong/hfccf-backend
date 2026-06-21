<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolHealthIncidentCategoryRequest;
use App\Http\Requests\Preschool\UpdatePreschoolHealthIncidentCategoryRequest;
use App\Http\Resources\Preschool\PreschoolHealthIncidentCategoryResource;
use App\Models\PreschoolHealthIncidentCategory;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolHealthIncidentCategoryController extends PreschoolHealthConfigurationController
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
            'message' => 'Incident categories retrieved successfully.',
            'data' => [
                'items' => PreschoolHealthIncidentCategoryResource::collection($service->listIncidentCategories($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolHealthIncidentCategoryRequest $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $category = $service->createIncidentCategory($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Incident category created successfully.',
            'data' => [
                'category' => PreschoolHealthIncidentCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $category): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = PreschoolHealthIncidentCategory::query()->withTrashed()->findOrFail($category);

        return response()->json([
            'success' => true,
            'message' => 'Incident category retrieved successfully.',
            'data' => [
                'category' => PreschoolHealthIncidentCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolHealthIncidentCategoryRequest $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $record = $service->updateIncidentCategory($category, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Incident category updated successfully.',
            'data' => [
                'category' => PreschoolHealthIncidentCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = $service->archiveIncidentCategory($category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Incident category archived successfully.',
            'data' => [
                'category' => PreschoolHealthIncidentCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
