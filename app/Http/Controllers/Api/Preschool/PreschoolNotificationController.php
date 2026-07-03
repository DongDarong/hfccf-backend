<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolNotification;
use App\Models\User;
use App\Services\PreschoolNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolNotificationController extends Controller
{
    public function index(Request $request, PreschoolNotificationService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool notifications retrieved successfully.',
            'data' => $service->listNotifications($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function summary(Request $request, PreschoolNotificationService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool notification summary retrieved successfully.',
            'data' => $service->summary($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function markRead(Request $request, PreschoolNotification $notification, PreschoolNotificationService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        $updated = $service->markRead($request->user(), $notification);

        return response()->json([
            'success' => true,
            'message' => 'Preschool notification marked as read successfully.',
            'data' => [
                'notification' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolNotification $notification, PreschoolNotificationService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        $updated = $service->archive($request->user(), $notification);

        return response()->json([
            'success' => true,
            'message' => 'Preschool notification archived successfully.',
            'data' => [
                'notification' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    private function authorizeStaff(?User $user): ?JsonResponse
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
