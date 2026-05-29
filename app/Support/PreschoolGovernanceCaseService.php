<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolGovernanceCase;
use App\Models\PreschoolGovernanceCaseEvidence;
use App\Models\PreschoolGovernanceCaseEvent;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Governance cases are workflow records that sit beside immutable snapshots.
 * They let administrators track, assign, escalate, and resolve institutional
 * risks without mutating historical Preschool data.
 */
class PreschoolGovernanceCaseService
{
    public const STATUSES = ['open', 'under_review', 'investigating', 'awaiting_evidence', 'escalated', 'resolved', 'closed'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const SOURCE_TYPES = ['governance_diff', 'integrity_warning', 'export_mismatch', 'lifecycle_anomaly', 'reconstruction_inconsistency', 'manual_review'];
    public const WORKFLOW_STATUSES = ['open', 'under_review', 'investigating', 'awaiting_evidence'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function index(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $query = $this->query($filters);

        $paginator = $query
            ->orderByDesc('is_urgent')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'under_review' THEN 2 WHEN 'investigating' THEN 3 WHEN 'awaiting_evidence' THEN 4 WHEN 'escalated' THEN 5 WHEN 'resolved' THEN 6 WHEN 'closed' THEN 7 ELSE 8 END ASC")
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->getCollection()->map(fn (PreschoolGovernanceCase $case): array => $this->preview($case))->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
            'summary' => $this->summary($filters),
            'options' => $this->options(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summary(array $filters = []): array
    {
        $query = $this->query($filters);
        $today = Carbon::today();

        return [
            'totalCases' => (clone $query)->count(),
            'openCases' => (clone $query)->where('status', 'open')->count(),
            'underReviewCases' => (clone $query)->whereIn('status', ['under_review', 'investigating', 'awaiting_evidence'])->count(),
            'escalatedCases' => (clone $query)->where('status', 'escalated')->count(),
            'resolvedCases' => (clone $query)->where('status', 'resolved')->count(),
            'closedCases' => (clone $query)->where('status', 'closed')->count(),
            'criticalCases' => (clone $query)->where('severity', 'critical')->count(),
            'urgentCases' => (clone $query)->where('is_urgent', true)->count(),
            'overdueCases' => (clone $query)->whereNotIn('status', ['resolved', 'closed'])->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
        ];
    }

    public function options(): array
    {
        return [
            'statusOptions' => collect(self::STATUSES)->map(fn (string $status): array => ['value' => $status, 'label' => Str::headline(str_replace('_', ' ', $status))])->values()->all(),
            'severityOptions' => collect(self::SEVERITIES)->map(fn (string $severity): array => ['value' => $severity, 'label' => Str::headline($severity)])->values()->all(),
            'sourceOptions' => collect(self::SOURCE_TYPES)->map(fn (string $sourceType): array => ['value' => $sourceType, 'label' => Str::headline(str_replace('_', ' ', $sourceType))])->values()->all(),
            'workflowStatusOptions' => collect(self::WORKFLOW_STATUSES)->map(fn (string $status): array => ['value' => $status, 'label' => Str::headline(str_replace('_', ' ', $status))])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function assignees(array $filters = []): array
    {
        $query = User::query()
            ->with(['role'])
            ->whereIn('role_code', ['superadmin', 'adminpreschool', 'teacher-preschool'])
            ->where('status', 'active')
            ->orderByRaw("CASE role_code WHEN 'superadmin' THEN 1 WHEN 'adminpreschool' THEN 2 WHEN 'teacher-preschool' THEN 3 ELSE 4 END ASC")
            ->orderBy('first_name')
            ->orderBy('last_name');

        if (($search = trim((string) Arr::get($filters, 'search', ''))) !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->limit(200)->get()->map(fn (User $user): array => $this->userSnapshot($user))->values()->all();
    }

    public function create(array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($data, $actor): PreschoolGovernanceCase {
            $case = PreschoolGovernanceCase::query()->create($this->payload($data, $actor, true));
            $case->load($this->relations())->loadCount(['events', 'evidence']);
            $this->recordEvent($case, 'governance_case_created', null, $case->status, $actor, 'Case created.', [
                'sourceType' => $case->source_type,
                'sourceReference' => $case->source_reference,
            ]);

            return $case->fresh()->load($this->relations())->loadCount(['events', 'evidence']);
        });
    }

    public function update(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);
            $changes = $this->applyMutableUpdates($case, $data);
            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            if (($note = trim((string) Arr::get($data, 'latest_note', ''))) !== '') {
                $this->recordEvent($case, 'governance_case_note_added', $before['status'] ?? null, $case->status, $actor, $note, ['changes' => $changes]);
            } elseif ($changes !== []) {
                $this->recordEvent($case, 'governance_case_updated', $before['status'] ?? null, $case->status, $actor, 'Case metadata updated.', ['changes' => $changes]);
            }

            return $case;
        });
    }

    public function assign(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);

            $case->owner_user_id = Arr::get($data, 'owner_user_id') ?: null;
            $case->reviewer_user_id = Arr::get($data, 'reviewer_user_id') ?: null;
            $case->escalation_officer_user_id = Arr::get($data, 'escalation_officer_user_id') ?: null;
            $case->due_date = Arr::get($data, 'due_date') ?: null;

            $workflowStatus = strtolower(trim((string) Arr::get($data, 'status', '')));
            if ($workflowStatus !== '' && in_array($workflowStatus, self::WORKFLOW_STATUSES, true)) {
                $case->status = $workflowStatus;
            } elseif ($case->status === 'open') {
                $case->status = 'under_review';
            }

            if (($note = trim((string) Arr::get($data, 'note', ''))) !== '') {
                $case->latest_note = $note;
            }

            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            $this->recordEvent($case, 'governance_case_assigned', $before['status'] ?? null, $case->status, $actor, 'Case assignments updated.', [
                'ownerUserId' => $case->owner_user_id,
                'reviewerUserId' => $case->reviewer_user_id,
                'escalationOfficerUserId' => $case->escalation_officer_user_id,
                'dueDate' => $case->due_date?->toDateString(),
                'workflowStatus' => $case->status,
            ]);

            return $case;
        });
    }

