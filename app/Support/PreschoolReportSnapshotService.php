<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolReportSnapshot;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Preschool report snapshots are the immutable history layer for finalized and
 * locked report output. They preserve the exact generated payload so later
 * operational changes cannot drift the historical report result.
 */
class PreschoolReportSnapshotService
{
    public function latestForContext(string $snapshotType, array $context = []): ?PreschoolReportSnapshot
    {
        return $this->queryForContext($snapshotType, $context)
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $payload
     */
    public function storeSnapshot(string $snapshotType, array $context, array $payload, ?User $actor = null, bool $forceNewVersion = false): PreschoolReportSnapshot
    {
        $existing = $this->latestForContext($snapshotType, $context);
        if ($existing && ! $forceNewVersion) {
            return $existing;
        }

        $snapshotVersion = ((int) ($existing?->snapshot_version ?? 0)) + 1;
        $generatedAt = now();
        $payload = $this->decoratePayload($payload, $snapshotType, $snapshotVersion, $generatedAt, $actor, $context);

        return PreschoolReportSnapshot::query()->create([
            'snapshot_type' => $snapshotType,
            'student_id' => $this->nullableInt($context['student_id'] ?? null),
            'class_id' => $this->nullableInt($context['class_id'] ?? null),
            'academic_year_id' => $this->nullableInt($context['academic_year_id'] ?? null),
            'term_id' => $this->nullableInt($context['term_id'] ?? null),
            'report_period_id' => $this->nullableInt($context['report_period_id'] ?? null),
            'generated_by' => $actor?->id,
            'lifecycle_state' => strtolower(trim((string) ($context['lifecycle_state'] ?? 'finalized'))) ?: 'finalized',
            'snapshot_version' => $snapshotVersion,
            'snapshot_payload' => $payload,
            'generated_at' => $generatedAt,
            'locked_at' => $this->normalizeDateTime($context['locked_at'] ?? null),
        ]);
    }

    /**
     * Freeze the active report period by materializing immutable snapshots for
     * student reports, classroom reports, and student progress summaries.
     *
     * @return array<string, int>
     */
    public function freezeReportPeriod(PreschoolReportPeriod $period, ?User $actor = null): array
    {
        $periodLabel = trim((string) $period->period_label);
        $context = $this->reportPeriodContext($period);
        $actor ??= request()->user() ?? $this->systemActor();

        $assessmentQuery = PreschoolStudentAssessment::query()
            ->where('period_label', $periodLabel)
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->select(['student_id', 'class_id'])
            ->distinct();

        $studentIds = (clone $assessmentQuery)->pluck('student_id')->filter()->map(static fn ($id) => (int) $id)->unique()->values();
        $classIds = (clone $assessmentQuery)->pluck('class_id')->filter()->map(static fn ($id) => (int) $id)->unique()->values();

        $studentSnapshots = 0;
        $classroomSnapshots = 0;
        $progressSnapshots = 0;

        $reportService = app(PreschoolReportService::class);
        $classroomService = app(PreschoolClassroomReportService::class);
        $progressService = app(PreschoolProgressSummaryService::class);

        foreach ($studentIds as $studentId) {
            $student = PreschoolStudent::query()->find($studentId);
            if (! $student) {
                continue;
            }

            $bundle = $reportService->bundle($actor ?? $this->systemActor(), $student, $periodLabel);
            if (($bundle['report'] ?? null) !== null) {
                $studentSnapshots++;
            }

            $summary = $progressService->forStudent($actor ?? $this->systemActor(), $student);
            $this->storeSnapshot('progress_summary', [
                    ...$context,
                    'student_id' => $student->id,
                ], $summary, $actor, true);
            $progressSnapshots++;
        }

        foreach ($classIds as $classId) {
            $class = PreschoolClass::query()->find($classId);
            if (! $class) {
                continue;
            }

            $bundle = $classroomService->bundle($actor ?? $this->systemActor(), $class, $periodLabel);
            if (($bundle['report'] ?? null) !== null) {
                $classroomSnapshots++;
            }
        }

        $period->forceFill([
            'summary_snapshot' => array_merge($period->summary_snapshot ?? [], [
                'generatedAt' => now()->toISOString(),
                'generatedByUserId' => $actor?->id,
                'studentReportSnapshots' => $studentSnapshots,
                'classroomReportSnapshots' => $classroomSnapshots,
                'progressSummarySnapshots' => $progressSnapshots,
                'snapshotVersion' => ($period->summary_snapshot['snapshotVersion'] ?? 0) + 1,
                'snapshotState' => $period->status,
            ]),
            'report_snapshot' => array_merge($period->report_snapshot ?? [], [
                'generatedAt' => now()->toISOString(),
                'generatedByUserId' => $actor?->id,
                'snapshotState' => $period->status,
                'studentReportSnapshots' => $studentSnapshots,
                'classroomReportSnapshots' => $classroomSnapshots,
                'progressSummarySnapshots' => $progressSnapshots,
            ]),
        ])->save();

        app(PreschoolLifecycleAuditService::class)->recordSafely([
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role_code,
            'action_type' => 'report_snapshot.generated',
            'entity_type' => 'report_period',
            'entity_id' => (string) $period->id,
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'report_period_id' => $period->id,
            'previous_state' => null,
            'new_state' => [
                'report_period_id' => $period->id,
                'report_period_label' => $period->period_label,
                'report_period_status' => $period->status,
                'studentReportSnapshots' => $studentSnapshots,
                'classroomReportSnapshots' => $classroomSnapshots,
                'progressSummarySnapshots' => $progressSnapshots,
            ],
            'request_context' => app(PreschoolLifecycleAuditService::class)->requestContext(null, [
                'operation' => 'freeze_report_period',
                'actor_user_id' => $actor?->id,
            ]),
            'lock_code' => $period->status,
            'lock_reason' => 'Immutable report snapshots generated for a lifecycle transition.',
        ]);

        return [
            'studentReportSnapshots' => $studentSnapshots,
            'classroomReportSnapshots' => $classroomSnapshots,
            'progressSummarySnapshots' => $progressSnapshots,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function snapshotPayload(PreschoolReportSnapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'snapshotType' => $snapshot->snapshot_type,
            'studentId' => $snapshot->student_id,
            'classId' => $snapshot->class_id,
            'academicYearId' => $snapshot->academic_year_id,
            'termId' => $snapshot->term_id,
            'reportPeriodId' => $snapshot->report_period_id,
            'generatedByUserId' => $snapshot->generated_by,
            'lifecycleState' => $snapshot->lifecycle_state,
            'snapshotVersion' => $snapshot->snapshot_version,
            'generatedAt' => $snapshot->generated_at?->toISOString(),
            'lockedAt' => $snapshot->locked_at?->toISOString(),
            'payload' => $snapshot->snapshot_payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function latestSnapshotMeta(string $snapshotType, array $context = []): ?array
    {
        $snapshot = $this->latestForContext($snapshotType, $context);

        return $snapshot ? $this->snapshotPayload($snapshot) : null;
    }

    private function queryForContext(string $snapshotType, array $context)
    {
        return PreschoolReportSnapshot::query()
            ->where('snapshot_type', $snapshotType)
            ->when(array_key_exists('student_id', $context), fn ($query) => $query->where('student_id', $this->nullableInt($context['student_id'] ?? null)))
            ->when(array_key_exists('class_id', $context), fn ($query) => $query->where('class_id', $this->nullableInt($context['class_id'] ?? null)))
            ->when(array_key_exists('academic_year_id', $context), fn ($query) => $query->where('academic_year_id', $this->nullableInt($context['academic_year_id'] ?? null)))
            ->when(array_key_exists('term_id', $context), fn ($query) => $query->where('term_id', $this->nullableInt($context['term_id'] ?? null)))
            ->when(array_key_exists('report_period_id', $context), fn ($query) => $query->where('report_period_id', $this->nullableInt($context['report_period_id'] ?? null)));
    }

    private function decoratePayload(array $payload, string $snapshotType, int $snapshotVersion, Carbon $generatedAt, ?User $actor, array $context): array
    {
        return array_merge($payload, [
            'snapshot' => [
                'snapshotType' => $snapshotType,
                'snapshotVersion' => $snapshotVersion,
                'generatedAt' => $generatedAt->toISOString(),
                'generatedByUserId' => $actor?->id,
                'generatedByRole' => $actor?->role_code,
                'lifecycleState' => strtolower(trim((string) ($context['lifecycle_state'] ?? 'finalized'))) ?: 'finalized',
                'reportPeriodId' => $context['report_period_id'] ?? null,
                'academicYearId' => $context['academic_year_id'] ?? null,
                'termId' => $context['term_id'] ?? null,
            ],
            'source' => 'snapshot',
            'frozen' => true,
            'generatedAt' => $generatedAt->toISOString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportPeriodContext(PreschoolReportPeriod $period): array
    {
        return [
            'report_period_id' => $period->id,
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'lifecycle_state' => $period->status,
            'locked_at' => $period->locked_at?->toISOString(),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function systemActor(): User
    {
        return User::query()->where('role_code', 'superadmin')->orderBy('id')->firstOrFail();
    }
}
