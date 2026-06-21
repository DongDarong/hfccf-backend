<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolAssessmentCategoryRequest;
use App\Http\Requests\Preschool\UpdatePreschoolAssessmentCategoryRequest;
use App\Http\Resources\Preschool\PreschoolAssessmentCategoryResource;
use App\Models\PreschoolAssessmentCategory;
use App\Support\PreschoolAssessmentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentCategorySettingsController extends Controller
{
    public function index(Request $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $items = $service->listCategories(true);

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment categories retrieved successfully.',
            'data' => [
                'items' => PreschoolAssessmentCategoryResource::collection($items)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePreschoolAssessmentCategoryRequest $request, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $category = $service->createCategory($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment category created successfully.',
            'data' => [
                'category' => PreschoolAssessmentCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePreschoolAssessmentCategoryRequest $request, PreschoolAssessmentCategory $category, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $category = $service->updateCategory($category, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment category updated successfully.',
            'data' => [
                'category' => PreschoolAssessmentCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolAssessmentCategory $category, PreschoolAssessmentConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request)) {
            return $response;
        }

        $category = $service->archiveCategory($category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment category archived successfully.',
            'data' => [
                'category' => PreschoolAssessmentCategoryResource::make($category)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
