<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PreschoolNotificationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAutomationController extends Controller
{
    public function runDailyChecks(Request $request, PreschoolNotificationRuleService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool daily automation checks completed successfully.',
            'data' => $service->runDailyChecks($request->user()),
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
