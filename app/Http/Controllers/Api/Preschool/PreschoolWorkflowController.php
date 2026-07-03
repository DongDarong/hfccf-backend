<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolWorkflowInstance;
use App\Models\User;
use App\Services\PreschoolWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolWorkflowController extends Controller
{
    public function index(Request $request, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflows retrieved successfully.',
            'data' => $service->listInstances($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function summary(Request $request, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow summary retrieved successfully.',
            'data' => $service->summary($request->user(), $request->query()),
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow retrieved successfully.',
            'data' => $service->show($workflow, $request->user()),
        ], Response::HTTP_OK);
    }

    public function store(Request $request, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'workflow_definition_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_workflow_definitions,id'],
            'workflow_definition_key' => ['sometimes', 'nullable', 'string', 'max:100', 'exists:preschool_workflow_definitions,key'],
            'source_type' => ['required', 'string', 'max:100'],
            'source_id' => ['required', 'string', 'max:191'],
            'source_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'current_step_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_workflow_steps,id'],
            'current_step_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:open,in_progress,pending_approval,approved,rejected,returned,completed,cancelled,escalated,overdue'],
            'priority' => ['sometimes', 'nullable', 'in:low,normal,high,urgent'],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'assigned_role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $workflow = $service->create($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow created successfully.',
            'data' => [
                'workflow' => $service->formatInstance($workflow, true),
            ],
        ], Response::HTTP_CREATED);
    }

    public function assign(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'assigned_to_user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id'],
            'assigned_role' => ['sometimes', 'nullable', 'string', 'max:64'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $updated = $service->assign($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow assignment updated successfully.',
            'data' => [
                'workflow' => $service->formatInstance($updated, true),
            ],
        ], Response::HTTP_OK);
    }

    public function transition(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'workflow_step_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_workflow_steps,id'],
            'workflow_step_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'in:open,in_progress,pending_approval,approved,rejected,returned,completed,cancelled,escalated,overdue'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $service->transition($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow transitioned successfully.',
            'data' => [
                'workflow' => $service->formatInstance($updated, true),
            ],
        ], Response::HTTP_OK);
    }

    public function complete(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $service->complete($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow completed successfully.',
            'data' => [
                'workflow' => $service->formatInstance($updated, true),
            ],
        ], Response::HTTP_OK);
    }

    public function cancel(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $service->cancel($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow cancelled successfully.',
            'data' => [
                'workflow' => $service->formatInstance($updated, true),
            ],
        ], Response::HTTP_OK);
    }

    public function escalate(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeWriter($request->user())) {
            return $response;
        }

        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $service->escalate($workflow, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow escalated successfully.',
            'data' => [
                'workflow' => $service->formatInstance($updated, true),
            ],
        ], Response::HTTP_OK);
    }

    public function timeline(Request $request, PreschoolWorkflowInstance $workflow, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow timeline retrieved successfully.',
            'data' => [
                'items' => $service->timeline($workflow),
            ],
        ], Response::HTTP_OK);
    }

    public function definitions(Request $request, PreschoolWorkflowService $service): JsonResponse
    {
        if ($response = $this->authorizeViewer($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool workflow definitions retrieved successfully.',
            'data' => [
                'items' => $service->listDefinitions(),
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
}
