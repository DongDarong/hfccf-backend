<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PreschoolInstitutionalReconstructionService;
use App\Support\PreschoolLifecycleAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Institutional governance review stays admin-only so historical state
 * reconstruction, replay, and anomaly review can be audited without exposing
 * any new write surface or teacher-facing workflow.
 */
class PreschoolInstitutionalGovernanceController extends Controller
{
    public function __construct(
        private readonly PreschoolInstitutionalReconstructionService $reconstructionService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    public function review(Request $request): JsonResponse
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
            'actor_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:191'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'generated_from' => ['sometimes', 'nullable', 'date'],
            'generated_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->reconstructionService->review($validated);

        $this->recordAudit($request, 'governance.review.viewed', 'institutional_governance_review', 'review', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance review retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function analytics(Request $request): JsonResponse
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
            'actor_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:191'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'generated_from' => ['sometimes', 'nullable', 'date'],
            'generated_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->reconstructionService->analytics($validated);

        $this->recordAudit($request, 'governance.analytics.viewed', 'institutional_governance_review', 'analytics', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool governance analytics retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function reconstruct(Request $request): JsonResponse
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
            'snapshot_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'lifecycle_state' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->reconstructionService->reconstruct($validated);

        $this->recordAudit($request, 'governance.reconstruction.viewed', 'institutional_reconstruction', 'list', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool institutional reconstruction retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function show(Request $request, string $context): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $this->contextFilters($context, $request);
        $payload = $this->reconstructionService->reconstruct($validated);

        $this->recordAudit($request, 'governance.reconstruction.viewed', 'institutional_reconstruction', $context, [
            'context' => $context,
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool institutional reconstruction context retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function replay(Request $request): JsonResponse
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
            'actor_user_id' => ['sometimes', 'nullable', 'string', 'max:16'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->reconstructionService->replay($validated);

        $this->recordAudit($request, 'governance.replay.viewed', 'institutional_replay', 'timeline', [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool institutional replay retrieved successfully.',
            'data' => $payload,
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
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'term_id' => $request->integer('term_id') ?: null,
            'report_period_id' => $request->integer('report_period_id') ?: null,
            'previous_state' => null,
            'new_state' => $context,
            'request_context' => $this->auditService->requestContext($request, $context),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFilters(string $context, Request $request): array
    {
        $context = trim($context);
        if ($context === '') {
            return $request->validate([
                'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
                'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
                'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
                'class_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
                'student_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
                'snapshot_type' => ['sometimes', 'nullable', 'string', 'max:64'],
                'lifecycle_state' => ['sometimes', 'nullable', 'string', 'max:32'],
                'source' => ['sometimes', 'nullable', 'string', 'max:32'],
                'search' => ['sometimes', 'nullable', 'string', 'max:191'],
            ]);
        }

        [$type, $id] = array_pad(explode(':', $context, 2), 2, null);
        $filters = [
            'academic_year_id' => null,
            'term_id' => null,
            'report_period_id' => null,
            'class_id' => null,
            'student_id' => null,
        ];

        match ($type) {
            'academic-year', 'academic_year' => $filters['academic_year_id'] = is_numeric($id) ? (int) $id : null,
            'term' => $filters['term_id'] = is_numeric($id) ? (int) $id : null,
            'report-period', 'report_period' => $filters['report_period_id'] = is_numeric($id) ? (int) $id : null,
            'class' => $filters['class_id'] = is_numeric($id) ? (int) $id : null,
            'student' => $filters['student_id'] = is_numeric($id) ? (int) $id : null,
            default => null,
        };

        return array_filter($filters, static fn ($value) => $value !== null);
    }
}
