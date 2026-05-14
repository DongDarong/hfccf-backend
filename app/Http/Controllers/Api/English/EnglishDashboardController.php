<?php

namespace App\Http\Controllers\Api\English;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EnglishService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnglishDashboardController extends Controller
{
    public function index(Request $request, EnglishService $englishService): JsonResponse
    {
        if ($response = $this->authorizeEnglishAdmin($request->user())) {
            return $response;
        }

        return ApiResponse::successResponse(
            'English dashboard retrieved successfully.',
            $englishService->dashboardSummary($request->user()),
        );
    }

    private function authorizeEnglishAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return ApiResponse::errorResponse('Unauthenticated.', null, 401);
        }

        if (in_array($user->role_code, ['superadmin', 'adminenglish'], true)) {
            return null;
        }

        return ApiResponse::errorResponse('Forbidden.', null, 403);
    }
}
