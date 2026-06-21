<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\StorePreschoolAttendanceSettingRequest;
use App\Http\Requests\Preschool\UpdatePreschoolAttendanceSettingRequest;
use App\Http\Resources\Preschool\PreschoolAttendanceSettingResource;
use App\Support\PreschoolAttendanceConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAttendanceSettingsController extends Controller
{
    public function show(Request $request, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $settings = $service->getSettings();

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance settings retrieved successfully.',
            'data' => [
                'settings' => PreschoolAttendanceSettingResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function update(UpdatePreschoolAttendanceSettingRequest $request, PreschoolAttendanceConfigurationService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        $settings = $service->updateSettings($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool attendance settings updated successfully.',
            'data' => [
                'settings' => PreschoolAttendanceSettingResource::make($settings)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeSettingsAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();

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
