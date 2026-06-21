<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolVaccinationCategoryRequest;
use App\Http\Requests\Preschool\UpdatePreschoolVaccinationCategoryRequest;
use App\Http\Resources\Preschool\PreschoolVaccinationCategoryResource;
use App\Models\PreschoolVaccinationCategory;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolVaccinationCategoryController extends PreschoolHealthConfigurationController
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
            'message' => 'Vaccination categories retrieved successfully.',
            'data' => [
                'items' => PreschoolVaccinationCategoryResource::collection($service->listVaccinationCategories($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolVaccinationCategoryRequest $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $category = $service->createVaccinationCategory($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Vaccination category created successfully.',
            'data' => [
                'category' => PreschoolVaccinationCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $category): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = PreschoolVaccinationCategory::query()->withTrashed()->findOrFail($category);

        return response()->json([
            'success' => true,
            'message' => 'Vaccination category retrieved successfully.',
            'data' => [
                'category' => PreschoolVaccinationCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolVaccinationCategoryRequest $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $record = $service->updateVaccinationCategory($category, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Vaccination category updated successfully.',
            'data' => [
                'category' => PreschoolVaccinationCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $category, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $record = $service->archiveVaccinationCategory($category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Vaccination category archived successfully.',
            'data' => [
                'category' => PreschoolVaccinationCategoryResource::make($record)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
