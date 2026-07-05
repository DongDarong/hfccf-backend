<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolAttendanceAlertService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAttendanceAlertController extends Controller
{
    public function index(Request $request, PreschoolAttendanceAlertService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance alerts retrieved successfully.',
            'data' => $service->listAttendanceAlerts($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    private function authorizeViewer(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
