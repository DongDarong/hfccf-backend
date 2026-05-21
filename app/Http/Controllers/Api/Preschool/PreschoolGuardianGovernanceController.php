<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preschool\AcknowledgeGovernanceIssueRequest;
use App\Http\Requests\Preschool\AssignGovernanceIssueRequest;
use App\Http\Requests\Preschool\DismissGovernanceIssueRequest;
use App\Http\Requests\Preschool\ResolveGovernanceIssueRequest;
use App\Http\Resources\Preschool\PreschoolGuardianGovernanceIssueResource;
use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\User;
use App\Support\PreschoolGuardianGovernanceService;
use App\Support\PreschoolGuardianIssueAggregationService;
use App\Support\PreschoolGuardianIssueLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreschoolGuardianGovernanceController extends Controller
{
    /**
     * All governance endpoints stay admin-only so that lifecycle transitions
     * and aggregation metrics never leak to teacher-preschool or lower roles.
     */

    // ── Sync ──────────────────────────────────────────────────────────────────

    public function sync(
        Request $request,
        PreschoolGuardianGovernanceService $governance,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $result = $governance->syncAll($request->user());

        return response()->json($result, Response::HTTP_OK);
    }

    // ── Issue list / detail ───────────────────────────────────────────────────

    public function index(
        Request $request,
        PreschoolGuardianGovernanceService $governance,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paginator = $governance->listIssues($request->only([
            'status', 'severity', 'priority', 'issue_type',
            'student_id', 'guardian_id', 'assigned_to_user_id',
            'unassigned', 'active_only', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Governance issues retrieved successfully.',
            'data' => PreschoolGuardianGovernanceIssueResource::collection($paginator),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(
        Request $request,
        PreschoolGuardianGovernanceIssue $issue,
        PreschoolGuardianGovernanceService $governance,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $issue->load('assignedTo');

        return response()->json([
            'success' => true,
            'message' => 'Governance issue retrieved successfully.',
            'data' => new PreschoolGuardianGovernanceIssueResource($issue),
        ], Response::HTTP_OK);
    }

    // ── Lifecycle actions ─────────────────────────────────────────────────────

    public function acknowledge(
        AcknowledgeGovernanceIssueRequest $request,
        PreschoolGuardianGovernanceIssue $issue,
        PreschoolGuardianIssueLifecycleService $lifecycle,
    ): JsonResponse {
        $updated = $lifecycle->acknowledge($issue, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Issue acknowledged.',
            'data' => new PreschoolGuardianGovernanceIssueResource($updated),
        ], Response::HTTP_OK);
    }

    public function assign(
        AssignGovernanceIssueRequest $request,
        PreschoolGuardianGovernanceIssue $issue,
        PreschoolGuardianIssueLifecycleService $lifecycle,
    ): JsonResponse {
        $validated = $request->validated();
        $updated = $lifecycle->assign(
            $issue,
            $request->user(),
            $validated['assigned_to_user_id'],
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Issue assigned.',
            'data' => new PreschoolGuardianGovernanceIssueResource($updated),
        ], Response::HTTP_OK);
    }

    public function resolve(
        ResolveGovernanceIssueRequest $request,
        PreschoolGuardianGovernanceIssue $issue,
        PreschoolGuardianIssueLifecycleService $lifecycle,
    ): JsonResponse {
        $updated = $lifecycle->resolve($issue, $request->user(), $request->input('notes'));

        return response()->json([
            'success' => true,
            'message' => 'Issue resolved.',
            'data' => new PreschoolGuardianGovernanceIssueResource($updated),
        ], Response::HTTP_OK);
    }

    public function dismiss(
        DismissGovernanceIssueRequest $request,
        PreschoolGuardianGovernanceIssue $issue,
        PreschoolGuardianIssueLifecycleService $lifecycle,
    ): JsonResponse {
        $updated = $lifecycle->dismiss($issue, $request->user(), $request->input('notes'));

        return response()->json([
            'success' => true,
            'message' => 'Issue dismissed.',
            'data' => new PreschoolGuardianGovernanceIssueResource($updated),
        ], Response::HTTP_OK);
    }

    // ── Aggregation ───────────────────────────────────────────────────────────

    public function dashboardSummary(
        Request $request,
        PreschoolGuardianIssueAggregationService $aggregation,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Governance dashboard summary retrieved successfully.',
            'data' => $aggregation->dashboardSummary($request->user()),
        ], Response::HTTP_OK);
    }

    public function staleIssues(
        Request $request,
        PreschoolGuardianIssueAggregationService $aggregation,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paginator = $aggregation->staleIssues($request->only(['per_page']));

        return response()->json([
            'success' => true,
            'message' => 'Stale governance issues retrieved successfully.',
            'data' => PreschoolGuardianGovernanceIssueResource::collection($paginator),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function recurringIssues(
        Request $request,
        PreschoolGuardianIssueAggregationService $aggregation,
    ): JsonResponse {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $paginator = $aggregation->recurringIssues($request->only(['per_page']));

        return response()->json([
            'success' => true,
            'message' => 'Recurring governance issues retrieved successfully.',
            'data' => PreschoolGuardianGovernanceIssueResource::collection($paginator),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], Response::HTTP_OK);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    private function authorizeAdmin(?User $user): ?JsonResponse
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
