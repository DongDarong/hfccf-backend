<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Support\PreschoolExportGovernanceService;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolReporting\PreschoolReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PreschoolReportingController extends Controller
{
    public function __construct(
        private readonly PreschoolReportingService $reportingService,
        private readonly PreschoolExportGovernanceService $exportGovernanceService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    public function definitions(Request $request): JsonResponse
    {
        if ($response = $this->authorizeRead($request->user(), 'operations')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preschool report definitions retrieved successfully.',
            'data' => [
                'definitions' => $this->reportingService->definitions($request->user()),
            ],
        ], Response::HTTP_OK);
    }

    public function dashboard(Request $request): JsonResponse
    {
        if ($response = $this->authorizeRead($request->user(), 'operations')) {
            return $response;
        }

        $validated = $this->validateFilters($request);
        $payload = $this->reportingService->dashboard($request->user(), $validated);
        $this->recordViewed($request, 'dashboard', $validated, $payload);

        return response()->json([
            'success' => true,
            'message' => 'Preschool reporting dashboard retrieved successfully.',
            'data' => [
                'dashboard' => $payload,
                'filters' => $payload['filters'] ?? [],
            ],
        ], Response::HTTP_OK);
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->section($request, 'attendance');
    }

    public function attendanceTrend(Request $request): JsonResponse
    {
        return $this->section($request, 'attendance');
    }

    public function attendanceClass(Request $request): JsonResponse
    {
        return $this->section($request, 'attendance');
    }

    public function attendanceStudent(Request $request): JsonResponse
    {
        return $this->section($request, 'attendance');
    }

    public function assessments(Request $request): JsonResponse
    {
        return $this->section($request, 'assessments');
    }

    public function assessmentPerformance(Request $request): JsonResponse
    {
        return $this->section($request, 'assessments');
    }

    public function assessmentCompletion(Request $request): JsonResponse
    {
        return $this->section($request, 'assessments');
    }

    public function health(Request $request): JsonResponse
    {
        return $this->section($request, 'health');
    }

    public function healthIncidents(Request $request): JsonResponse
    {
        return $this->section($request, 'health');
    }

    public function healthVaccinations(Request $request): JsonResponse
    {
        return $this->section($request, 'health');
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->section($request, 'payments');
    }

    public function paymentRevenue(Request $request): JsonResponse
    {
        return $this->section($request, 'payments');
    }

    public function paymentOutstanding(Request $request): JsonResponse
    {
        return $this->section($request, 'payments');
    }

    public function enrollments(Request $request): JsonResponse
    {
        return $this->section($request, 'enrollment');
    }

    public function enrollmentTrends(Request $request): JsonResponse
    {
        return $this->section($request, 'enrollment');
    }

    public function guardians(Request $request): JsonResponse
    {
        return $this->section($request, 'guardians');
    }

    public function guardianIssues(Request $request): JsonResponse
    {
        return $this->section($request, 'guardians');
    }

    public function classroom(Request $request): JsonResponse
    {
        return $this->section($request, 'classroom');
    }

    public function compliance(Request $request): JsonResponse
    {
        return $this->section($request, 'compliance');
    }

    public function export(Request $request): JsonResponse
    {
        if ($response = $this->authorizeExport($request->user())) {
            return $response;
        }

        $validated = $request->validate([
            'section' => ['required', 'string', 'max:32'],
            'format' => ['sometimes', 'nullable', 'string', 'in:csv,excel,xlsx,pdf,html,print'],
            'academicYearId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'termId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'dateFrom' => ['sometimes', 'nullable', 'date'],
            'dateTo' => ['sometimes', 'nullable', 'date'],
            'classId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'studentId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'teacherId' => ['sometimes', 'nullable', 'string', 'max:16'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'reportPeriodId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
        ]);

        $section = $this->normalizeSection($validated['section']);
        if ($response = $this->authorizeRead($request->user(), $section)) {
            return $response;
        }

        $payload = $this->reportingService->export($request->user(), $section, $validated['format'] ?? 'csv', $validated);

        $exportRecord = $this->exportGovernanceService->recordExport([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'export_type' => $section.'_report',
            'export_format' => $payload['format'],
            'export_source' => 'live',
            'academic_year_id' => $validated['academicYearId'] ?? null,
            'term_id' => $validated['termId'] ?? null,
            'report_period_id' => $validated['reportPeriodId'] ?? null,
            'filters' => $validated,
            'record_count' => $payload['recordCount'] ?? 0,
            'file_name' => $payload['filename'],
            'checksum' => null,
            'export_reason' => null,
            'request_context' => $this->auditService->requestContext($request, [
                'reportType' => $section,
                'exportFormat' => $payload['format'],
            ]),
            'exported_at' => now(),
        ]);

        $this->recordExported($request, $section, $validated, $payload, $exportRecord->id);

        return response()->json([
            'success' => true,
            'message' => 'Preschool report export created successfully.',
            'data' => [
                'export' => $payload,
                'exportRecordId' => $exportRecord->id,
            ],
        ], Response::HTTP_OK);
    }

    private function section(Request $request, string $section): JsonResponse
    {
        if ($response = $this->authorizeRead($request->user(), $section)) {
            return $response;
        }

        $validated = $this->validateFilters($request);
        $payload = $this->reportingService->section($request->user(), $section, $validated);
        $this->recordViewed($request, $section, $validated, $payload);

        return response()->json([
            'success' => true,
            'message' => 'Preschool report retrieved successfully.',
            'data' => $payload,
        ], Response::HTTP_OK);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'academicYearId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'termId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'dateFrom' => ['sometimes', 'nullable', 'date'],
            'dateTo' => ['sometimes', 'nullable', 'date'],
            'classId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_classes,id'],
            'studentId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_students,id'],
            'teacherId' => ['sometimes', 'nullable', 'string', 'max:16'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'reportPeriodId' => ['sometimes', 'nullable', 'integer', 'exists:preschool_report_periods,id'],
        ]);
    }

    private function authorizeRead(?object $user, string $section): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->role_code === 'teacher-preschool' && in_array($section, ['payments', 'guardians', 'compliance'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function authorizeExport(?object $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'data' => null,
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function normalizeSection(string $section): string
    {
        $section = strtolower(trim($section));

        return match ($section) {
            'assessment' => 'assessments',
            'billing', 'payment' => 'payments',
            'enrollment', 'enrollments' => 'enrollment',
            default => $section,
        };
    }

    private function recordViewed(Request $request, string $section, array $filters, array $payload): void
    {
        $this->auditService->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => 'report.generated',
            'entity_type' => 'preschool_report',
            'entity_id' => $section,
            'academic_year_id' => $filters['academicYearId'] ?? null,
            'term_id' => $filters['termId'] ?? null,
            'report_period_id' => $filters['reportPeriodId'] ?? null,
            'new_state' => [
                'reportType' => $section,
                'filters' => $filters,
                'generatedAt' => $payload['generatedAt'] ?? now()->toISOString(),
            ],
            'request_context' => $this->auditService->requestContext($request, [
                'reportType' => $section,
                'filters' => $filters,
            ]),
        ]);
    }

    private function recordExported(Request $request, string $section, array $filters, array $payload, int|string $exportRecordId): void
    {
        $this->auditService->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => 'report_export.created',
            'entity_type' => 'preschool_report_export',
            'entity_id' => (string) $exportRecordId,
            'academic_year_id' => $filters['academicYearId'] ?? null,
            'term_id' => $filters['termId'] ?? null,
            'report_period_id' => $filters['reportPeriodId'] ?? null,
            'new_state' => [
                'reportType' => $section,
                'exportFormat' => $payload['format'] ?? 'csv',
                'generatedAt' => $payload['generatedAt'] ?? now()->toISOString(),
                'filters' => $filters,
            ],
            'request_context' => $this->auditService->requestContext($request, [
                'reportType' => $section,
                'exportFormat' => $payload['format'] ?? 'csv',
            ]),
        ]);
    }
}
