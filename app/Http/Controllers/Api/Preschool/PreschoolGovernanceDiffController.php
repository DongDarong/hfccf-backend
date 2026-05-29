<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PreschoolGovernanceDiffService;
use App\Support\PreschoolLifecycleAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Governance diff analysis stays admin-only so comparison, review, and
 * integrity review actions remain institutional and never become a staff write
 * workflow.
 */
class PreschoolGovernanceDiffController extends Controller
{
    public function __construct(
        private readonly PreschoolGovernanceDiffService $diffService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->diffService->summary($validated);

        $this->recordAudit($request, 'governance_diff.summary_viewed', 'governance_diff_summary', 'summary', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance diff summary retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function compare(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'comparison_mode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'metric_group' => ['sometimes', 'nullable', 'string', 'max:64'],
            'left_context_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'left_snapshot_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_snapshots,id'],
            'left_export_record_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_export_records,id'],
            'left_academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'left_term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'left_report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'left_class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'left_student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'left_search' => ['sometimes', 'nullable', 'string', 'max:191'],
            'right_context_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'right_snapshot_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_snapshots,id'],
            'right_export_record_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_export_records,id'],
            'right_academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'right_term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'right_report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'right_class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'right_student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'right_search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->diffService->compare($validated);

        $this->recordAudit($request, 'governance_diff.generated', 'governance_diff', (string) ($payload['reviewKey'] ?? 'diff'), [
            'comparisonMode' => $validated['comparison_mode'] ?? $payload['comparisonMode'] ?? null,
            'summary' => $payload['summary'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance diff comparison retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function integrityReview(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->diffService->integrityReview($validated);

        $this->recordAudit($request, 'integrity_review.viewed', 'institutional_integrity_review', 'system', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool integrity review retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function showIntegrityReview(Request $request, string $context): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->diffService->integrityContext($context, $validated);

        $this->recordAudit($request, 'integrity_review.viewed', 'institutional_integrity_review', $context, [
            'context' => $context,
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool integrity context retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function review(Request $request, string $context): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'review_action' => ['required', 'string', 'max:64'],
            'review_note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'severity' => ['sometimes', 'nullable', 'string', 'max:16'],
        ]);

        $resolved = $this->diffService->resolveContext($context, []);
        $reviewKey = (string) ($resolved['reviewKey'] ?? $context);

        $this->auditService->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => 'integrity_review.'.$validated['review_action'],
            'entity_type' => 'institutional_integrity_review',
            'entity_id' => $reviewKey,
            'academic_year_id' => $this->contextAcademicYearId($resolved),
            'term_id' => $this->contextTermId($resolved),
            'report_period_id' => $this->contextReportPeriodId($resolved),
            'previous_state' => null,
            'new_state' => [
                'context' => $resolved['context'] ?? [],
                'reviewAction' => $validated['review_action'],
                'note' => $validated['review_note'] ?? null,
                'severity' => $validated['severity'] ?? null,
                'reviewStatus' => $validated['review_action'],
            ],
            'request_context' => $this->auditService->requestContext($request, [
                'context' => $context,
                'reviewAction' => $validated['review_action'],
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool integrity review action recorded successfully.',
            'data' => [
                'reviewKey' => $reviewKey,
                'reviewAction' => $validated['review_action'],
                'recordedAt' => now()->toISOString(),
            ],
        ], Response::HTTP_OK);
    }

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

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordAudit(Request $request, string $actionType, string $entityType, string $entityId, array $context = []): void
    {
        $user = $request->user();

        $this->auditService->recordSafely([
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $this->contextAcademicYearId($context),
            'term_id' => $this->contextTermId($context),
            'report_period_id' => $this->contextReportPeriodId($context),
            'previous_state' => null,
            'new_state' => $context,
            'request_context' => $this->auditService->requestContext($request, $context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextAcademicYearId(array $context): ?int
    {
        foreach ([
            'academicYearId',
            'academic_year_id',
            'context.academicYearId',
            'context.academic_year_id',
            'filters.academic_year_id',
            'filters.academicYearId',
        ] as $path) {
            $value = data_get($context, $path);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextTermId(array $context): ?int
    {
        foreach ([
            'termId',
            'term_id',
            'context.termId',
            'context.term_id',
            'filters.term_id',
            'filters.termId',
        ] as $path) {
            $value = data_get($context, $path);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextReportPeriodId(array $context): ?int
    {
        foreach ([
            'reportPeriodId',
            'report_period_id',
            'context.reportPeriodId',
            'context.report_period_id',
            'filters.report_period_id',
            'filters.reportPeriodId',
        ] as $path) {
            $value = data_get($context, $path);
            if ($value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return null;
    }
}
