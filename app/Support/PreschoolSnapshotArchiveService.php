<?php

namespace App\Support;

use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportSnapshot;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Snapshot archive queries stay isolated from the live report services so the
 * institutional archive can browse immutable outputs without recalculating or
 * mutating the historical payloads.
 */
class PreschoolSnapshotArchiveService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderByDesc('generated_at')
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function analytics(array $filters = []): array
    {
        $query = $this->baseQuery($filters);

        $overview = [
            'totalSnapshots' => (clone $query)->count(),
            'studentReportSnapshots' => (clone $query)->where('snapshot_type', 'student_report')->count(),
            'classroomReportSnapshots' => (clone $query)->where('snapshot_type', 'classroom_report')->count(),
            'progressSummarySnapshots' => (clone $query)->where('snapshot_type', 'progress_summary')->count(),
            'finalizedSnapshots' => (clone $query)->where('lifecycle_state', 'finalized')->count(),
            'lockedSnapshots' => (clone $query)->where('lifecycle_state', 'locked')->count(),
            'archivedSnapshots' => (clone $query)->where('lifecycle_state', 'archived')->count(),
        ];

        $typeCounts = (clone $query)
            ->select('snapshot_type', \DB::raw('COUNT(*) as total'))
            ->groupBy('snapshot_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'snapshotType' => $row->snapshot_type,
                'total' => (int) $row->total,
            ])
            ->values();

        $stateCounts = (clone $query)
            ->select('lifecycle_state', \DB::raw('COUNT(*) as total'))
            ->groupBy('lifecycle_state')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'lifecycleState' => $row->lifecycle_state,
                'total' => (int) $row->total,
            ])
            ->values();

        $academicYearCounts = (clone $query)
            ->select('academic_year_id', \DB::raw('COUNT(*) as total'))
            ->groupBy('academic_year_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'academicYearId' => $row->academic_year_id,
                'total' => (int) $row->total,
            ])
            ->values();

        $termCounts = (clone $query)
            ->select('term_id', \DB::raw('COUNT(*) as total'))
            ->groupBy('term_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'termId' => $row->term_id,
                'total' => (int) $row->total,
            ])
            ->values();

        $reportPeriodCounts = (clone $query)
            ->select('report_period_id', \DB::raw('COUNT(*) as total'))
            ->groupBy('report_period_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'reportPeriodId' => $row->report_period_id,
                'total' => (int) $row->total,
            ])
            ->values();

        $generatedTrend = (clone $query)
            ->selectRaw('DATE(generated_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => $row->day,
                'total' => (int) $row->total,
            ])
            ->values();

        $classComparison = (clone $query)
            ->get()
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => (string) ($snapshot->class_id ?? ''))
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $averageScore = $snapshots
                    ->map(fn (PreschoolReportSnapshot $snapshot) => $this->snapshotSummaryValue($snapshot, ['summary', 'averageScore']))
                    ->filter(static fn ($value) => $value !== null)
                    ->map(static fn ($value) => (float) $value);

                return [
                    'classId' => $first?->class_id,
                    'className' => $this->snapshotClassName($first),
                    'snapshotCount' => $snapshots->count(),
                    'averageScore' => $averageScore->count() ? round((float) $averageScore->avg(), 2) : null,
                    'latestGeneratedAt' => $snapshots->max(fn (PreschoolReportSnapshot $snapshot) => $snapshot->generated_at?->toISOString()),
                ];
            })
            ->values()
            ->sortByDesc('snapshotCount')
            ->values();

        $generatedByCounts = (clone $query)
            ->with('generatedBy')
            ->get()
            ->groupBy('generated_by')
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $actor = $first?->generatedBy;

                return [
                    'generatedByUserId' => $first?->generated_by,
                    'generatedByName' => trim(($actor?->first_name ?? '').' '.($actor?->last_name ?? '')),
                    'generatedByRole' => $actor?->role_code,
                    'total' => $snapshots->count(),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values();

        $snapshotCollection = (clone $query)->get();

        $yearlyAttendanceTrend = $snapshotCollection
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => (string) ($snapshot->academic_year_id ?? ''))
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $academicYear = $this->academicYearSnapshot($first?->academicYear);

                return [
                    'academicYearId' => $first?->academic_year_id,
                    'academicYearLabel' => $academicYear['label'] ?? null,
                    'snapshotCount' => $snapshots->count(),
                    'attendanceCount' => $snapshots->sum(fn (PreschoolReportSnapshot $snapshot) => (int) ($this->attendanceSummary($snapshot->snapshot_payload ?? [])['attendanceCount'] ?? 0)),
                    'averageScore' => $this->averageScoreFromSnapshots($snapshots),
                ];
            })
            ->values()
            ->sortByDesc('snapshotCount')
            ->values();

        $termAttendanceTrend = $snapshotCollection
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => (string) ($snapshot->term_id ?? ''))
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $term = $this->termSnapshot($first?->term);

                return [
                    'termId' => $first?->term_id,
                    'termLabel' => $term['name'] ?? null,
                    'snapshotCount' => $snapshots->count(),
                    'attendanceCount' => $snapshots->sum(fn (PreschoolReportSnapshot $snapshot) => (int) ($this->attendanceSummary($snapshot->snapshot_payload ?? [])['attendanceCount'] ?? 0)),
                    'averageScore' => $this->averageScoreFromSnapshots($snapshots),
                ];
            })
            ->values()
            ->sortByDesc('snapshotCount')
            ->values();

        $reportPeriodCompletionOverview = $snapshotCollection
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => (string) ($snapshot->report_period_id ?? ''))
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $reportPeriod = $this->reportPeriodSnapshot($first?->reportPeriod);
                $stateCounts = $snapshots->groupBy('lifecycle_state')->map->count();

                return [
                    'reportPeriodId' => $first?->report_period_id,
                    'reportPeriodLabel' => $reportPeriod['label'] ?? null,
                    'snapshotCount' => $snapshots->count(),
                    'finalizedCount' => (int) ($stateCounts['finalized'] ?? 0),
                    'lockedCount' => (int) ($stateCounts['locked'] ?? 0),
                    'archivedCount' => (int) ($stateCounts['archived'] ?? 0),
                    'attendanceCount' => $snapshots->sum(fn (PreschoolReportSnapshot $snapshot) => (int) ($this->attendanceSummary($snapshot->snapshot_payload ?? [])['attendanceCount'] ?? 0)),
                ];
            })
            ->values()
            ->sortByDesc('snapshotCount')
            ->values();

        $assessmentCategoryTrend = $snapshotCollection
            ->flatMap(function (PreschoolReportSnapshot $snapshot): Collection {
                $categories = Arr::get($snapshot->snapshot_payload ?? [], 'categorySummaries', Arr::get($snapshot->snapshot_payload ?? [], 'category_summaries', []));
                $categories = is_array($categories) ? $categories : [];

                return collect($categories)->map(function (array $category) use ($snapshot): array {
                    $categorySnapshot = $category['category'] ?? [];

                    return [
                        'categoryKey' => (string) ($categorySnapshot['code'] ?? $categorySnapshot['name'] ?? $categorySnapshot['id'] ?? 'unknown'),
                        'categoryName' => $categorySnapshot['name'] ?? $categorySnapshot['code'] ?? 'Unknown',
                        'snapshotVersion' => (int) $snapshot->snapshot_version,
                        'count' => (int) ($category['count'] ?? 0),
                        'averageScore' => $category['averageScore'] ?? null,
                        'latestAssessmentDate' => $category['latestAssessmentDate'] ?? null,
                    ];
                });
            })
            ->groupBy('categoryKey')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'categoryKey' => $first['categoryKey'] ?? null,
                    'categoryName' => $first['categoryName'] ?? null,
                    'snapshotCount' => $rows->count(),
                    'assessmentCount' => $rows->sum('count'),
                    'averageScore' => $this->averageFromRows($rows->pluck('averageScore')),
                    'latestAssessmentDate' => $rows->max('latestAssessmentDate'),
                ];
            })
            ->values()
            ->sortByDesc('assessmentCount')
            ->values();

        $studentProgression = $snapshotCollection
            ->where('snapshot_type', 'student_report')
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => (string) ($snapshot->student_id ?? ''))
            ->map(function (Collection $snapshots): array {
                $first = $snapshots->first();
                $student = $this->studentSnapshot($first?->student);

                return [
                    'studentId' => $first?->student_id,
                    'studentName' => $student['fullName'] ?? null,
                    'studentCode' => $student['studentCode'] ?? null,
                    'snapshotCount' => $snapshots->count(),
                    'attendanceCount' => $snapshots->sum(fn (PreschoolReportSnapshot $snapshot) => (int) ($this->attendanceSummary($snapshot->snapshot_payload ?? [])['attendanceCount'] ?? 0)),
                    'averageScore' => $this->averageScoreFromSnapshots($snapshots),
                    'latestGeneratedAt' => $snapshots->max(fn (PreschoolReportSnapshot $snapshot) => $snapshot->generated_at?->toISOString()),
                ];
            })
            ->values()
            ->sortByDesc('snapshotCount')
            ->values();

        return [
            'overview' => $overview,
            'typeCounts' => $typeCounts,
            'stateCounts' => $stateCounts,
            'academicYearCounts' => $academicYearCounts,
            'termCounts' => $termCounts,
            'reportPeriodCounts' => $reportPeriodCounts,
            'classComparison' => $classComparison,
            'generatedByCounts' => $generatedByCounts,
            'generatedTrend' => $generatedTrend,
            'yearlyAttendanceTrend' => $yearlyAttendanceTrend,
            'termAttendanceTrend' => $termAttendanceTrend,
            'reportPeriodCompletionOverview' => $reportPeriodCompletionOverview,
            'assessmentCategoryTrend' => $assessmentCategoryTrend,
            'studentProgression' => $studentProgression,
        ];
    }

    public function detail(PreschoolReportSnapshot $snapshot): array
    {
        $snapshot->loadMissing(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy']);

        $previousSnapshot = $this->previousSnapshot($snapshot);

        return [
            'snapshot' => $this->transformSnapshot($snapshot, true),
            'previousSnapshot' => $previousSnapshot ? $this->transformSnapshot($previousSnapshot, true) : null,
            'comparison' => $previousSnapshot ? $this->comparison($previousSnapshot, $snapshot) : [],
            'auditTrail' => $this->auditTrail($snapshot),
        ];
    }

    public function preview(PreschoolReportSnapshot $snapshot): array
    {
        $snapshot->loadMissing(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy']);

        return $this->transformSnapshot($snapshot, false);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRows(array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->orderByDesc('generated_at')
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PreschoolReportSnapshot $snapshot): array => $this->transformSnapshot($snapshot, true));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectSnapshots(array $filters = []): Collection
    {
        return $this->baseQuery($filters)
            ->orderByDesc('generated_at')
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $query = PreschoolReportSnapshot::query()
            ->with(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy']);

        $query->whereIn('lifecycle_state', $this->normalizeStateFilter($filters['lifecycle_state'] ?? null));

        foreach ([
            'academic_year_id' => 'academic_year_id',
            'term_id' => 'term_id',
            'report_period_id' => 'report_period_id',
            'class_id' => 'class_id',
            'student_id' => 'student_id',
            'snapshot_type' => 'snapshot_type',
            'generated_by' => 'generated_by',
        ] as $filterKey => $column) {
            $value = $filters[$filterKey] ?? null;
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        if (($filters['generated_from'] ?? '') !== '') {
            $query->whereDate('generated_at', '>=', $filters['generated_from']);
        }

        if (($filters['generated_to'] ?? '') !== '') {
            $query->whereDate('generated_at', '<=', $filters['generated_to']);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereHas('student', function (Builder $studentQuery) use ($search): void {
                        $studentQuery->where('student_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('preschoolClass', function (Builder $classQuery) use ($search): void {
                        $classQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('reportPeriod', function (Builder $reportQuery) use ($search): void {
                        $reportQuery->where('period_label', 'like', "%{$search}%");
                    })
                    ->orWhere('snapshot_type', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function normalizeStateFilter(mixed $value): array
    {
        $allowed = ['finalized', 'locked', 'archived'];
        $values = collect(is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value))))
            ->map(fn ($item) => strtolower((string) $item))
            ->filter(fn ($item) => in_array($item, $allowed, true))
            ->values()
            ->all();

        return $values ?: $allowed;
    }

    public function transformSnapshot(PreschoolReportSnapshot $snapshot, bool $includePayload = false): array
    {
        $snapshot->loadMissing(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy']);

        $payload = $snapshot->snapshot_payload ?? [];

        return [
            'id' => $snapshot->id,
            'snapshotType' => $snapshot->snapshot_type,
            'lifecycleState' => $snapshot->lifecycle_state,
            'snapshotVersion' => (int) $snapshot->snapshot_version,
            'generatedAt' => $snapshot->generated_at?->toISOString(),
            'lockedAt' => $snapshot->locked_at?->toISOString(),
            'generatedByUserId' => $snapshot->generated_by,
            'generatedBy' => $this->userSnapshot($snapshot->generatedBy),
            'academicYearId' => $snapshot->academic_year_id,
            'termId' => $snapshot->term_id,
            'reportPeriodId' => $snapshot->report_period_id,
            'studentId' => $snapshot->student_id,
            'classId' => $snapshot->class_id,
            'sourceStatus' => strtolower(trim((string) data_get($payload, 'source', 'snapshot'))) ?: 'snapshot',
            'student' => $this->studentSnapshot($snapshot->student),
            'class' => $this->classSnapshot($snapshot->preschoolClass),
            'academicYear' => $this->academicYearSnapshot($snapshot->academicYear),
            'term' => $this->termSnapshot($snapshot->term),
            'reportPeriod' => $this->reportPeriodSnapshot($snapshot->reportPeriod),
            'reportSummary' => $this->reportSummary($payload),
            'attendanceSummary' => $this->attendanceSummary($payload),
            'assessmentSummary' => $this->assessmentSummary($payload),
            'progressSummary' => $this->progressSummary($payload),
            'summary' => $this->snapshotSummary($payload),
            'contextLabel' => $this->contextLabel($snapshot),
            'raw' => $includePayload ? $payload : null,
        ];
    }

    private function comparison(PreschoolReportSnapshot $previous, PreschoolReportSnapshot $current): array
    {
        $previousMetrics = $this->snapshotMetrics($previous->snapshot_payload ?? []);
        $currentMetrics = $this->snapshotMetrics($current->snapshot_payload ?? []);

        $fields = ['finalizedAssessments', 'averageScore', 'observationCount', 'attendanceCount', 'presentCount', 'lateCount', 'absentCount', 'excusedCount', 'studentCount'];
        $changes = [];

        foreach ($fields as $field) {
            $before = $previousMetrics[$field] ?? null;
            $after = $currentMetrics[$field] ?? null;

            if ($before !== $after) {
                $changes[] = [
                    'field' => $field,
                    'previous' => $before,
                    'current' => $after,
                ];
            }
        }

        return [
            'previousSnapshotId' => $previous->id,
            'previousSnapshotVersion' => (int) $previous->snapshot_version,
            'currentSnapshotId' => $current->id,
            'currentSnapshotVersion' => (int) $current->snapshot_version,
            'changes' => $changes,
        ];
    }

    private function previousSnapshot(PreschoolReportSnapshot $snapshot): ?PreschoolReportSnapshot
    {
        return PreschoolReportSnapshot::query()
            ->where('snapshot_type', $snapshot->snapshot_type)
            ->where('lifecycle_state', $snapshot->lifecycle_state)
            ->when($snapshot->student_id !== null, fn (Builder $query) => $query->where('student_id', $snapshot->student_id))
            ->when($snapshot->class_id !== null, fn (Builder $query) => $query->where('class_id', $snapshot->class_id))
            ->when($snapshot->academic_year_id !== null, fn (Builder $query) => $query->where('academic_year_id', $snapshot->academic_year_id))
            ->when($snapshot->term_id !== null, fn (Builder $query) => $query->where('term_id', $snapshot->term_id))
            ->when($snapshot->report_period_id !== null, fn (Builder $query) => $query->where('report_period_id', $snapshot->report_period_id))
            ->where('snapshot_version', '<', $snapshot->snapshot_version)
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->first();
    }

    private function auditTrail(PreschoolReportSnapshot $snapshot): array
    {
        return PreschoolLifecycleAuditLog::query()
            ->with(['actor', 'reportPeriod'])
            ->where(function (Builder $query) use ($snapshot): void {
                $query->where('report_period_id', $snapshot->report_period_id)
                    ->orWhere(function (Builder $nestedQuery) use ($snapshot): void {
                        $nestedQuery->where('entity_type', 'report_snapshot')
                            ->where('entity_id', (string) $snapshot->id);
                    });
            })
            ->whereIn('action_type', [
                'report_snapshot.generated',
                'report_snapshot.archive_viewed',
                'report_snapshot.analytics_viewed',
                'report_snapshot.exported',
                'report_period.finalized',
                'report_period.locked',
                'report_period.archived',
                'write.blocked',
                'override.approved',
                'override.attempt',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (PreschoolLifecycleAuditLog $log): array => [
                'id' => $log->id,
                'actionType' => $log->action_type,
                'entityType' => $log->entity_type,
                'entityId' => $log->entity_id,
                'actorUserId' => $log->actor_user_id,
                'actorRole' => $log->actor_role,
                'reportPeriodId' => $log->report_period_id,
                'overrideReason' => $log->override_reason,
                'lockReason' => $log->lock_reason,
                'lockCode' => $log->lock_code,
                'createdAt' => $log->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function reportSummary(array $payload): array
    {
        $summary = Arr::get($payload, 'summary', Arr::get($payload, 'report.summary', []));
        $summary = is_array($summary) ? $summary : [];

        return [
            'finalizedAssessments' => $this->numberValue($summary, ['finalizedAssessments', 'finalized_assessments']),
            'averageScore' => $this->numberValue($summary, ['averageScore', 'average_score']),
            'latestAssessmentDate' => $this->textValue($summary, ['latestAssessmentDate', 'latest_assessment_date']),
            'observationCount' => $this->numberValue($summary, ['observationCount', 'observation_count']),
            'studentCount' => $this->numberValue($summary, ['studentCount', 'student_count']),
        ];
    }

    private function attendanceSummary(array $payload): array
    {
        $summary = Arr::get($payload, 'attendanceSummary', Arr::get($payload, 'attendance_summary', []));
        $summary = is_array($summary) ? $summary : [];

        return [
            'attendanceCount' => $this->numberValue($summary, ['attendanceCount', 'attendance_count']),
            'presentCount' => $this->numberValue($summary, ['presentCount', 'present_count']),
            'lateCount' => $this->numberValue($summary, ['lateCount', 'late_count']),
            'absentCount' => $this->numberValue($summary, ['absentCount', 'absent_count']),
            'excusedCount' => $this->numberValue($summary, ['excusedCount', 'excused_count']),
            'latestAttendanceDate' => $this->textValue($summary, ['latestAttendanceDate', 'latest_attendance_date']),
        ];
    }

    private function assessmentSummary(array $payload): array
    {
        $categories = Arr::get($payload, 'categorySummaries', Arr::get($payload, 'category_summaries', []));
        $categories = is_array($categories) ? $categories : [];
        $assessments = Arr::get($payload, 'assessments', Arr::get($payload, 'report.assessments', []));
        $assessments = is_array($assessments) ? $assessments : [];

        return [
            'categoryCount' => is_array($categories) ? count($categories) : 0,
            'assessmentCount' => is_array($assessments) ? count($assessments) : 0,
            'categories' => $categories,
        ];
    }

    private function progressSummary(array $payload): array
    {
        $summary = Arr::get($payload, 'summary', []);
        $summary = is_array($summary) ? $summary : [];
        $studentSummaries = Arr::get($payload, 'studentSummaries', []);
        $studentSummaries = is_array($studentSummaries) ? $studentSummaries : [];

        return [
            'studentCount' => $this->numberValue($summary, ['studentCount', 'student_count']),
            'finalizedAssessments' => $this->numberValue($summary, ['finalizedAssessments', 'finalized_assessments']),
            'averageScore' => $this->numberValue($summary, ['averageScore', 'average_score']),
            'observationCount' => $this->numberValue($summary, ['observationCount', 'observation_count']),
            'studentSummaries' => $studentSummaries,
        ];
    }

    private function snapshotSummary(array $payload): array
    {
        return [
            'finalizedAssessments' => $this->reportSummary($payload)['finalizedAssessments'],
            'averageScore' => $this->reportSummary($payload)['averageScore'],
            'attendanceCount' => $this->attendanceSummary($payload)['attendanceCount'],
            'observationCount' => $this->reportSummary($payload)['observationCount'],
            'studentCount' => $this->reportSummary($payload)['studentCount'],
        ];
    }

    private function snapshotMetrics(array $payload): array
    {
        return array_merge(
            $this->reportSummary($payload),
            $this->attendanceSummary($payload),
            $this->progressSummary($payload),
        );
    }

    private function snapshotSummaryValue(PreschoolReportSnapshot $snapshot, array $path): mixed
    {
        return data_get($snapshot->snapshot_payload ?? [], implode('.', $path));
    }

    private function snapshotClassName(?PreschoolReportSnapshot $snapshot): ?string
    {
        if (! $snapshot) {
            return null;
        }

        return $snapshot->preschoolClass?->name ?: ($snapshot->class_id ? 'Class #'.$snapshot->class_id : null);
    }

    private function studentSnapshot(mixed $student): ?array
    {
        if (! $student) {
            return null;
        }

        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'fullName' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'status' => $student->status,
        ];
    }

    private function classSnapshot(mixed $class): ?array
    {
        if (! $class) {
            return null;
        }

        return [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'teacherUserId' => $class->teacher_user_id,
            'teacherDisplayName' => $class->teacher_display_name,
            'level' => $class->level,
            'status' => $class->status,
        ];
    }

    private function academicYearSnapshot(mixed $academicYear): ?array
    {
        if (! $academicYear) {
            return null;
        }

        return [
            'id' => $academicYear->id,
            'code' => $academicYear->code,
            'label' => $academicYear->label,
            'status' => $academicYear->status,
        ];
    }

    private function termSnapshot(mixed $term): ?array
    {
        if (! $term) {
            return null;
        }

        return [
            'id' => $term->id,
            'code' => $term->code,
            'name' => $term->name,
            'status' => $term->status,
        ];
    }

    private function reportPeriodSnapshot(mixed $period): ?array
    {
        if (! $period) {
            return null;
        }

        return [
            'id' => $period->id,
            'label' => $period->period_label,
            'status' => $period->status,
            'fromDate' => $period->from_date?->toDateString(),
            'toDate' => $period->to_date?->toDateString(),
        ];
    }

    private function userSnapshot(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'displayName' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'username' => $user->username,
            'email' => $user->email,
            'roleCode' => $user->role_code,
        ];
    }

    private function contextLabel(PreschoolReportSnapshot $snapshot): string
    {
        $parts = [];
        if ($snapshot->student) {
            $parts[] = trim(($snapshot->student->first_name ?? '').' '.($snapshot->student->last_name ?? ''));
        }
        if ($snapshot->preschoolClass) {
            $parts[] = $snapshot->preschoolClass->name;
        }
        if ($snapshot->reportPeriod) {
            $parts[] = $snapshot->reportPeriod->period_label;
        }

        return trim(implode(' | ', array_filter($parts)));
    }

    private function numberValue(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return is_numeric($source[$key]) ? 0 + $source[$key] : null;
            }
        }

        return null;
    }

    private function textValue(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && trim((string) $source[$key]) !== '') {
                return trim((string) $source[$key]);
            }
        }

        return null;
    }

    private function averageScoreFromSnapshots(Collection $snapshots): ?float
    {
        $values = $snapshots
            ->map(fn (PreschoolReportSnapshot $snapshot) => $this->reportSummary($snapshot->snapshot_payload ?? [])['averageScore'] ?? null)
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (float) $value);

        return $values->count() ? round((float) $values->avg(), 2) : null;
    }

    private function averageFromRows(Collection $values): ?float
    {
        $normalized = $values
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->map(static fn ($value) => (float) $value);

        return $normalized->count() ? round((float) $normalized->avg(), 2) : null;
    }
}
