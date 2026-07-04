<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PreschoolWorkflowSyncRun;
use App\Services\PreschoolWorkflowSyncService;
use App\Services\PreschoolWorkflowSyncRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolWorkflowSyncController extends Controller
{
    public function preview(Request $request, PreschoolWorkflowSyncService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $filters = $this->validateFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Workflow sync preview retrieved successfully.',
            'data' => $service->preview($filters, $request->user()),
        ], Response::HTTP_OK);
    }

    public function run(Request $request, PreschoolWorkflowSyncService $service, PreschoolWorkflowSyncRunService $runService): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $filters = $this->validateFilters($request);
        $dryRun = (bool) ($filters['dry_run'] ?? false);
        unset($filters['dry_run']);

        $result = $dryRun
            ? $service->preview($filters, $request->user())
            : $runService->run($filters, $request->user());

        return response()->json([
            'success' => true,
            'message' => $dryRun ? 'Workflow sync preview completed successfully.' : 'Workflow sync completed successfully.',
            'data' => $result,
        ], Response::HTTP_OK);
    }

    public function index(Request $request, PreschoolWorkflowSyncRunService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $filters = $this->validateHistoryFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Workflow sync history retrieved successfully.',
            'data' => $service->listRuns($filters, $request->user()),
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolWorkflowSyncRun $run, PreschoolWorkflowSyncRunService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Workflow sync run retrieved successfully.',
            'data' => $service->showRun($run, $request->user()),
        ], Response::HTTP_OK);
    }

    public function items(Request $request, PreschoolWorkflowSyncRun $run, PreschoolWorkflowSyncRunService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $filters = $this->validateItemFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Workflow sync run items retrieved successfully.',
            'data' => $service->listRunItems($run, $filters, $request->user()),
        ], Response::HTTP_OK);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'definition_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'batch_size' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'dry_run' => ['sometimes', 'nullable', 'boolean'],
        ]);
    }

    private function validateHistoryFilters(Request $request): array
    {
        return $request->validate([
            'mode' => ['sometimes', 'nullable', 'string', 'in:preview,run'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'definition_key' => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'started_by_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function validateItemFilters(Request $request): array
    {
        return $request->validate([
            'result_status' => ['sometimes', 'nullable', 'string', 'in:created,existing,skipped,failed'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
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
