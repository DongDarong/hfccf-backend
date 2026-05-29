<?php

namespace App\Http\Controllers\Api\Preschool;

use App\Http\Controllers\Controller;
use App\Http\Resources\Preschool\PreschoolReportPeriodResource;
use App\Models\PreschoolClass;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolReportPeriodService;
use App\Support\PreschoolReportSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preschool report periods are the reporting freeze boundary. Admins manage
 * their lifecycle; teachers can only read the status for context.
 */
class PreschoolReportPeriodController extends Controller
{
    public function index(Request $request, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAny($request->user())) {
            return $response;
        }

        $student = $this->resolveStudent($request);
        $class = $this->resolveClass($request);

        $periods = $service->reportPeriods($request->user(), $student, $class)->values();

        return response()->json([
            'success' => true,
            'message' => 'Preschool report periods retrieved successfully.',
            'data' => [
                'periods' => $periods,
                'currentContext' => $service->currentContext(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $validated = $request->validate($this->rules());
        $period = $service->create($validated);
        $this->recordAudit($request, 'report_period.created', $period, null, $service->snapshot($period));

        return response()->json([
            'success' => true,
            'message' => 'Report period created successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($period)->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, PreschoolReportPeriod $reportPeriod, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        if ($this->isLocked($reportPeriod) && ! $this->canOverride($request->user(), $request, $reportPeriod->status)) {
            return $this->lockedResponse($request, $reportPeriod, 'This report period is locked.');
        }

        $validated = $request->validate($this->rules($reportPeriod));
        $previous = $service->snapshot($reportPeriod->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']));
        $updated = $service->update($reportPeriod, $validated);
        $this->recordAudit($request, 'report_period.updated', $updated, $previous, $service->snapshot($updated));

        return response()->json([
            'success' => true,
            'message' => 'Report period updated successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function activate(Request $request, PreschoolReportPeriod $reportPeriod, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->snapshot($reportPeriod->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']));
        $updated = $service->activate($reportPeriod);
        $this->recordAudit($request, 'report_period.activated', $updated, $previous, $service->snapshot($updated));

        return response()->json([
            'success' => true,
            'message' => 'Report period activated successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function finalize(Request $request, PreschoolReportPeriod $reportPeriod, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->snapshot($reportPeriod->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']));
        $updated = $service->finalize($reportPeriod, $request->user());
        $freezeSummary = app(PreschoolReportSnapshotService::class)->freezeReportPeriod($updated, $request->user());
        $updated = $updated->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
        $this->recordAudit(
            $request,
            'report_period.finalized',
            $updated,
            $previous,
            array_merge($service->snapshot($updated), ['freezeSummary' => $freezeSummary]),
        );

        return response()->json([
            'success' => true,
            'message' => 'Report period finalized successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function lock(Request $request, PreschoolReportPeriod $reportPeriod, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->snapshot($reportPeriod->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']));
        $updated = $service->lock($reportPeriod, $request->user());
        $freezeSummary = app(PreschoolReportSnapshotService::class)->freezeReportPeriod($updated, $request->user());
        $updated = $updated->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
        $this->recordAudit(
            $request,
            'report_period.locked',
            $updated,
            $previous,
            array_merge($service->snapshot($updated), ['freezeSummary' => $freezeSummary]),
        );

        return response()->json([
            'success' => true,
            'message' => 'Report period locked successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    public function archive(Request $request, PreschoolReportPeriod $reportPeriod, PreschoolReportPeriodService $service): JsonResponse
    {
        if ($response = $this->authorizeAdmin($request->user())) {
            return $response;
        }

        $previous = $service->snapshot($reportPeriod->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']));
        $updated = $service->archive($reportPeriod, $request->user());
        $freezeSummary = app(PreschoolReportSnapshotService::class)->freezeReportPeriod($updated, $request->user());
        $updated = $updated->fresh(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
        $this->recordAudit(
            $request,
            'report_period.archived',
            $updated,
            $previous,
            array_merge($service->snapshot($updated), ['freezeSummary' => $freezeSummary]),
        );

        return response()->json([
            'success' => true,
            'message' => 'Report period archived successfully.',
            'data' => [
                'period' => PreschoolReportPeriodResource::make($updated)->resolve($request),
            ],
        ], Response::HTTP_OK);
    }

    private function rules(?PreschoolReportPeriod $reportPeriod = null): array
    {
        return [
            'period_label' => ['sometimes', 'required', 'string', 'max:120'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:preschool_terms,id'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:from_date'],
            'status' => ['sometimes', 'nullable', 'in:draft,active,finalized,locked,archived'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    private function authorizeAny(?User $user): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool', 'teacher-preschool'], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
            'data' => null,
        ], Response::HTTP_FORBIDDEN);
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

    private function resolveStudent(Request $request): ?PreschoolStudent
    {
        $studentId = trim((string) $request->query('student_id', ''));
        if ($studentId === '') {
            return null;
        }

        return PreschoolStudent::query()->findOrFail($studentId);
    }

    private function resolveClass(Request $request): ?PreschoolClass
    {
        $classId = trim((string) $request->query('class_id', ''));
        if ($classId === '') {
            return null;
        }

        return PreschoolClass::query()->findOrFail($classId);
    }

    private function isLocked(PreschoolReportPeriod $period): bool
    {
        return in_array($period->status, ['finalized', 'locked', 'archived'], true);
    }

    private function canOverride(?User $user, Request $request, string $currentStatus = ''): bool
    {
        return $user
            && in_array($user->role_code, ['superadmin', 'adminpreschool'], true)
            && (bool) $request->input('override_locked_context', false)
            && trim((string) $request->input('override_reason', '')) !== ''
            && $currentStatus !== 'archived'
            && $request->input('status') !== 'archived';
    }

    private function lockedResponse(Request $request, PreschoolReportPeriod $period, string $message): JsonResponse
    {
        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => 'write.blocked',
            'entity_type' => 'report_period',
            'entity_id' => (string) $period->id,
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'report_period_id' => $period->id,
            'previous_state' => [
                'id' => $period->id,
                'period_label' => $period->period_label,
                'academic_year_id' => $period->academic_year_id,
                'term_id' => $period->term_id,
                'status' => $period->status,
            ],
            'new_state' => [
                'id' => $period->id,
                'period_label' => $period->period_label,
                'academic_year_id' => $period->academic_year_id,
                'term_id' => $period->term_id,
                'status' => $period->status,
            ],
            'lock_code' => 'report_period_locked',
            'lock_reason' => $message,
            'request_context' => app(PreschoolLifecycleAuditService::class)->requestContext($request, [
                'route' => $request->route()?->getName(),
            ]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'lockReason' => $message,
                'lockCode' => 'report_period_locked',
                'canOverride' => $this->canOverride($request->user(), $request, $period->status),
                'context' => [
                    'report_period_id' => $period->id,
                    'report_period_label' => $period->period_label,
                    'report_period_status' => $period->status,
                ],
            ],
        ], Response::HTTP_CONFLICT);
    }

    private function recordAudit(
        Request $request,
        string $actionType,
        PreschoolReportPeriod $period,
        ?array $previousState,
        ?array $newState,
    ): void {
        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role_code,
            'action_type' => $actionType,
            'entity_type' => 'report_period',
            'entity_id' => (string) $period->id,
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'report_period_id' => $period->id,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'override_reason' => trim((string) $request->input('override_reason', '')) ?: null,
            'lock_code' => $period->status,
            'lock_reason' => in_array($period->status, ['finalized', 'locked', 'archived'], true) ? 'Report period lifecycle transition.' : null,
            'request_context' => app(PreschoolLifecycleAuditService::class)->requestContext($request, [
                'route' => $request->route()?->getName(),
            ]),
        ]);
    }
}
