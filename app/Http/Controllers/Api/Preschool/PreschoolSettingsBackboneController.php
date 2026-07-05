<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\UpdatePreschoolSettingsBackboneRequest;
use App\Http\Resources\Preschool\PreschoolSettingsBackboneResource;
use App\Support\PreschoolSettingsBackboneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolSettingsBackboneController extends Controller
{
    /**
     * Preschool settings form the shared configuration backbone for the module.
     * Teachers can read the snapshot for workflow context, while only preschool
     * admins can write the canonical settings payload.
     */
    public function show(Request $request, PreschoolSettingsBackboneService $service): JsonResponse
    {
        if ($response = $this->authorizeRead($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool settings retrieved successfully.',
            'data' => [
                'settings' => PreschoolSettingsBackboneResource::make($service->snapshot())->resolve($request),
                'academicContext' => $service->currentAcademicContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolSettingsBackboneRequest $request, PreschoolSettingsBackboneService $service): JsonResponse
    {
        if ($response = $this->authorizeWrite($request->user())) {
            return $response;
        }

        $snapshot = $service->saveSnapshot(
            $request->validated(),
            $request->user(),
            [
                'source' => 'preschool-settings-backbone',
                'context' => [
                    'route' => $request->path(),
                    'method' => $request->method(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Preschool settings saved successfully.',
            'data' => [
                'settings' => PreschoolSettingsBackboneResource::make($snapshot)->resolve($request),
                'academicContext' => $service->currentAcademicContext(),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeRead($user): ?JsonResponse
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

    private function authorizeWrite($user): ?JsonResponse
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
