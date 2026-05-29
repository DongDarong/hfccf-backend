<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Models\PreschoolReportExportRecord;
use App\Support\PreschoolExportGovernanceService;
use App\Support\PreschoolLifecycleAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export governance stays admin-only so immutable export history, archive
 * downloads, and comparison tools remain institutional review utilities
 * instead of becoming a teacher-facing workflow.
 */
class PreschoolExportGovernanceController extends Controller
{
    public function __construct(
        private readonly PreschoolExportGovernanceService $exportGovernanceService,
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
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'actor_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'exported_from' => ['sometimes', 'nullable', 'date'],
            'exported_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $paginator = $this->exportGovernanceService->paginate(
            $validated,
            (int) ($validated['per_page'] ?? 20),
            (int) ($validated['page'] ?? 1),
        );

        $items = $paginator->getCollection()->map(fn (PreschoolReportExportRecord $record): array => $this->exportGovernanceService->previewRecord($record));

        $this->recordAudit($request, 'report_export.archive_viewed', 'report_export_archive', 'list', null, [
            'filters' => $validated,
            'resultCount' => $paginator->total(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool export governance retrieved successfully.',
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

    public function show(Request $request, PreschoolReportExportRecord $exportRecord): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $payload = $this->exportGovernanceService->detail($exportRecord);

        $this->recordAudit($request, 'report_export.archive_viewed', 'report_export_record', (string) $exportRecord->id, null, [
            'detail' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool export record retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function analytics(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'actor_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'exported_from' => ['sometimes', 'nullable', 'date'],
            'exported_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $payload = $this->exportGovernanceService->overview($validated);

        $this->recordAudit($request, 'report_export.analytics_viewed', 'report_export_archive', 'analytics', null, [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool export analytics retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function comparisonOptions(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'actor_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'exported_from' => ['sometimes', 'nullable', 'date'],
            'exported_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool export comparison options retrieved successfully.',
            'data' => $this->exportGovernanceService->options($validated),
        ], Response::HTTP_OK);
    }

    public function compare(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'comparison_mode' => ['required', 'string', 'max:64'],
            'left_context' => ['required', 'array'],
            'right_context' => ['required', 'array'],
        ]);

        $payload = $this->exportGovernanceService->compare($validated);

        $this->recordAudit($request, 'report_export.comparison_viewed', 'report_export_archive', 'comparison', null, [
            'comparisonMode' => $validated['comparison_mode'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool historical comparison retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    public function timeline(Request $request): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'report_period_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
            'actor_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'export_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'export_format' => ['sometimes', 'nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $payload = $this->exportGovernanceService->timeline($validated, (int) ($validated['limit'] ?? 50));

        $this->recordAudit($request, 'report_export.timeline_viewed', 'report_export_archive', 'timeline', null, [
            'filters' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preschool institutional timeline retrieved successfully.',
            'data' => [
                'items' => $payload,
            ],
        ], Response::HTTP_OK);
    }

    public function downloadCsv(Request $request, PreschoolReportExportRecord $exportRecord): StreamedResponse|JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $rows = $this->exportGovernanceService->rowsForRecord($exportRecord);
        $filename = $exportRecord->file_name ?: sprintf(
            'preschool-export-%s-%s.csv',
            $exportRecord->export_type,
            now()->format('Ymd-His'),
        );

        $this->recordAudit($request, 'report_export.downloaded', 'report_export_record', (string) $exportRecord->id, $exportRecord, [
            'exportType' => $exportRecord->export_type,
            'exportFormat' => $exportRecord->export_format,
            'rowCount' => $rows->count(),
        ]);

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

    private function authorizeAdmin(?\App\Models\User $user): ?JsonResponse
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
    private function recordAudit(Request $request, string $actionType, string $entityType, string $entityId, ?PreschoolReportExportRecord $record = null, array $context = []): void
    {
        $user = $request->user();

        $this->auditService->recordSafely([
            'actor_user_id' => $user?->id,
            'actor_role' => $user?->role_code,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'academic_year_id' => $record?->academic_year_id,
            'term_id' => $record?->term_id,
            'report_period_id' => $record?->report_period_id,
            'previous_state' => null,
            'new_state' => $context,
            'request_context' => $this->auditService->requestContext($request, $context),
        ]);
    }
}
