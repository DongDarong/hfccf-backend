<?php

namespace App\Support;

use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportExportRecord;
use App\Models\PreschoolReportSnapshot;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Export governance keeps immutable export metadata separate from the live
 * report builders so institutional downloads can be reviewed, regenerated,
 * and compared without mutating snapshot payloads.
 */
class PreschoolExportGovernanceService
{
    public function __construct(
        private readonly PreschoolSnapshotArchiveService $archiveService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('exported_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function overview(array $filters = []): array
    {
        $query = $this->query($filters);
        $snapshotQuery = $this->archiveService->collectSnapshots($filters);

        return [
            'totalExports' => (clone $query)->count(),
            'snapshotExports' => (clone $query)->where('export_source', 'snapshot')->count(),
            'liveExports' => (clone $query)->where('export_source', 'live')->count(),
            'csvExports' => (clone $query)->where('export_format', 'csv')->count(),
            'studentReportExports' => (clone $query)->where('export_type', 'student_report')->count(),
            'classroomReportExports' => (clone $query)->where('export_type', 'classroom_report')->count(),
            'progressSummaryExports' => (clone $query)->where('export_type', 'progress_summary')->count(),
            'snapshotArchiveExports' => (clone $query)->where('export_type', 'snapshot_archive')->count(),
            'institutionalSummaryExports' => (clone $query)->where('export_type', 'institutional_summary')->count(),
            'actorCounts' => (clone $query)
                ->whereNotNull('actor_user_id')
                ->select('actor_user_id', \DB::raw('COUNT(*) as total'))
                ->groupBy('actor_user_id')
                ->orderByDesc('total')
                ->get()
                ->map(function ($row): array {
                    $actor = User::query()->find($row->actor_user_id);

                    return [
                        'actorUserId' => $row->actor_user_id,
                        'actorName' => trim(($actor?->first_name ?? '').' '.($actor?->last_name ?? '')),
                        'actorRole' => $actor?->role_code,
                        'total' => (int) $row->total,
                    ];
                })
                ->values(),
            'sourceCounts' => [
                'snapshot' => (clone $query)->where('export_source', 'snapshot')->count(),
                'live' => (clone $query)->where('export_source', 'live')->count(),
            ],
            'exportTrend' => (clone $query)
                ->selectRaw('DATE(exported_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row): array => [
                    'day' => $row->day,
                    'total' => (int) $row->total,
                ])
                ->values(),
            'recentSnapshotCount' => $snapshotQuery->count(),
        ];
    }

    public function detail(PreschoolReportExportRecord $record): array
    {
        $record->loadMissing(['actor', 'academicYear', 'term', 'reportPeriod']);

        $snapshots = $this->snapshotsForRecord($record);

        return [
            'record' => $this->transformRecord($record),
            'includedSnapshots' => $snapshots->map(fn (PreschoolReportSnapshot $snapshot): array => $this->archiveService->preview($snapshot))->values()->all(),
            'includedSnapshotIds' => $record->snapshot_ids ?? [],
            'includedSnapshotCount' => $snapshots->count(),
            'auditTrail' => $this->timeline([
                'actor_user_id' => $record->actor_user_id,
                'academic_year_id' => $record->academic_year_id,
                'term_id' => $record->term_id,
                'report_period_id' => $record->report_period_id,
                'export_type' => $record->export_type,
                'export_format' => $record->export_format,
                'source' => $record->export_source,
            ], 8),
        ];
    }

    public function previewRecord(PreschoolReportExportRecord $record): array
    {
        $record->loadMissing(['actor', 'academicYear', 'term', 'reportPeriod']);

        return $this->transformRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordExport(array $data): PreschoolReportExportRecord
    {
        return PreschoolReportExportRecord::query()->create([
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'actor_role' => $data['actor_role'] ?? null,
            'export_type' => $data['export_type'],
            'export_format' => $data['export_format'],
            'export_source' => $data['export_source'],
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
            'report_period_id' => $data['report_period_id'] ?? null,
            'filters' => $data['filters'] ?? null,
            'snapshot_ids' => $data['snapshot_ids'] ?? null,
            'record_count' => $data['record_count'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'checksum' => $data['checksum'] ?? null,
            'export_reason' => $data['export_reason'] ?? null,
            'request_context' => $data['request_context'] ?? null,
            'exported_at' => $data['exported_at'] ?? now(),
        ]);
    }

    public function rowsForRecord(PreschoolReportExportRecord $record): Collection
    {
        return $this->snapshotsForRecord($record)
            ->map(fn (PreschoolReportSnapshot $snapshot): array => $this->archiveService->transformSnapshot($snapshot, true));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function options(array $filters = []): array
    {
        return [
            'comparisonModes' => [
                ['value' => 'term_vs_term'],
                ['value' => 'academic_year_vs_academic_year'],
                ['value' => 'report_period_vs_report_period'],
                ['value' => 'class_vs_class'],
                ['value' => 'student_progression'],
                ['value' => 'snapshot_version_vs_version'],
            ],
            'metricGroups' => [
                ['value' => 'overview'],
                ['value' => 'attendance'],
                ['value' => 'assessment'],
                ['value' => 'progress'],
            ],
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function compare(array $payload): array
    {
        $mode = strtolower((string) ($payload['comparison_mode'] ?? 'report_period_vs_report_period'));
        $leftSnapshots = $this->resolveComparisonSnapshots($mode, Arr::get($payload, 'left_context', []));
        $rightSnapshots = $this->resolveComparisonSnapshots($mode, Arr::get($payload, 'right_context', []));

        $leftSummary = $this->comparisonSummary($leftSnapshots);
        $rightSummary = $this->comparisonSummary($rightSnapshots);

        $metricKeys = ['snapshotCount', 'attendanceCount', 'absenceRate', 'finalizedAssessments', 'averageScore', 'observationCount', 'studentCount'];

        $metrics = collect($metricKeys)->map(function (string $metric) use ($leftSummary, $rightSummary): array {
            $left = $leftSummary[$metric] ?? null;
            $right = $rightSummary[$metric] ?? null;

            return [
                'metric' => $metric,
                'left' => $left,
                'right' => $right,
                'delta' => $this->metricDelta($left, $right),
            ];
        })->values()->all();

        return [
            'comparisonMode' => $mode,
            'left' => [
                'context' => $this->comparisonContextLabel($mode, Arr::get($payload, 'left_context', [])),
                'summary' => $leftSummary,
                'snapshots' => $leftSnapshots->map(fn (PreschoolReportSnapshot $snapshot): array => $this->archiveService->preview($snapshot))->values()->all(),
            ],
            'right' => [
                'context' => $this->comparisonContextLabel($mode, Arr::get($payload, 'right_context', [])),
                'summary' => $rightSummary,
                'snapshots' => $rightSnapshots->map(fn (PreschoolReportSnapshot $snapshot): array => $this->archiveService->preview($snapshot))->values()->all(),
            ],
            'metrics' => $metrics,
            'trend' => [
                'left' => $this->snapshotTrend($leftSnapshots),
                'right' => $this->snapshotTrend($rightSnapshots),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function timeline(array $filters = [], int $limit = 50): array
    {
        $auditQuery = PreschoolLifecycleAuditLog::query()->with(['actor', 'reportPeriod']);
        $exportQuery = PreschoolReportExportRecord::query()->with(['actor', 'reportPeriod']);

        foreach ([
            'academic_year_id',
            'term_id',
            'report_period_id',
            'actor_user_id',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $auditQuery->where($field, $filters[$field]);
                $exportQuery->where($field, $filters[$field]);
            }
        }

        if (($filters['export_type'] ?? '') !== '') {
            $exportQuery->where('export_type', $filters['export_type']);
        }

        if (($filters['export_format'] ?? '') !== '') {
            $exportQuery->where('export_format', $filters['export_format']);
        }

        if (($filters['source'] ?? '') !== '') {
            $exportQuery->where('export_source', $filters['source']);
        }

        $events = $auditQuery
            ->whereDate('created_at', '>=', now()->subDays(90)->toDateString())
            ->get()
            ->map(function (PreschoolLifecycleAuditLog $log): array {
                $actor = $log->actor;

                return [
                    'id' => 'audit-'.$log->id,
                    'eventType' => $log->action_type,
                    'source' => 'audit',
                    'title' => $log->action_type,
                    'description' => trim(implode(' | ', array_filter([
                        $log->entity_type.' #'.$log->entity_id,
                        $log->lock_reason,
                        $log->override_reason,
                    ]))),
                    'actor' => $this->userSnapshot($actor),
                    'context' => [
                        'academicYearId' => $log->academic_year_id,
                        'termId' => $log->term_id,
                        'reportPeriodId' => $log->report_period_id,
                    ],
                    'recordedAt' => $log->created_at?->toISOString(),
                ];
            });

        $exports = $exportQuery
            ->whereDate('exported_at', '>=', now()->subDays(90)->toDateString())
            ->get()
            ->map(function (PreschoolReportExportRecord $record): array {
                $actor = $record->actor;

                return [
                    'id' => 'export-'.$record->id,
                    'eventType' => 'export.created',
                    'source' => 'export',
                    'title' => $record->export_type.' export',
                    'description' => trim(implode(' | ', array_filter([
                        $record->export_format,
                        $record->export_source,
                        $record->file_name,
                        $record->record_count ? $record->record_count.' records' : null,
                    ]))),
                    'actor' => $this->userSnapshot($actor),
                    'context' => [
                        'academicYearId' => $record->academic_year_id,
                        'termId' => $record->term_id,
                        'reportPeriodId' => $record->report_period_id,
                    ],
                    'recordedAt' => $record->exported_at?->toISOString(),
                ];
            });

        return collect($events)
            ->merge($exports)
            ->sortByDesc('recordedAt')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        $query = PreschoolReportExportRecord::query()->with(['actor', 'academicYear', 'term', 'reportPeriod']);

        foreach ([
            'export_type' => 'export_type',
            'export_format' => 'export_format',
            'academic_year_id' => 'academic_year_id',
            'term_id' => 'term_id',
            'report_period_id' => 'report_period_id',
            'actor_user_id' => 'actor_user_id',
            'source' => 'export_source',
        ] as $filterKey => $column) {
            $value = $filters[$filterKey] ?? null;
            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        if (($filters['exported_from'] ?? '') !== '') {
            $query->whereDate('exported_at', '>=', $filters['exported_from']);
        }

        if (($filters['exported_to'] ?? '') !== '') {
            $query->whereDate('exported_at', '<=', $filters['exported_to']);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('export_type', 'like', "%{$search}%")
                    ->orWhere('export_format', 'like', "%{$search}%")
                    ->orWhere('export_source', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('export_reason', 'like', "%{$search}%")
                    ->orWhere('checksum', 'like', "%{$search}%")
                    ->orWhereHas('actor', function (Builder $actorQuery) use ($search): void {
                        $actorQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function transformRecord(PreschoolReportExportRecord $record): array
    {
        $record->loadMissing(['actor', 'academicYear', 'term', 'reportPeriod']);
        $snapshotIds = collect($record->snapshot_ids ?? [])->filter()->values();

        return [
            'id' => $record->id,
            'actorUserId' => $record->actor_user_id,
            'actorRole' => $record->actor_role,
            'actor' => $this->userSnapshot($record->actor),
            'exportType' => $record->export_type,
            'exportFormat' => $record->export_format,
            'exportSource' => $record->export_source,
            'academicYearId' => $record->academic_year_id,
            'termId' => $record->term_id,
            'reportPeriodId' => $record->report_period_id,
            'academicYear' => $this->academicYearSnapshot($record->academicYear),
            'term' => $this->termSnapshot($record->term),
            'reportPeriod' => $this->reportPeriodSnapshot($record->reportPeriod),
            'filters' => $record->filters ?? [],
            'snapshotIds' => $record->snapshot_ids ?? [],
            'recordCount' => (int) ($record->record_count ?? 0),
            'fileName' => $record->file_name,
            'checksum' => $record->checksum,
            'exportReason' => $record->export_reason,
            'exportedAt' => $record->exported_at?->toISOString(),
            'requestContext' => $record->request_context ?? [],
            'snapshotCount' => $snapshotIds->count(),
            'downloadable' => true,
        ];
    }

    private function snapshotsForRecord(PreschoolReportExportRecord $record): Collection
    {
        $ids = collect($record->snapshot_ids ?? [])->filter()->map(fn ($value) => (int) $value)->values();

        if ($ids->isNotEmpty()) {
            $snapshots = PreschoolReportSnapshot::query()
                ->with(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy'])
                ->whereIn('id', $ids->all())
                ->get()
                ->sortBy(fn (PreschoolReportSnapshot $snapshot) => $ids->search((int) $snapshot->id))
                ->values();

            return $snapshots;
        }

        $filters = is_array($record->filters) ? $record->filters : [];

        return $this->archiveService->collectSnapshots($filters);
    }

    private function comparisonSummary(Collection $snapshots): array
    {
        $attendance = [
            'attendanceCount' => 0,
            'presentCount' => 0,
            'lateCount' => 0,
            'absentCount' => 0,
            'excusedCount' => 0,
        ];
        $reports = [
            'finalizedAssessments' => 0,
            'averageScore' => null,
            'observationCount' => 0,
            'studentCount' => 0,
        ];
        $averageScores = [];
        $absenceRates = [];
        $studentCounts = [];

        foreach ($snapshots as $snapshot) {
            $payload = $snapshot->snapshot_payload ?? [];
            $att = $this->attendanceSummary($payload);
            $rep = $this->reportSummary($payload);

            $attendance['attendanceCount'] += (int) ($att['attendanceCount'] ?? 0);
            $attendance['presentCount'] += (int) ($att['presentCount'] ?? 0);
            $attendance['lateCount'] += (int) ($att['lateCount'] ?? 0);
            $attendance['absentCount'] += (int) ($att['absentCount'] ?? 0);
            $attendance['excusedCount'] += (int) ($att['excusedCount'] ?? 0);

            if ($rep['averageScore'] !== null) {
                $averageScores[] = (float) $rep['averageScore'];
            }
            if ($rep['studentCount'] !== null) {
                $studentCounts[] = (int) $rep['studentCount'];
            }
            if (($att['attendanceCount'] ?? 0) > 0) {
                $absenceRates[] = round(((int) ($att['absentCount'] ?? 0) / max((int) ($att['attendanceCount'] ?? 0), 1)) * 100, 2);
            }

            $reports['finalizedAssessments'] += (int) ($rep['finalizedAssessments'] ?? 0);
            $reports['observationCount'] += (int) ($rep['observationCount'] ?? 0);
        }

        $reports['averageScore'] = count($averageScores) ? round((float) (array_sum($averageScores) / count($averageScores)), 2) : null;
        $reports['studentCount'] = count($studentCounts) ? max($studentCounts) : 0;

        return [
            'snapshotCount' => $snapshots->count(),
            'attendanceCount' => $attendance['attendanceCount'],
            'presentCount' => $attendance['presentCount'],
            'lateCount' => $attendance['lateCount'],
            'absentCount' => $attendance['absentCount'],
            'excusedCount' => $attendance['excusedCount'],
            'absenceRate' => $attendance['attendanceCount'] > 0
                ? round(($attendance['absentCount'] / max($attendance['attendanceCount'], 1)) * 100, 2)
                : null,
            'finalizedAssessments' => $reports['finalizedAssessments'],
            'averageScore' => $reports['averageScore'],
            'observationCount' => $reports['observationCount'],
            'studentCount' => $reports['studentCount'],
        ];
    }

    private function resolveComparisonSnapshots(string $mode, array $context): Collection
    {
        $query = PreschoolReportSnapshot::query()
            ->with(['student', 'preschoolClass', 'academicYear', 'term', 'reportPeriod', 'generatedBy'])
            ->whereIn('lifecycle_state', ['finalized', 'locked', 'archived']);

        match ($mode) {
            'snapshot_version_vs_version' => $this->applySnapshotComparisonContext($query, $context),
            'term_vs_term' => $query->where('term_id', Arr::get($context, 'term_id')),
            'academic_year_vs_academic_year' => $query->where('academic_year_id', Arr::get($context, 'academic_year_id')),
            'report_period_vs_report_period' => $query->where('report_period_id', Arr::get($context, 'report_period_id')),
            'class_vs_class' => $query->where('class_id', Arr::get($context, 'class_id')),
            'student_progression' => $query->where('student_id', Arr::get($context, 'student_id')),
            default => $query->where('report_period_id', Arr::get($context, 'report_period_id')),
        };

        $query = $this->applyComparisonContext($query, $mode, $context);

        return $query
            ->orderByDesc('generated_at')
            ->orderByDesc('snapshot_version')
            ->orderByDesc('id')
            ->get();
    }

    private function applySnapshotComparisonContext(Builder $query, array $context): void
    {
        if (($leftId = Arr::get($context, 'snapshot_id')) !== null) {
            $query->where('id', $leftId);
            return;
        }

        if (($version = Arr::get($context, 'snapshot_version')) !== null) {
            $query->where('snapshot_version', $version);
        }
    }

    private function applyComparisonContext(Builder $query, string $mode, array $context): Builder
    {
        return match ($mode) {
            'term_vs_term' => $query->where('term_id', Arr::get($context, 'term_id')),
            'academic_year_vs_academic_year' => $query->where('academic_year_id', Arr::get($context, 'academic_year_id')),
            'report_period_vs_report_period' => $query->where('report_period_id', Arr::get($context, 'report_period_id')),
            'class_vs_class' => $query->where('class_id', Arr::get($context, 'class_id')),
            'student_progression' => $query->where('student_id', Arr::get($context, 'student_id')),
            'snapshot_version_vs_version' => $query,
            default => $query,
        };
    }

    private function comparisonContextLabel(string $mode, array $context): string
    {
        return match ($mode) {
            'term_vs_term' => 'Term #'.(Arr::get($context, 'term_id') ?? '-'),
            'academic_year_vs_academic_year' => 'Academic year #'.(Arr::get($context, 'academic_year_id') ?? '-'),
            'report_period_vs_report_period' => 'Report period #'.(Arr::get($context, 'report_period_id') ?? '-'),
            'class_vs_class' => 'Class #'.(Arr::get($context, 'class_id') ?? '-'),
            'student_progression' => 'Student #'.(Arr::get($context, 'student_id') ?? '-'),
            'snapshot_version_vs_version' => 'Snapshot #'.(Arr::get($context, 'snapshot_id') ?? Arr::get($context, 'snapshot_version') ?? '-'),
            default => 'Comparison context',
        };
    }

    private function snapshotTrend(Collection $snapshots): array
    {
        return $snapshots
            ->groupBy(fn (PreschoolReportSnapshot $snapshot): string => $snapshot->generated_at?->format('Y-m-d') ?? 'unknown')
            ->map(fn (Collection $items, string $day): array => [
                'day' => $day,
                'snapshotCount' => $items->count(),
                'averageScore' => $this->comparisonSummary($items)['averageScore'],
                'attendanceCount' => $this->comparisonSummary($items)['attendanceCount'],
            ])
            ->values()
            ->all();
    }

    private function metricDelta(mixed $left, mixed $right): mixed
    {
        if (! is_numeric($left) || ! is_numeric($right)) {
            return null;
        }

        return round((float) $right - (float) $left, 2);
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
        ];
    }

    private function reportSummary(array $payload): array
    {
        $summary = Arr::get($payload, 'summary', Arr::get($payload, 'report.summary', []));
        $summary = is_array($summary) ? $summary : [];

        return [
            'finalizedAssessments' => $this->numberValue($summary, ['finalizedAssessments', 'finalized_assessments']),
            'averageScore' => $this->numberValue($summary, ['averageScore', 'average_score']),
            'observationCount' => $this->numberValue($summary, ['observationCount', 'observation_count']),
            'studentCount' => $this->numberValue($summary, ['studentCount', 'student_count']),
        ];
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
}