    public function escalate(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);
            $reason = trim((string) Arr::get($data, 'reason', Arr::get($data, 'urgent_reason', '')));

            $case->status = 'escalated';
            $case->is_urgent = true;
            $case->urgent_reason = $reason !== '' ? $reason : $case->urgent_reason;
            if (Arr::exists($data, 'escalation_officer_user_id')) {
                $case->escalation_officer_user_id = Arr::get($data, 'escalation_officer_user_id') ?: null;
            }
            if (Arr::exists($data, 'due_date')) {
                $case->due_date = Arr::get($data, 'due_date') ?: null;
            }

            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            $this->recordEvent($case, 'governance_case_escalated', $before['status'] ?? null, $case->status, $actor, $reason !== '' ? $reason : 'Case escalated.', [
                'urgentReason' => $case->urgent_reason,
                'escalationOfficerUserId' => $case->escalation_officer_user_id,
                'dueDate' => $case->due_date?->toDateString(),
            ]);

            return $case;
        });
    }

    public function resolve(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);
            $note = trim((string) Arr::get($data, 'resolution_note', Arr::get($data, 'note', '')));

            $case->status = 'resolved';
            $case->resolved_by = $actor->id;
            $case->resolved_at = now();
            $case->resolution_note = $note !== '' ? $note : $case->resolution_note;
            if ($note !== '') {
                $case->latest_note = $note;
            }

            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            $this->recordEvent($case, 'governance_case_resolved', $before['status'] ?? null, $case->status, $actor, $note !== '' ? $note : 'Case resolved.', [
                'resolutionNote' => $case->resolution_note,
            ]);

            return $case;
        });
    }

    public function close(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);
            $note = trim((string) Arr::get($data, 'note', Arr::get($data, 'closure_note', '')));

            $case->status = 'closed';
            $case->closed_by = $actor->id;
            $case->closed_at = now();
            if ($note !== '') {
                $case->latest_note = $note;
                if (trim((string) $case->resolution_note) === '') {
                    $case->resolution_note = $note;
                }
            }

            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            $this->recordEvent($case, 'governance_case_closed', $before['status'] ?? null, $case->status, $actor, $note !== '' ? $note : 'Case closed.', [
                'closureNote' => $note,
            ]);

            return $case;
        });
    }

    public function reopen(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCase
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCase {
            $case->loadMissing($this->relations());
            $before = $this->preview($case);
            $reason = trim((string) Arr::get($data, 'reason', Arr::get($data, 'note', '')));

            $case->status = 'open';
            $case->resolved_by = null;
            $case->resolved_at = null;
            $case->closed_by = null;
            $case->closed_at = null;
            $case->resolution_note = null;
            if ($reason !== '') {
                $case->latest_note = $reason;
            }

            $case->save();
            $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

            $this->recordEvent($case, 'governance_case_reopened', $before['status'] ?? null, $case->status, $actor, $reason !== '' ? $reason : 'Case reopened.', ['reason' => $reason]);

            return $case;
        });
    }

    public function addEvidence(PreschoolGovernanceCase $case, array $data, User $actor): PreschoolGovernanceCaseEvidence
    {
        return DB::transaction(function () use ($case, $data, $actor): PreschoolGovernanceCaseEvidence {
            $case->loadMissing($this->relations());
            $evidence = PreschoolGovernanceCaseEvidence::query()->create([
                'governance_case_id' => $case->id,
                'evidence_type' => (string) Arr::get($data, 'evidence_type', 'manual_note'),
                'evidence_reference' => Arr::get($data, 'evidence_reference') ?: null,
                'evidence_label' => Arr::get($data, 'evidence_label') ?: null,
                'evidence_description' => Arr::get($data, 'evidence_description') ?: null,
                'metadata' => Arr::get($data, 'metadata') ?: null,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->recordEvent($case, 'governance_case_evidence_added', $case->status, $case->status, $actor, Arr::get($data, 'evidence_label') ?: 'Evidence added.', [
                'evidenceId' => $evidence->id,
                'evidenceType' => $evidence->evidence_type,
                'evidenceReference' => $evidence->evidence_reference,
            ]);

            return $evidence;
        });
    }

    public function detail(PreschoolGovernanceCase $case): array
    {
        $case->loadMissing($this->relations())->loadCount(['events', 'evidence']);

        return [
            'record' => $this->preview($case),
            'events' => $case->events->sortByDesc('created_at')->values()->map(fn (PreschoolGovernanceCaseEvent $event): array => $this->eventSnapshot($event))->all(),
            'evidence' => $case->evidence->sortByDesc('created_at')->values()->map(fn (PreschoolGovernanceCaseEvidence $item): array => $this->evidenceSnapshot($item))->all(),
            'timeline' => $this->timelineFromCase($case),
            'summary' => [
                'eventCount' => $case->events->count(),
                'evidenceCount' => $case->evidence->count(),
                'timelineCount' => $case->events->count() + $case->evidence->count(),
                'lastUpdatedAt' => $case->updated_at?->toISOString(),
            ],
            'options' => $this->options(),
        ];
    }

    public function preview(PreschoolGovernanceCase $case): array
    {
        return [
            'id' => $case->id,
            'caseKey' => $case->case_key,
            'title' => $case->title,
            'summary' => $case->summary,
            'sourceType' => $case->source_type,
            'sourceReference' => $case->source_reference,
            'sourceContext' => $case->source_context ?? [],
            'severity' => $case->severity,
            'riskScore' => (int) $case->risk_score,
            'status' => $case->status,
            'isUrgent' => (bool) $case->is_urgent,
            'urgentReason' => $case->urgent_reason,
            'owner' => $this->userSnapshot($case->owner),
            'reviewer' => $this->userSnapshot($case->reviewer),
            'escalationOfficer' => $this->userSnapshot($case->escalationOfficer),
            'dueDate' => $case->due_date?->toDateString(),
            'academicYear' => $this->academicYearSnapshot($case->academicYear),
            'term' => $this->termSnapshot($case->term),
            'reportPeriod' => $this->reportPeriodSnapshot($case->reportPeriod),
            'class' => $this->classSnapshot($case->preschoolClass),
            'student' => $this->studentSnapshot($case->student),
            'createdBy' => $this->userSnapshot($case->creator),
            'resolvedBy' => $this->userSnapshot($case->resolver),
            'closedBy' => $this->userSnapshot($case->closer),
            'resolvedAt' => $case->resolved_at?->toISOString(),
            'closedAt' => $case->closed_at?->toISOString(),
            'resolutionNote' => $case->resolution_note,
            'latestNote' => $case->latest_note,
            'eventsCount' => (int) ($case->events_count ?? $case->events->count()),
            'evidenceCount' => (int) ($case->evidence_count ?? $case->evidence->count()),
            'createdAt' => $case->created_at?->toISOString(),
            'updatedAt' => $case->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters = []): Builder
    {
        $query = PreschoolGovernanceCase::query()
            ->with(['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student'])
            ->withCount(['events', 'evidence']);

        foreach (['academic_year_id', 'term_id', 'report_period_id', 'class_id', 'student_id', 'owner_user_id', 'reviewer_user_id', 'escalation_officer_user_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (['status', 'severity', 'source_type'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['source_reference'] ?? '') !== '') {
            $query->where('source_reference', 'like', '%'.trim((string) $filters['source_reference']).'%');
        }

        if (($filters['is_urgent'] ?? '') !== '') {
            $query->where('is_urgent', filter_var($filters['is_urgent'], FILTER_VALIDATE_BOOLEAN));
        }

        foreach ([['due_date', 'due_from', '>='], ['due_date', 'due_to', '<='], ['created_at', 'created_from', '>='], ['created_at', 'created_to', '<='], ['updated_at', 'updated_from', '>='], ['updated_at', 'updated_to', '<=']] as [$column, $filterKey, $operator]) {
            if (($filters[$filterKey] ?? '') !== '') {
                $query->whereDate($column, $operator, $filters[$filterKey]);
            }
        }

        if (($search = trim((string) Arr::get($filters, 'search', ''))) !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('case_key', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('source_reference', 'like', $like)
                    ->orWhere('urgent_reason', 'like', $like)
                    ->orWhere('resolution_note', 'like', $like)
                    ->orWhere('latest_note', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payload(array $data, User $actor, bool $create = false): array
    {
        $severity = $this->normalizeSeverity((string) Arr::get($data, 'severity', 'medium'));

        return [
            'case_key' => $create ? $this->generateCaseKey() : Arr::get($data, 'case_key', $this->generateCaseKey()),
            'title' => trim((string) Arr::get($data, 'title', '')),
            'summary' => Arr::get($data, 'summary') ?: null,
            'source_type' => $this->normalizeSourceType((string) Arr::get($data, 'source_type', 'manual_review')),
            'source_reference' => Arr::get($data, 'source_reference') ?: null,
            'source_context' => Arr::get($data, 'source_context') ?: null,
            'severity' => $severity,
            'risk_score' => max(0, min(100, (int) Arr::get($data, 'risk_score', $this->defaultRiskScore($severity)))),
            'status' => $this->normalizeStatus((string) Arr::get($data, 'status', 'open')),
            'is_urgent' => (bool) Arr::get($data, 'is_urgent', false),
            'urgent_reason' => Arr::get($data, 'urgent_reason') ?: null,
            'owner_user_id' => Arr::get($data, 'owner_user_id') ?: null,
            'reviewer_user_id' => Arr::get($data, 'reviewer_user_id') ?: null,
            'escalation_officer_user_id' => Arr::get($data, 'escalation_officer_user_id') ?: null,
            'due_date' => Arr::get($data, 'due_date') ?: null,
            'academic_year_id' => Arr::get($data, 'academic_year_id') ?: null,
            'term_id' => Arr::get($data, 'term_id') ?: null,
            'report_period_id' => Arr::get($data, 'report_period_id') ?: null,
            'class_id' => Arr::get($data, 'class_id') ?: null,
            'student_id' => Arr::get($data, 'student_id') ?: null,
            'created_by' => $actor->id,
            'latest_note' => Arr::get($data, 'latest_note') ?: null,
            'resolution_note' => Arr::get($data, 'resolution_note') ?: null,
            'resolved_by' => null,
            'resolved_at' => null,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function applyMutableUpdates(PreschoolGovernanceCase $case, array $data): array
    {
        $changes = [];
        foreach ([
            'title', 'summary', 'source_type', 'source_reference', 'source_context', 'severity', 'risk_score',
            'is_urgent', 'urgent_reason', 'owner_user_id', 'reviewer_user_id', 'escalation_officer_user_id',
            'due_date', 'academic_year_id', 'term_id', 'report_period_id', 'class_id', 'student_id', 'latest_note', 'resolution_note',
        ] as $field) {
            if (! Arr::exists($data, $field)) {
                continue;
            }

            $value = $data[$field];
            if ($field === 'source_type') {
                $value = $this->normalizeSourceType((string) $value);
            } elseif ($field === 'severity') {
                $value = $this->normalizeSeverity((string) $value);
            } elseif ($field === 'risk_score') {
                $value = max(0, min(100, (int) $value));
            } elseif ($field === 'is_urgent') {
                $value = (bool) $value;
            } elseif (in_array($field, ['title', 'summary', 'source_reference', 'urgent_reason', 'latest_note', 'resolution_note'], true)) {
                $value = trim((string) $value) ?: null;
            } elseif ($field === 'source_context') {
                $value = $value ?: null;
            } else {
                $value = $value ?: null;
            }

            if ($case->{$field} !== $value) {
                $changes[] = $field;
                $case->{$field} = $value;
            }
        }

        return $changes;
    }

    private function recordEvent(PreschoolGovernanceCase $case, string $eventType, ?string $previousStatus, ?string $newStatus, User $actor, ?string $note = null, array $metadata = []): PreschoolGovernanceCaseEvent
    {
        return PreschoolGovernanceCaseEvent::query()->create([
            'governance_case_id' => $case->id,
            'event_type' => $eventType,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role_code,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timelineFromCase(PreschoolGovernanceCase $case): array
    {
        $events = $case->events->map(function (PreschoolGovernanceCaseEvent $event): array {
            return [
                'id' => 'event:'.$event->id,
                'source' => 'event',
                'eventType' => $event->event_type,
                'actionType' => $event->event_type,
                'entityType' => 'governance_case',
                'entityId' => (string) $event->governance_case_id,
                'title' => Str::headline(str_replace('_', ' ', $event->event_type)),
                'description' => $event->note,
                'previousStatus' => $event->previous_status,
                'newStatus' => $event->new_status,
                'actor' => $this->userSnapshot($event->actor),
                'context' => $event->metadata ?? [],
                'recordedAt' => $event->created_at?->toISOString(),
            ];
        })->all();

        $evidence = $case->evidence->map(function (PreschoolGovernanceCaseEvidence $item): array {
            return [
                'id' => 'evidence:'.$item->id,
                'source' => 'evidence',
                'eventType' => 'governance_case_evidence_added',
                'actionType' => 'governance_case_evidence_added',
                'entityType' => 'governance_case',
                'entityId' => (string) $item->governance_case_id,
                'title' => $item->evidence_label ?: Str::headline(str_replace('_', ' ', $item->evidence_type)),
                'description' => $item->evidence_description,
                'actor' => $this->userSnapshot($item->creator),
                'context' => [
                    'evidenceId' => $item->id,
                    'evidenceType' => $item->evidence_type,
                    'evidenceReference' => $item->evidence_reference,
                    'metadata' => $item->metadata,
                ],
                'recordedAt' => $item->created_at?->toISOString(),
            ];
        })->all();

        return collect(array_merge($events, $evidence))
            ->sortByDesc(fn (array $row): string => (string) Arr::get($row, 'recordedAt', ''))
            ->values()
            ->all();
    }

    private function eventSnapshot(PreschoolGovernanceCaseEvent $event): array
    {
        return [
            'id' => $event->id,
            'eventType' => $event->event_type,
            'actorUserId' => $event->actor_user_id,
            'actorRole' => $event->actor_role,
            'previousStatus' => $event->previous_status,
            'newStatus' => $event->new_status,
            'note' => $event->note,
            'metadata' => $event->metadata ?? [],
            'actor' => $this->userSnapshot($event->actor),
            'recordedAt' => $event->created_at?->toISOString(),
        ];
    }

    private function evidenceSnapshot(PreschoolGovernanceCaseEvidence $item): array
    {
        return [
            'id' => $item->id,
            'evidenceType' => $item->evidence_type,
            'evidenceReference' => $item->evidence_reference,
            'evidenceLabel' => $item->evidence_label,
            'evidenceDescription' => $item->evidence_description,
            'metadata' => $item->metadata ?? [],
            'creator' => $this->userSnapshot($item->creator),
            'recordedAt' => $item->created_at?->toISOString(),
        ];
    }

    private function userSnapshot(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $fullName = trim($user->first_name.' '.$user->last_name);

        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'displayName' => $fullName !== '' ? $fullName : $user->username,
            'username' => $user->username,
            'email' => $user->email,
            'roleCode' => $user->role_code,
            'status' => $user->status,
            'raw' => $user,
        ];
    }

    private function academicYearSnapshot(?PreschoolAcademicYear $year): ?array
    {
        if (! $year) {
            return null;
        }

        return ['id' => $year->id, 'code' => $year->code, 'label' => $year->label, 'status' => $year->status, 'raw' => $year];
    }

    private function termSnapshot(?PreschoolAcademicTerm $term): ?array
    {
        if (! $term) {
            return null;
        }

        return ['id' => $term->id, 'code' => $term->code, 'name' => $term->name, 'label' => $term->label, 'status' => $term->status, 'raw' => $term];
    }

    private function reportPeriodSnapshot(?PreschoolReportPeriod $period): ?array
    {
        if (! $period) {
            return null;
        }

        return ['id' => $period->id, 'code' => $period->code, 'label' => $period->label, 'status' => $period->status, 'raw' => $period];
    }

    private function classSnapshot(?PreschoolClass $class): ?array
    {
        if (! $class) {
            return null;
        }

        return ['id' => $class->id, 'code' => $class->code, 'name' => $class->name, 'label' => $class->label ?? $class->name, 'status' => $class->status, 'raw' => $class];
    }

    private function studentSnapshot(?PreschoolStudent $student): ?array
    {
        if (! $student) {
            return null;
        }

        $fullName = trim($student->first_name.' '.$student->last_name);

        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'fullName' => $fullName !== '' ? $fullName : $student->name,
            'status' => $student->status,
            'raw' => $student,
        ];
    }

    private function relations(): array
    {
        return ['owner', 'reviewer', 'escalationOfficer', 'creator', 'resolver', 'closer', 'academicYear', 'term', 'reportPeriod', 'preschoolClass', 'student', 'events.actor', 'evidence.creator'];
    }

    private function normalizeSeverity(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::SEVERITIES, true) ? $value : 'medium';
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::STATUSES, true) ? $value : 'open';
    }

    private function normalizeSourceType(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::SOURCE_TYPES, true) ? $value : 'manual_review';
    }

    private function defaultRiskScore(string $severity): int
    {
        return match ($severity) {
            'critical' => 90,
            'high' => 75,
            'medium' => 50,
            'low' => 25,
            default => 50,
        };
    }

    private function generateCaseKey(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'GC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));

            if (! PreschoolGovernanceCase::query()->where('case_key', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'GC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
