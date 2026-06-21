<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Support\PreschoolSettingsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolSettingsDashboardController extends PreschoolHealthConfigurationController
{
    public function show(Request $request, PreschoolSettingsDashboardService $service): JsonResponse
    {
        if ($response = $this->authorizeSettingsAccess($request)) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool settings dashboard retrieved successfully.',
            'data' => [
                'dashboard' => $service->getDashboard(),
            ],
        ], Response::HTTP_OK);
    }
}
