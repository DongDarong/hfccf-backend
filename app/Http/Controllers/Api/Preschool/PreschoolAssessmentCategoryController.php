<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolAssessmentCategoryResource;
use App\Models\User;
use App\Support\PreschoolAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAssessmentCategoryController extends Controller
{
    /**
     * The assessment category list is shared across teacher and admin flows so
     * the frontend never needs a hardcoded category matrix.
     */
    public function index(Request $request, PreschoolAssessmentService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool assessment categories retrieved successfully.',
            'data' => [
                'items' => PreschoolAssessmentCategoryResource::collection($service->listCategories())->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
