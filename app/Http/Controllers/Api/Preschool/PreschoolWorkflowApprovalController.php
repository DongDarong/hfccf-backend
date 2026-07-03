<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolWorkflowApproval;
use App\Models\PreschoolWorkflowInstance;
use App\Models\User;
use App\Services\PreschoolWorkflowApprovalService;
use App\Services\PreschoolWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolWorkflowApprovalController extends Controller
{
    public function index(Request $request, PreschoolWorkflowApprovalService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow approvals retrieved successfully.',
            'data' => $service->listApprovals($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function store(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowApprovalService $service, PreschoolWorkflowService $workflowService): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'workflow_step_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_workflow_steps,id'],
            'workflow_step_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'requested_to_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'requested_to_role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'decision_notes' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $approval = $service->requestApproval($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow approval requested successfully.',
            'data' => [
                'approval' => $workflowService->formatApproval($approval),
            ],
        ], Response::HTTP_CREATED);
    }

    public function approve(Request $request, PreschoolWorkflowApproval $approval, PreschoolWorkflowApprovalService $service): JsonResponse
    {
        return $this->decide($request, $approval, $service, 'approved', 'Preschool workflow approval approved successfully.');
    }

    public function reject(Request $request, PreschoolWorkflowApproval $approval, PreschoolWorkflowApprovalService $service): JsonResponse
    {
        return $this->decide($request, $approval, $service, 'rejected', 'Preschool workflow approval rejected successfully.');
    }

    public function returnApproval(Request $request, PreschoolWorkflowApproval $approval, PreschoolWorkflowApprovalService $service): JsonResponse
    {
        return $this->decide($request, $approval, $service, 'returned', 'Preschool workflow approval returned successfully.');
    }

    public function cancel(Request $request, PreschoolWorkflowApproval $approval, PreschoolWorkflowApprovalService $service): JsonResponse
    {
        return $this->decide($request, $approval, $service, 'cancelled', 'Preschool workflow approval cancelled successfully.');
    }

    private function decide(Request $request, PreschoolWorkflowApproval $approval, PreschoolWorkflowApprovalService $service, string $status, string $message): JsonResponse
    {
        if ($response = $this->authorizeApprover($request->user(), $approval)) {
            return $response;
        }

        $data = $request->validate([
            'decision_notes' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = match ($status) {
            'approved' => $service->approve($approval, $data, $request->user()),
            'rejected' => $service->reject($approval, $data, $request->user()),
            'returned' => $service->returnApproval($approval, $data, $request->user()),
            default => $service->cancel($approval, $data, $request->user()),
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'approval' => app(PreschoolWorkflowService::class)->formatApproval($updated),
            ],
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

    private function authorizeWriter(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }

    private function authorizeApprover(?User $user, PreschoolWorkflowApproval $approval): ?JsonResponse
    {
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'data' => null], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return null;
        }

        if ($approval->requested_to_user_id === $user->id || $approval->requested_to_role === $user->role_code) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.', 'data' => null], Response::HTTP_FORBIDDEN);
    }
}
