<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportExportRecord;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolReportSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Institutional reconstruction stays read-only and snapshot-first so admins
 * can review historical state, audit behavior, and replay sequence without
 * mutating live Preschool records.
 */
class PreschoolInstitutionalReconstructionService
{
    public function __construct(
        private readonly PreschoolSnapshotArchiveService $snapshotArchiveService,
        private readonly PreschoolExportGovernanceService $exportGovernanceService,
        private readonly PreschoolLifecycleAuditService $auditService,
        private readonly PreschoolAcademicLifecycleService $academicLifecycleService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function review(array $filters = []): array
    {
        $auditEvents = $this->auditEvents($filters, 60);
        $exportEvents = $this->exportEvents($filters, 60);
        $snapshots = $this->snapshots($filters);

        return [
            'overview' => [
                'totalAudits' => $auditEvents->count(),
                'blockedWrites' => $auditEvents->where('actionType', 'write.blocked')->count(),
                'overrideAttempts' => $auditEvents->where('actionType', 'override.attempt')->count(),
                'overrideApprovals' => $auditEvents->where('actionType', 'override.approved')->count(),
                'exportEvents' => $exportEvents->count(),
                'snapshotCount' => $snapshots->count(),
                'reconstructionContexts' => $this->reconstructionContextCount($filters),
            ],
            'overrideReview' => $auditEvents
                ->filter(fn (array $event): bool => str_starts_with($event['actionType'] ?? '', 'override.'))
                ->take(20)
                ->values()
                ->all(),
            'blockedWriteReview' => $auditEvents
                ->filter(fn (array $event): bool => ($event['actionType'] ?? '') === 'write.blocked')
                ->take(20)
                ->values()
                ->all(),
            'exportReview' => $exportEvents
                ->take(20)
                ->values()
                ->all(),
            'anomalyReview' => $this->anomalyReview($auditEvents, $exportEvents, $snapshots),
            'integrityReview' => [
                'snapshotStates' => $snapshots->groupBy('lifecycle_state')->map->count()->map(fn ($total, $state) => [
                    'state' => $state,
                    'total' => $total,
                ])->values()->all(),
                'reportPeriodStates' => $this->reportPeriodStateSummary($filters),
                'academicYearStates' => $this->academicYearStateSummary($filters),
                'termStates' => $this->termStateSummary($filters),
            ],
            'retentionReview' => $this->retentionReview($filters, $snapshots, $exportEvents),
            'timeline' => $this->timeline($filters, 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function analytics(array $filters = []): array
    {
        $review = $this->review($filters);

        return [
            'overview' => $review['overview'],
            'overrideActorCounts' => $this->actorCounts(
                $this->auditEvents($filters, 180)->filter(fn (array $event): bool => str_starts_with($event['actionType'] ?? '', 'override.'))
            ),
            'exportActorCounts' => $this->actorCounts($this->exportEvents($filters, 180)),
            'blockedWriteTrend' => $this->trendByDay($this->auditEvents($filters, 30)->filter(fn (array $event): bool => ($event['actionType'] ?? '') === 'write.blocked')),
            'replayEventCounts' => $this->eventCounts($review['timeline'] ?? []),
            'retentionSummary' => $review['retentionReview'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function reconstruct(array $filters = []): array
    {
        $snapshots = $this->snapshots($filters);
        $auditEvents = $this->auditEvents($filters, 120);
        $exportEvents = $this->exportEvents($filters, 120);
        $assignmentEvents = $this->assignmentEvents($filters);
        $reportPeriods = $this->reportPeriods($filters);
        $academicYears = $this->academicYears($filters);
        $terms = $this->terms($filters);
        $timeline = $this->mergeTimeline(
            $this->snapshotTimeline($snapshots),
            $auditEvents->all(),
            $exportEvents->all(),
            $assignmentEvents,
            $this->reportPeriodTimeline($reportPeriods),
        );

        return [
            'context' => $this->contextSummary($filters),
            'summary' => [
                'snapshotCount' => $snapshots->count(),
                'auditCount' => $auditEvents->count(),
                'exportCount' => $exportEvents->count(),
                'assignmentCount' => count($assignmentEvents),
                'reportPeriodCount' => $reportPeriods->count(),
                'academicYearCount' => $academicYears->count(),
                'termCount' => $terms->count(),
            ],
            'academicContext' => [
                'academicYears' => $academicYears->map(fn ($year): array => $this->academicYearSnapshot($year))->values()->all(),
                'terms' => $terms->map(fn ($term): array => $this->termSnapshot($term))->values()->all(),
                'reportPeriods' => $reportPeriods->map(fn ($period): array => $this->reportPeriodSnapshot($period))->values()->all(),
            ],
            'historicalState' => [
                'snapshots' => $snapshots->map(fn (PreschoolReportSnapshot $snapshot): array => $this->snapshotArchiveService->preview($snapshot))->values()->all(),
                'audits' => $auditEvents->take(50)->values()->all(),
                'exports' => $exportEvents->take(50)->values()->all(),
                'assignments' => array_slice($assignmentEvents, 0, 50),
                'reportPeriods' => $reportPeriods->map(fn ($period): array => $this->reportPeriodSnapshot($period))->values()->all(),
            ],
            'timeline' => $timeline,
            'references' => [
                'snapshotIds' => $snapshots->pluck('id')->values()->all(),
                'exportIds' => $exportEvents->pluck('id')->values()->all(),
                'auditIds' => $auditEvents->pluck('id')->values()->all(),
                'assignmentIds' => collect($assignmentEvents)->pluck('id')->values()->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function replay(array $filters = []): array
    {
        $reconstruction = $this->reconstruct($filters);
        $timeline = collect($reconstruction['timeline'] ?? [])->values();

        return [
            'items' => $timeline->all(),
            'overview' => [
                'totalEvents' => $timeline->count(),
                'auditEvents' => $timeline->where('source', 'audit')->count(),
                'exportEvents' => $timeline->where('source', 'export')->count(),
                'snapshotEvents' => $timeline->where('source', 'snapshot')->count(),
                'assignmentEvents' => $timeline->where('source', 'assignment')->count(),
                'reportPeriodEvents' => $timeline->where('source', 'report_period')->count(),
            ],
            'timeline' => $timeline->all(),
            'summary' => $reconstruction['summary'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function snapshots(array $filters): Collection
    {
        return $this->snapshotArchiveService->collectSnapshots($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function auditQuery(array $filters): Builder
    {
        $query = PreschoolLifecycleAuditLog::query()->with(['actor', 'reportPeriod']);

        foreach ([
            'academic_year_id',
            'term_id',
            'report_period_id',
            'actor_user_id',
            'entity_type',
            'entity_id',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['action_type'] ?? '') !== '') {
            $query->where('action_type', $filters['action_type']);
        }

        if (($filters['generated_from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['generated_from']);
        }

        if (($filters['generated_to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['generated_to']);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('action_type', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%")
                    ->orWhere('entity_id', 'like', "%{$search}%")
                    ->orWhere('override_reason', 'like', "%{$search}%")
                    ->orWhere('lock_reason', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportQuery(array $filters): Builder
    {
        $query = PreschoolReportExportRecord::query()->with(['actor', 'academicYear', 'term', 'reportPeriod']);

        foreach ([
            'academic_year_id',
            'term_id',
            'report_period_id',
            'actor_user_id',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        foreach ([
            'export_type' => 'export_type',
            'export_format' => 'export_format',
            'source' => 'export_source',
        ] as $filterKey => $column) {
            if (($filters[$filterKey] ?? '') !== '') {
                $query->where($column, $filters[$filterKey]);
            }
        }

        if (($filters['generated_from'] ?? '') !== '') {
            $query->whereDate('exported_at', '>=', $filters['generated_from']);
        }

        if (($filters['generated_to'] ?? '') !== '') {
            $query->whereDate('exported_at', '<=', $filters['generated_to']);
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
                    ->orWhere('checksum', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportPeriodQuery(array $filters): Builder
    {
        $query = PreschoolReportPeriod::query()->with(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);

        foreach ([
            'academic_year_id',
            'term_id',
            'report_period_id',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $column = $field === 'report_period_id' ? 'id' : $field;
                $query->where($column, $filters[$field]);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function academicYearQuery(array $filters): Builder
    {
        $query = PreschoolAcademicYear::query();

        if (($filters['academic_year_id'] ?? null) !== null && $filters['academic_year_id'] !== '') {
            $query->where('id', $filters['academic_year_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function termQuery(array $filters): Builder
    {
        $query = PreschoolAcademicTerm::query()->with('academicYear');

        foreach ([
            'academic_year_id',
            'term_id',
        ] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $column = $field === 'term_id' ? 'id' : $field;
                $query->where($column, $filters[$field]);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function auditEvents(array $filters, int $limit = 60): Collection
    {
        return $this->auditQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (PreschoolLifecycleAuditLog $log): array {
                return [
                    'id' => 'audit-'.$log->id,
                    'source' => 'audit',
                    'actionType' => $log->action_type,
                    'entityType' => $log->entity_type,
                    'entityId' => $log->entity_id,
                    'title' => $log->action_type,
                    'description' => trim(implode(' | ', array_filter([
                        $log->entity_type.' #'.$log->entity_id,
                        $log->lock_reason,
                        $log->override_reason,
                    ]))),
                    'actor' => $this->userSnapshot($log->actor),
                    'context' => [
                        'academicYearId' => $log->academic_year_id,
                        'termId' => $log->term_id,
                        'reportPeriodId' => $log->report_period_id,
                    ],
                    'previousState' => $log->previous_state,
                    'newState' => $log->new_state,
                    'recordedAt' => $log->created_at?->toISOString(),
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportEvents(array $filters, int $limit = 60): Collection
    {
        return $this->exportQuery($filters)
            ->orderByDesc('exported_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (PreschoolReportExportRecord $record): array {
                return [
                    'id' => 'export-'.$record->id,
                    'source' => 'export',
                    'actionType' => 'report_export.created',
                    'entityType' => 'report_export_record',
                    'entityId' => (string) $record->id,
                    'title' => $record->export_type.' export',
                    'description' => trim(implode(' | ', array_filter([
                        $record->export_format,
                        $record->export_source,
                        $record->file_name,
                    ]))),
                    'actor' => $this->userSnapshot($record->actor),
                    'context' => [
                        'academicYearId' => $record->academic_year_id,
                        'termId' => $record->term_id,
                        'reportPeriodId' => $record->report_period_id,
                    ],
                    'recordedAt' => $record->exported_at?->toISOString(),
                    'exportType' => $record->export_type,
                    'exportFormat' => $record->export_format,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function assignmentEvents(array $filters): array
    {
        $classStudentQuery = DB::table('preschool_class_students')
            ->leftJoin('preschool_classes', 'preschool_classes.id', '=', 'preschool_class_students.class_id')
            ->leftJoin('preschool_students', 'preschool_students.id', '=', 'preschool_class_students.student_id')
            ->select([
                'preschool_class_students.id',
                'preschool_class_students.class_id',
                'preschool_class_students.student_id',
                'preschool_class_students.academic_year_id',
                'preschool_class_students.term_id',
                'preschool_class_students.enrollment_status',
                'preschool_class_students.enrollment_started_at',
                'preschool_class_students.enrollment_ended_at',
                'preschool_class_students.academic_year',
                'preschool_class_students.term_label',
                'preschool_classes.name as class_name',
                'preschool_students.first_name as student_first_name',
                'preschool_students.last_name as student_last_name',
            ])
            ->orderByDesc('preschool_class_students.enrollment_started_at')
            ->limit(40);

        foreach (['academic_year_id', 'term_id', 'class_id', 'student_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $classStudentQuery->where('preschool_class_students.'.$field, $filters[$field]);
            }
        }

        $teacherQuery = DB::table('preschool_class_teacher_assignments')
            ->leftJoin('preschool_classes', 'preschool_classes.id', '=', 'preschool_class_teacher_assignments.class_id')
            ->leftJoin('users', 'users.id', '=', 'preschool_class_teacher_assignments.teacher_user_id')
            ->select([
                'preschool_class_teacher_assignments.id',
                'preschool_class_teacher_assignments.class_id',
                'preschool_class_teacher_assignments.teacher_user_id',
                'preschool_class_teacher_assignments.academic_year_id',
                'preschool_class_teacher_assignments.term_id',
                'preschool_class_teacher_assignments.status',
                'preschool_class_teacher_assignments.assigned_at',
                'preschool_class_teacher_assignments.ended_at',
                'preschool_class_teacher_assignments.academic_year',
                'preschool_class_teacher_assignments.term_label',
                'preschool_class_teacher_assignments.teacher_display_name',
                'preschool_classes.name as class_name',
                'users.first_name as teacher_first_name',
                'users.last_name as teacher_last_name',
            ])
            ->orderByDesc('preschool_class_teacher_assignments.assigned_at')
            ->limit(40);

        foreach (['academic_year_id', 'term_id', 'class_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $teacherQuery->where('preschool_class_teacher_assignments.'.$field, $filters[$field]);
            }
        }

        $events = [];

        foreach ($classStudentQuery->get() as $row) {
            $events[] = [
                'id' => 'assignment-student-'.$row->id,
                'source' => 'assignment',
                'actionType' => 'assignment.student_class.'.strtolower((string) ($row->enrollment_status ?? 'active')),
                'title' => 'Student assignment',
                'description' => trim(implode(' | ', array_filter([
                    trim(($row->student_first_name ?? '').' '.($row->student_last_name ?? '')) ?: null,
                    $row->class_name ?? null,
                    $row->term_label ?? null,
                    $row->academic_year ?? null,
                    $row->enrollment_status ?? null,
                ]))),
                'actor' => null,
                'context' => [
                    'academicYearId' => $row->academic_year_id,
                    'termId' => $row->term_id,
                    'classId' => $row->class_id,
                    'studentId' => $row->student_id,
                ],
                'recordedAt' => optional($row->enrollment_started_at)->toISOString() ?? null,
            ];
        }

        foreach ($teacherQuery->get() as $row) {
            $events[] = [
                'id' => 'assignment-teacher-'.$row->id,
                'source' => 'assignment',
                'actionType' => 'assignment.teacher_class.'.strtolower((string) ($row->status ?? 'active')),
                'title' => 'Teacher assignment',
                'description' => trim(implode(' | ', array_filter([
                    $row->teacher_display_name
                        ?: trim(($row->teacher_first_name ?? '').' '.($row->teacher_last_name ?? '')) ?: null,
                    $row->class_name ?? null,
                    $row->term_label ?? null,
                    $row->academic_year ?? null,
                    $row->status ?? null,
                ]))),
                'actor' => null,
                'context' => [
                    'academicYearId' => $row->academic_year_id,
                    'termId' => $row->term_id,
                    'classId' => $row->class_id,
                ],
                'recordedAt' => optional($row->assigned_at)->toISOString() ?? null,
            ];
        }

        return collect($events)
            ->sortByDesc('recordedAt')
            ->take(80)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportPeriods(array $filters): Collection
    {
        return $this->reportPeriodQuery($filters)
            ->orderByDesc('finalized_at')
            ->orderByDesc('locked_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function academicYears(array $filters): Collection
    {
        return $this->academicYearQuery($filters)
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function terms(array $filters): Collection
    {
        return $this->termQuery($filters)
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function retentionReview(array $filters, Collection $snapshots, Collection $exportEvents): array
    {
        $archivedYears = PreschoolAcademicYear::query()->where('status', 'closed')->count();
        $archivedTerms = PreschoolAcademicTerm::query()->where('status', 'closed')->count();
        $archivedReportPeriods = PreschoolReportPeriod::query()->where('status', 'archived')->count();

        return [
            'archivedAcademicYears' => $archivedYears,
            'archivedTerms' => $archivedTerms,
            'archivedReportPeriods' => $archivedReportPeriods,
            'oldSnapshots' => $snapshots->filter(fn (PreschoolReportSnapshot $snapshot): bool => $snapshot->generated_at?->lt(now()->subDays(180)) ?? false)->count(),
            'oldExports' => $exportEvents->filter(fn (array $event): bool => isset($event['recordedAt']) && Carbon::parse($event['recordedAt'])->lt(now()->subDays(180)))->count(),
            'retentionWindowDays' => 180,
            'retentionNotes' => 'Review-only retention metadata; no destructive cleanup is triggered from this workflow.',
            'filters' => $filters,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function actorCounts(Collection $events): array
    {
        return $events
            ->filter(fn (array $event): bool => ! empty($event['actor']['id'] ?? null))
            ->groupBy(fn (array $event): string => (string) ($event['actor']['id'] ?? ''))
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'actorUserId' => $first['actor']['id'] ?? null,
                    'actorName' => $first['actor']['displayName'] ?? null,
                    'actorRole' => $first['actor']['roleCode'] ?? null,
                    'total' => $items->count(),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function trendByDay(Collection $events): array
    {
        return $events
            ->groupBy(fn (array $event): string => substr((string) ($event['recordedAt'] ?? 'unknown'), 0, 10))
            ->map(fn (Collection $items, string $day): array => [
                'day' => $day,
                'total' => $items->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function eventCounts(array $events): array
    {
        return collect($events)
            ->groupBy('actionType')
            ->map(fn (Collection $items, string $action): array => [
                'actionType' => $action,
                'total' => $items->count(),
            ])
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $auditEvents
     * @param  Collection<int, array<string, mixed>>  $exportEvents
     * @param  Collection<int, PreschoolReportSnapshot>  $snapshots
     */
    private function anomalyReview(Collection $auditEvents, Collection $exportEvents, Collection $snapshots): array
    {
        return [
            'overrideEvents' => $auditEvents->filter(fn (array $event): bool => str_starts_with($event['actionType'] ?? '', 'override.'))->count(),
            'blockedWrites' => $auditEvents->where('actionType', 'write.blocked')->count(),
            'exportEvents' => $exportEvents->count(),
            'snapshotFreezes' => $snapshots->whereIn('lifecycle_state', ['finalized', 'locked', 'archived'])->count(),
            'repeatActors' => collect($this->actorCounts($auditEvents))
                ->filter(fn (array $actor): bool => (int) ($actor['total'] ?? 0) >= 3)
                ->take(10)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reconstructionContextCount(array $filters): int
    {
        return collect([
            Arr::get($filters, 'academic_year_id'),
            Arr::get($filters, 'term_id'),
            Arr::get($filters, 'report_period_id'),
            Arr::get($filters, 'class_id'),
            Arr::get($filters, 'student_id'),
        ])->filter(fn ($value) => $value !== null && $value !== '')->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function contextSummary(array $filters): array
    {
        return [
            'academicYearId' => Arr::get($filters, 'academic_year_id'),
            'termId' => Arr::get($filters, 'term_id'),
            'reportPeriodId' => Arr::get($filters, 'report_period_id'),
            'classId' => Arr::get($filters, 'class_id'),
            'studentId' => Arr::get($filters, 'student_id'),
            'snapshotType' => Arr::get($filters, 'snapshot_type'),
            'lifecycleState' => Arr::get($filters, 'lifecycle_state'),
            'source' => Arr::get($filters, 'source'),
            'search' => Arr::get($filters, 'search'),
        ];
    }

    /**
     * @param  Collection<int, PreschoolReportSnapshot>  $snapshots
     */
    private function snapshotTimeline(Collection $snapshots): array
    {
        return $snapshots
            ->map(function (PreschoolReportSnapshot $snapshot): array {
                return [
                    'id' => 'snapshot-'.$snapshot->id,
                    'source' => 'snapshot',
                    'actionType' => 'report_snapshot.generated',
                    'title' => $snapshot->snapshot_type.' snapshot',
                    'description' => trim(implode(' | ', array_filter([
                        $snapshot->lifecycle_state,
                        'v'.($snapshot->snapshot_version ?? 0),
                        $snapshot->reportPeriod?->period_label,
                    ]))),
                    'actor' => $this->userSnapshot($snapshot->generatedBy),
                    'context' => [
                        'academicYearId' => $snapshot->academic_year_id,
                        'termId' => $snapshot->term_id,
                        'reportPeriodId' => $snapshot->report_period_id,
                        'classId' => $snapshot->class_id,
                        'studentId' => $snapshot->student_id,
                    ],
                    'recordedAt' => $snapshot->generated_at?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, PreschoolReportPeriod>  $reportPeriods
     */
    private function reportPeriodTimeline(Collection $reportPeriods): array
    {
        return $reportPeriods
            ->map(function (PreschoolReportPeriod $period): array {
                $recordedAt = $period->archived_at ?? $period->finalized_at ?? $period->locked_at;

                return [
                    'id' => 'report-period-'.$period->id,
                    'source' => 'report_period',
                    'actionType' => 'report_period.'.$period->status,
                    'title' => $period->period_label,
                    'description' => trim(implode(' | ', array_filter([
                        $period->status,
                        $period->academicYear?->label ?? $period->academicYear?->code,
                        $period->term?->name ?? $period->term?->code,
                    ]))),
                    'actor' => $this->userSnapshot($period->lockedBy ?? $period->finalizedBy ?? $period->archivedBy),
                    'context' => [
                        'academicYearId' => $period->academic_year_id,
                        'termId' => $period->term_id,
                        'reportPeriodId' => $period->id,
                    ],
                    'recordedAt' => $recordedAt?->toISOString(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$sources
     */
    private function mergeTimeline(array ...$sources): array
    {
        return collect($sources)
            ->flatten(1)
            ->filter(fn (array $event): bool => ! empty($event['recordedAt'] ?? null))
            ->sortByDesc('recordedAt')
            ->values()
            ->all();
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
            'academicYear' => $this->academicYearSnapshot($period->academicYear),
            'term' => $this->termSnapshot($period->term),
        ];
    }

    private function reportPeriodStateSummary(array $filters): array
    {
        return $this->reportPeriodQuery($filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function academicYearStateSummary(array $filters): array
    {
        return $this->academicYearQuery($filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function termStateSummary(array $filters): array
    {
        return $this->termQuery($filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }
}
