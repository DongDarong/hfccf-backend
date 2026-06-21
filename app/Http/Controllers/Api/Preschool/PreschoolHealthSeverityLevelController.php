<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Requests\Preschool\StorePreschoolHealthSeverityLevelRequest;
use App\Http\Requests\Preschool\UpdatePreschoolHealthSeverityLevelRequest;
use App\Http\Resources\Preschool\PreschoolHealthSeverityLevelResource;
use App\Models\PreschoolHealthSeverityLevel;
use App\Support\PreschoolHealthConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolHealthSeverityLevelController extends PreschoolHealthConfigurationController
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
            'message' => 'Severity levels retrieved successfully.',
            'data' => [
                'items' => PreschoolHealthSeverityLevelResource::collection($service->listSeverityLevels($filters))->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolHealthSeverityLevelRequest $request, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $level = $service->createSeverityLevel($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Severity level created successfully.',
            'data' => [
                'severity' => PreschoolHealthSeverityLevelResource::make($level)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $severity): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $level = PreschoolHealthSeverityLevel::query()->withTrashed()->findOrFail($severity);

        return response()->json([
            'success' => true,
            'message' => 'Severity level retrieved successfully.',
            'data' => [
                'severity' => PreschoolHealthSeverityLevelResource::make($level)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolHealthSeverityLevelRequest $request, string $severity, PreschoolHealthConfigurationService $service): JsonResponse
    {
        $level = $service->updateSeverityLevel($severity, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Severity level updated successfully.',
            'data' => [
                'severity' => PreschoolHealthSeverityLevelResource::make($level)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, string $severity, PreschoolHealthConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $level = $service->archiveSeverityLevel($severity, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Severity level archived successfully.',
            'data' => [
                'severity' => PreschoolHealthSeverityLevelResource::make($level)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }
}
