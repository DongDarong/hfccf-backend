<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolReportSnapshot;
use App\Models\User;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolSnapshotArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Snapshot archive access stays admin-only so immutable report history can be
 * reviewed, compared, and exported without exposing a new teacher workflow.
 */
class PreschoolReportSnapshotController extends Controller
{
    public function __construct(
        private readonly PreschoolSnapshotArchiveService $archiveService,
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
            'snapshot_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'lifecycle_state' => ['sometimes', 'nullable', 'string', 'max:32'],
            'generated_from' => ['sometimes', 'nullable', 'date'],
            'generated_to' => ['sometimes', 'nullable', 'date'],
            'generated_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $paginator = $this->archiveService->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 20),
            (int) ($validated['page'] ?? 1),
        );

        $items = $paginator->getCollection()->map(fn (PreschoolReportSnapshot $snapshot): array => $this->archiveService->preview($snapshot));

        $this->recordAudit($request, 'report_snapshot.archive_viewed', 'report_snapshot_archive', 'list', null, [
            'filters' => $validated,
            'resultCount' => $paginator->total(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool snapshot archive retrieved successfully.',
            'data' => [
                'items' => $items->values()->all(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PreschoolReportSnapshot $snapshot): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payload = $this->archiveService->detail($snapshot);

        $this->recordAudit($request, 'report_snapshot.archive_viewed', 'report_snapshot', (string) $snapshot->id, $snapshot, [
            'detail' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool snapshot retrieved successfully.',
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
            'snapshot_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'lifecycle_state' => ['sometimes', 'nullable', 'string', 'max:32'],
            'generated_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'generated_from' => ['sometimes', 'nullable', 'date'],
            'generated_to' => ['sometimes', 'nullable', 'date'],
        ]);

        $payload = $this->archiveService->analytics($validated);

        $this->recordAudit($request, 'report_snapshot.analytics_viewed', 'report_snapshot_archive', 'analytics', null, [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool snapshot analytics retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function exportCsv(Request $request): StreamedResponse|JsonResponse
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
            'generated_from' => ['sometimes', 'nullable', 'date'],
            'generated_to' => ['sometimes', 'nullable', 'date'],
            'generated_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $rows = $this->archiveService->exportRows($validated);

        $this->recordAudit($request, 'report_snapshot.exported', 'report_snapshot_archive', 'export', null, [
            'filters' => $validated,
            'rowCount' => $rows->count(),
        ]);

        $filename = sprintf('preschool-snapshot-archive-%s.csv', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'snapshot_id',
                'snapshot_type',
                'lifecycle_state',
                'snapshot_version',
                'generated_at',
                'generated_by_user_id',
                'generated_by_name',
                'academic_year',
                'term',
                'report_period',
                'student',
                'class',
                'source_status',
                'finalized_assessments',
                'average_score',
                'attendance_count',
                'present_count',
                'late_count',
                'absent_count',
                'excused_count',
                'observation_count',
                'student_count',
            ]);

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['id'] ?? '',
                    $row['snapshotType'] ?? '',
                    $row['lifecycleState'] ?? '',
                    $row['snapshotVersion'] ?? '',
                    $row['generatedAt'] ?? '',
                    $row['generatedByUserId'] ?? '',
                    $row['generatedBy']['displayName'] ?? '',
                    $row['academicYear']['label'] ?? $row['academicYear']['code'] ?? '',
                    $row['term']['name'] ?? $row['term']['code'] ?? '',
                    $row['reportPeriod']['label'] ?? '',
                    $row['student']['fullName'] ?? '',
                    $row['class']['name'] ?? '',
                    $row['sourceStatus'] ?? '',
                    $row['reportSummary']['finalizedAssessments'] ?? '',
                    $row['reportSummary']['averageScore'] ?? '',
                    $row['attendanceSummary']['attendanceCount'] ?? '',
                    $row['attendanceSummary']['presentCount'] ?? '',
                    $row['attendanceSummary']['lateCount'] ?? '',
                    $row['attendanceSummary']['absentCount'] ?? '',
                    $row['attendanceSummary']['excusedCount'] ?? '',
                    $row['reportSummary']['observationCount'] ?? '',
                    $row['progressSummary']['studentCount'] ?? '',
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
    private function recordAudit(Request $request, string $actionType, string $entityType, string $entityId, ?PreschoolReportSnapshot $snapshot = null, array $context = []): void
    {
        $user = $request->user();

        $this->auditService->recordSafely([
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $snapshot?->academic_year_id,
            'term_id' => $snapshot?->term_id,
            'report_period_id' => $snapshot?->report_period_id,
            'previous_state' => null,
            'new_state' => $context,
            'request_context' => $this->auditService->requestContext($request, $context),
        ]);
    }
}
