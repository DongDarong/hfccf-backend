<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PreschoolWorkflowObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolWorkflowObservabilityController extends Controller
{
    public function dashboard(Request $request, PreschoolWorkflowObservabilityService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow observability dashboard retrieved successfully.',
            'data' => $service->dashboard($validated, $request->user()),
        ], Response::HTTP_OK);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'definition_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'started_by_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'mode' => ['sometimes', 'nullable', 'string', 'in:preview,run'],
        ]);
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
