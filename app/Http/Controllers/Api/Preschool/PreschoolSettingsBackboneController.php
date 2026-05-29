<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolSettingsBackboneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolSettingsBackboneController extends Controller
{
    /**
     * Preschool settings are the academic backbone for the module. They stay
     * admin-only and provide the shared academic year, term, and operational
     * defaults used by reporting, attendance, schedules, and assignments.
     */
    public function show(Request $request, PreschoolSettingsBackboneService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool settings retrieved successfully.',
            'data' => [
                'settings' => $service->snapshot(),
                'academicContext' => $service->currentAcademicContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function update(Request $request, PreschoolSettingsBackboneService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academicYear' => ['sometimes', 'array'],
            'terms' => ['sometimes', 'array'],
            'classConfigurations' => ['sometimes', 'array'],
            'attendance' => ['sometimes', 'array'],
            'assessment' => ['sometimes', 'array'],
            'schedule' => ['sometimes', 'array'],
            'enrollment' => ['sometimes', 'array'],
            'payment' => ['sometimes', 'array'],
        ]);

        $snapshot = $service->saveSnapshot($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preschool settings saved successfully.',
            'data' => [
                'settings' => $snapshot,
                'academicContext' => $service->currentAcademicContext(),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeAdmin($user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
    }
}
