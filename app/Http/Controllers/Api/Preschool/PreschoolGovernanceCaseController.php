<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolGovernanceCase;
use App\Models\User;
use App\Support\PreschoolGovernanceCaseService;
use App\Support\PreschoolLifecycleAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Governance cases stay admin-only so institutional risks can be reviewed and
 * resolved without exposing any new staff or guardian workflow.
 */
class PreschoolGovernanceCaseController extends Controller
{
    public function __construct(
        private readonly PreschoolGovernanceCaseService $caseService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'owner_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'reviewer_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'escalation_officer_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'status' => ['sometimes', 'nullable', Rule::in($this->statusValues())],
            'severity' => ['sometimes', 'nullable', Rule::in($this->severityValues())],
            'source_type' => ['sometimes', 'nullable', Rule::in($this->sourceValues())],
            'source_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_urgent' => ['sometimes', 'nullable', 'boolean'],
            'due_from' => ['sometimes', 'nullable', 'date'],
            'due_to' => ['sometimes', 'nullable', 'date'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date'],
            'updated_from' => ['sometimes', 'nullable', 'date'],
            'updated_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->caseService->index($validated, (int) ($validated['per_page'] ?? 20), (int) ($validated['page'] ?? 1));

        $this->recordAudit($request, 'governance_case_listed', 'governance_case_collection', 'list', null, [
            'filters' => $validated,
            'resultCount' => count($payload['items'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance cases retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payload = $this->caseService->detail($case);

        $this->recordAudit($request, 'governance_case_viewed', 'governance_case', (string) $case->case_key, $case->toArray(), $payload['record'] ?? null, [
            'detail' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'source_type' => ['required', 'string', Rule::in($this->sourceValues())],
            'source_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_context' => ['sometimes', 'nullable', 'array'],
            'severity' => ['sometimes', 'nullable', Rule::in($this->severityValues())],
            'risk_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in($this->statusValues())],
            'is_urgent' => ['sometimes', 'nullable', 'boolean'],
            'urgent_reason' => ['sometimes', 'nullable', 'string'],
            'owner_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'reviewer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'escalation_officer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'latest_note' => ['sometimes', 'nullable', 'string'],
            'resolution_note' => ['sometimes', 'nullable', 'string'],
        ]);

        $case = $this->caseService->create($validated, $request->user());
        $payload = $this->caseService->detail($case);

        $this->recordAudit($request, 'governance_case_created', 'governance_case', (string) $case->case_key, null, $payload['record'] ?? null, [
            'sourceType' => $case->source_type,
            'sourceReference' => $case->source_reference,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case created successfully.',
            'data' => $payload,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:191'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'source_type' => ['sometimes', 'nullable', Rule::in($this->sourceValues())],
            'source_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_context' => ['sometimes', 'nullable', 'array'],
            'severity' => ['sometimes', 'nullable', Rule::in($this->severityValues())],
            'risk_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'is_urgent' => ['sometimes', 'nullable', 'boolean'],
            'urgent_reason' => ['sometimes', 'nullable', 'string'],
            'owner_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'reviewer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'escalation_officer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'latest_note' => ['sometimes', 'nullable', 'string'],
            'resolution_note' => ['sometimes', 'nullable', 'string'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->update($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_updated', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'changes' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case updated successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function assign(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'owner_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'reviewer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'escalation_officer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', Rule::in($this->workflowStatusValues())],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->assign($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_assigned', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'changes' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case assignments updated successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function escalate(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'escalation_officer_user_id' => ['sometimes', 'nullable', 'string', 'max:16', 'exists:users,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->escalate($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_escalated', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case escalated successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function resolve(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->resolve($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_resolved', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'resolutionNote' => $validated['resolution_note'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case resolved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function close(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->close($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_closed', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case closed successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function reopen(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $before = $this->caseService->preview($case->loadMissing(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])->loadCount(['events', 'evidence']));
        $updated = $this->caseService->reopen($case, $validated, $request->user());
        $payload = $this->caseService->detail($updated);

        $this->recordAudit($request, 'governance_case_reopened', 'governance_case', (string) $case->case_key, $before, $payload['record'] ?? null, [
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case reopened successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function evidence(Request $request, PreschoolGovernanceCase $case): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'evidence_type' => ['required', 'string', 'max:64'],
            'evidence_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'evidence_label' => ['sometimes', 'nullable', 'string', 'max:191'],
            'evidence_description' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $evidence = $this->caseService->addEvidence($case, $validated, $request->user());
        $payload = $this->caseService->detail($case->fresh()->load($this->relations())->loadCount(['events', 'evidence']));

        $this->recordAudit($request, 'governance_case_evidence_added', 'governance_case', (string) $case->case_key, null, [
            'evidence' => [
                'id' => $evidence->id,
                'evidenceType' => $evidence->evidence_type,
                'evidenceReference' => $evidence->evidence_reference,
                'evidenceLabel' => $evidence->evidence_label,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case evidence added successfully.',
            'data' => $payload,
        ], Response::HTTP_CREATED);
    }

    public function assignees(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance case assignees retrieved successfully.',
            'data' => [
                'items' => $this->caseService->assignees($validated),
            ],
        ], Response::HTTP_OK);
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

    /**
     * @param  array<string, mixed>|null  $previousState
     * @param  array<string, mixed>|null  $newState
     * @param  array<string, mixed>  $context
     */
    private function recordAudit(Request $request, string $actionType, string $entityType, string $entityId, mixed $previousState = null, mixed $newState = null, array $context = []): void
    {
        $user = $request->user();

        $this->auditService->recordSafely([
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'term_id' => $request->integer('term_id') ?: null,
            'report_period_id' => $request->integer('report_period_id') ?: null,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'request_context' => $this->auditService->requestContext($request, $context),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statusValues(): array
    {
        return PreschoolGovernanceCaseService::STATUSES;
    }

    /**
     * @return array<int, string>
     */
    private function severityValues(): array
    {
        return PreschoolGovernanceCaseService::SEVERITIES;
    }

    /**
     * @return array<int, string>
     */
    private function sourceValues(): array
    {
        return PreschoolGovernanceCaseService::SOURCE_TYPES;
    }

    /**
     * @return array<int, string>
     */
    private function workflowStatusValues(): array
    {
        return PreschoolGovernanceCaseService::WORKFLOW_STATUSES;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student', 'events.actor', 'evidence.creator'];
    }
}
