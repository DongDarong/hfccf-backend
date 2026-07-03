<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolAutomationTask;
use App\Models\User;
use App\Services\PreschoolAutomationTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolAutomationTaskController extends Controller
{
    public function index(Request $request, PreschoolAutomationTaskService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool automation tasks retrieved successfully.',
            'data' => $service->listTasks($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function summary(Request $request, PreschoolAutomationTaskService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool automation task summary retrieved successfully.',
            'data' => $service->summary($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function complete(Request $request, PreschoolAutomationTask $task, PreschoolAutomationTaskService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        $updated = $service->complete($request->user(), $task);

        return response()->json([
            'success' => true,
            'message' => 'Preschool automation task completed successfully.',
            'data' => [
                'task' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function cancel(Request $request, PreschoolAutomationTask $task, PreschoolAutomationTaskService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        $updated = $service->cancel($request->user(), $task);

        return response()->json([
            'success' => true,
            'message' => 'Preschool automation task cancelled successfully.',
            'data' => [
                'task' => $updated,
            ],
        ], Response::HTTP_OK);
    }

    public function assign(Request $request, PreschoolAutomationTask $task, PreschoolAutomationTaskService $service): JsonResponse
    {
        if ($response = $this->authorizeStaff($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'assigned_to_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'assigned_role' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $updated = $service->assign($request->user(), $task, $data);

        return response()->json([
            'success' => true,
            'message' => 'Preschool automation task assigned successfully.',
            'data' => [
                'task' => $updated,
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
