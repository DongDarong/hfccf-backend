<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolStudentAssessment;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Preschool report periods are persisted so report freezes, lifecycle states,
 * and audit history stay stable even though the underlying reporting content
 * still derives from finalized assessment data.
 */
class PreschoolReportPeriodService
{
    public function __construct(
        private readonly PreschoolAssessmentAggregationService $aggregation,
        private readonly PreschoolLifecycleAuditService $audit,
    ) {}

    public function reportPeriods(User $user, ?PreschoolStudent $student = null, ?PreschoolClass $class = null, array $filters = []): Collection
    {
        $derived = $this->aggregation->reportPeriods($user, $student, $class);
        $this->syncDerivedPeriods($derived);

        if (($student !== null || $class !== null) && $derived->isEmpty()) {
            return collect();
        }

        $periodType = $this->nullableText($filters['period_type'] ?? null);
        $academicYearId = $this->nullableInt($filters['academic_year_id'] ?? null);
        $termId = $this->nullableInt($filters['term_id'] ?? null);

        $derivedKeys = $derived
            ->map(fn (array $row): string => $this->periodKey(
                $this->rowLabel($row),
                $row['periodType'] ?? null,
                $row['academicYearId'] ?? null,
                $row['termId'] ?? null,
            ))
            ->all();

        $records = PreschoolReportPeriod::query()
            ->with(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy'])
            ->when($periodType, fn (Builder $query, string $type) => $query->where('period_type', $type))
            ->when($academicYearId !== null, fn (Builder $query) => $query->where('academic_year_id', $academicYearId))
            ->when($termId !== null, fn (Builder $query) => $query->where('term_id', $termId))
            ->when(($student !== null || $class !== null) && $derivedKeys !== [], function (Builder $query) use ($derived): void {
                $query->where(function (Builder $builder) use ($derived): void {
                    foreach ($derived as $row) {
                        $builder->orWhere(function (Builder $inner) use ($row): void {
                            $inner->where('period_label', $this->rowLabel($row))
                                ->where('period_type', $row['periodType'] ?? 'term')
                                ->where('academic_year_id', $row['academicYearId'] ?? null)
                                ->where('term_id', $row['termId'] ?? null);
                        });
                    }
                });
            })
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->get();

        $periods = $records->map(fn (PreschoolReportPeriod $period) => $this->snapshot($period))->keyBy(fn (array $row) => $this->periodKey($this->rowLabel($row), $row['periodType'] ?? null, $row['academicYearId'] ?? null, $row['termId'] ?? null));

        if ($derived->isEmpty()) {
            return $records->map(fn (PreschoolReportPeriod $period) => $this->snapshot($period))->values();
        }

        $combined = $derived
            ->map(function (array $row) use ($periods): array {
                $key = $this->periodKey($this->rowLabel($row), $row['periodType'] ?? null, $row['academicYearId'] ?? null, $row['termId'] ?? null);
                $existing = $periods->get($key);

                if ($existing) {
                    return array_merge($row, $existing);
                }

                return $row + [
                    'id' => null,
                    'reportPeriodId' => null,
                    'periodType' => $row['periodType'] ?? 'term',
                    'status' => 'finalized',
                    'isDraft' => false,
                    'isActive' => false,
                    'isFinalized' => true,
                    'isLocked' => false,
                    'isArchived' => false,
                ];
            })
            ->values();

        $derivedKeys = $combined->map(fn (array $row) => $this->periodKey($this->rowLabel($row), $row['periodType'] ?? null, $row['academicYearId'] ?? null, $row['termId'] ?? null))->all();
        $manualPeriods = $records
            ->filter(fn (PreschoolReportPeriod $period) => ! in_array($this->periodKey($period->period_label, $period->period_type, $period->academic_year_id, $period->term_id), $derivedKeys, true))
            ->map(fn (PreschoolReportPeriod $period) => $this->snapshot($period));

        return $combined->concat($manualPeriods)->values();
    }

    public function currentContext(): array
    {
        $period = PreschoolReportPeriod::query()
            ->whereIn('status', ['active', 'finalized', 'locked'])
            ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();

        if (! $period) {
            $period = PreschoolReportPeriod::query()
                ->where('status', 'draft')
                ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
                ->orderByDesc('id')
                ->first();
        }

        return $period
            ? $this->contextSnapshot($period)
            : [
                'report_period_id' => null,
                'report_period_label' => '',
                'report_period_status' => '',
                'report_period_type' => '',
                'report_period_locked_at' => null,
                'report_period_finalized_at' => null,
                'report_period_archived_at' => null,
            ];
    }

    public function resolveForDate(mixed $date): array
    {
        $normalizedDate = $this->normalizeDate($date);
        if (! $normalizedDate) {
            return $this->currentContext();
        }

        $period = PreschoolReportPeriod::query()
            ->with(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy'])
            ->whereDate('from_date', '<=', $normalizedDate)
            ->where(function (Builder $query) use ($normalizedDate): void {
                $query->whereNull('to_date')
                    ->orWhereDate('to_date', '>=', $normalizedDate);
            })
            ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();

        return $period ? $this->contextSnapshot($period) : $this->currentContext();
    }

    public function findByContext(string $periodLabel, mixed $periodType = null, mixed $academicYearId = null, mixed $termId = null): ?PreschoolReportPeriod
    {
        $label = trim($periodLabel);
        if ($label === '') {
            return null;
        }

        $query = PreschoolReportPeriod::query()
            ->where('period_label', $label)
            ->when($this->nullableText($periodType), fn (Builder $builder, string $type) => $builder->where('period_type', $type))
            ->when($academicYearId !== null && $academicYearId !== '', fn (Builder $builder) => $builder->where('academic_year_id', $this->nullableInt($academicYearId)))
            ->when($termId !== null && $termId !== '', fn (Builder $builder) => $builder->where('term_id', $this->nullableInt($termId)));

        return $query
            ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();
    }

    public function resolveForAssessment(array $payload = [], ?string $assessmentDate = null): ?PreschoolReportPeriod
    {
        $periodLabel = trim((string) ($payload['period_label'] ?? ''));
        if ($periodLabel === '') {
            return null;
        }

        $academicContext = app(PreschoolAcademicLifecycleService::class)->resolveForDate($assessmentDate ?: ($payload['assessment_date'] ?? null));
        $academicYearId = $academicContext['academic_year_id'] ?? null;
        $termId = $academicContext['term_id'] ?? null;
        $periodType = $this->nullableText($payload['period_type'] ?? null);
        $reportPeriodId = $this->nullableInt($payload['report_period_id'] ?? null);
        $normalizedAssessmentDate = $this->normalizeDate($assessmentDate ?: ($payload['assessment_date'] ?? null));

        if ($reportPeriodId !== null) {
            $matched = PreschoolReportPeriod::query()->find($reportPeriodId);
            if ($matched) {
                return $matched;
            }
        }

        $query = PreschoolReportPeriod::query()
            ->where('period_label', $periodLabel)
            ->where('academic_year_id', $academicYearId);

        if ($periodType) {
            $query->where('period_type', $periodType);
        }

        if ($normalizedAssessmentDate) {
            $query->whereDate('from_date', '<=', $normalizedAssessmentDate)
                ->where(function (Builder $builder) use ($normalizedAssessmentDate): void {
                    $builder->whereNull('to_date')
                        ->orWhereDate('to_date', '>=', $normalizedAssessmentDate);
                });
        }

        $period = $query
            ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();

        if ($period) {
            return $period;
        }

        $termQuery = PreschoolReportPeriod::query()
            ->where('period_label', $periodLabel)
            ->where('academic_year_id', $academicYearId)
            ->whereNotNull('term_id')
            ->where('term_id', $termId);

        if ($periodType) {
            $termQuery->where('period_type', $periodType);
        }

        $period = $termQuery
            ->orderByRaw("case period_type when 'monthly' then 1 when 'term' then 2 when 'annual' then 3 else 4 end")
            ->orderByDesc('to_date')
            ->orderByDesc('id')
            ->first();

        if ($period) {
            return $period;
        }

        $derived = PreschoolStudentAssessment::query()
            ->where('period_label', $periodLabel)
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->when($assessmentDate, static function (Builder $query, string $date): void {
                $query->whereDate('assessment_date', '<=', $date);
            })
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->first();

        if (! $derived) {
            return null;
        }

        return PreschoolReportPeriod::query()->create([
            'period_label' => $periodLabel,
            'period_type' => $periodType ?: 'term',
            'academic_year_id' => $academicYearId,
            'term_id' => $termId,
            'from_date' => $derived->assessment_date?->toDateString(),
            'to_date' => $derived->assessment_date?->toDateString(),
            'status' => 'finalized',
        ]);
    }

    public function create(array $data): PreschoolReportPeriod
    {
        $period = new PreschoolReportPeriod([
            'period_label' => trim((string) ($data['period_label'] ?? '')),
            'period_type' => $this->normalizePeriodType($data['period_type'] ?? null),
            'academic_year_id' => $this->nullableInt($data['academic_year_id'] ?? null),
            'term_id' => $this->nullableInt($data['term_id'] ?? null),
            'from_date' => $this->normalizeDate($data['from_date'] ?? null),
            'to_date' => $this->normalizeDate($data['to_date'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? 'draft'),
            'notes' => $this->nullableText($data['notes'] ?? null),
        ]);

        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function update(PreschoolReportPeriod $period, array $data): PreschoolReportPeriod
    {
        foreach (['period_label', 'period_type', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $period->{$field} = $field === 'status'
                    ? $this->normalizeStatus($data[$field] ?? null)
                    : ($field === 'notes' ? $this->nullableText($data[$field] ?? null) : ($field === 'period_type' ? $this->normalizePeriodType($data[$field] ?? null) : trim((string) $data[$field])));
            }
        }

        foreach (['academic_year_id', 'term_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $period->{$field} = $this->nullableInt($data[$field] ?? null);
            }
        }

        foreach (['from_date', 'to_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $period->{$field} = $this->normalizeDate($data[$field] ?? null);
            }
        }

        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function activate(PreschoolReportPeriod $period): PreschoolReportPeriod
    {
        $period->status = 'active';
        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function finalize(PreschoolReportPeriod $period, ?User $actor = null): PreschoolReportPeriod
    {
        $period->status = 'finalized';
        $period->finalized_at = now();
        $period->finalized_by = $actor?->id;
        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function lock(PreschoolReportPeriod $period, ?User $actor = null): PreschoolReportPeriod
    {
        $period->status = 'locked';
        $period->locked_at = now();
        $period->locked_by = $actor?->id;
        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function archive(PreschoolReportPeriod $period, ?User $actor = null): PreschoolReportPeriod
    {
        $period->status = 'archived';
        $period->archived_at = now();
        $period->archived_by = $actor?->id;
        $period->save();

        return $period->refresh()->load(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy']);
    }

    public function snapshot(PreschoolReportPeriod $period): array
    {
        return array_merge($this->contextSnapshot($period), [
            'id' => $period->id,
            'label' => $period->period_label,
            'periodLabel' => $period->period_label,
            'periodType' => $period->period_type,
            'reportPeriodId' => $period->id,
            'fromDate' => $period->from_date?->toDateString(),
            'toDate' => $period->to_date?->toDateString(),
            'summarySnapshot' => $period->summary_snapshot,
            'reportSnapshot' => $period->report_snapshot,
            'notes' => $period->notes,
            'createdAt' => $period->created_at?->toISOString(),
            'updatedAt' => $period->updated_at?->toISOString(),
            'lockedByUserId' => $period->locked_by,
            'lockedByName' => trim(($period->lockedBy?->first_name ?? '').' '.($period->lockedBy?->last_name ?? '')),
            'finalizedByUserId' => $period->finalized_by,
            'finalizedByName' => trim(($period->finalizedBy?->first_name ?? '').' '.($period->finalizedBy?->last_name ?? '')),
            'archivedByUserId' => $period->archived_by,
            'archivedByName' => trim(($period->archivedBy?->first_name ?? '').' '.($period->archivedBy?->last_name ?? '')),
            'isDraft' => $period->status === 'draft',
            'isActive' => $period->status === 'active',
            'isFinalized' => $period->status === 'finalized',
            'isLocked' => $period->status === 'locked',
            'isArchived' => $period->status === 'archived',
        ]);
    }

    public function contextSnapshot(PreschoolReportPeriod $period): array
    {
        return [
            'report_period_id' => $period->id,
            'report_period_label' => $period->period_label,
            'report_period_type' => $period->period_type,
            'report_period_status' => $period->status,
            'report_period_locked_at' => $period->locked_at?->toISOString(),
            'report_period_finalized_at' => $period->finalized_at?->toISOString(),
            'report_period_archived_at' => $period->archived_at?->toISOString(),
            'academic_year_id' => $period->academic_year_id,
            'term_id' => $period->term_id,
            'reportPeriodId' => $period->id,
        ];
    }

    public function recordAudit(array $data): void
    {
        $this->audit->recordSafely($data);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $derived
     */
    private function syncDerivedPeriods(Collection $derived): void
    {
        foreach ($derived as $row) {
            $key = $this->periodKey($this->rowLabel($row), $row['periodType'] ?? null, $row['academicYearId'] ?? null, $row['termId'] ?? null);
            if ($key === '|||') {
                continue;
            }

            $period = PreschoolReportPeriod::query()->firstOrNew([
                'period_label' => $this->rowLabel($row),
                'period_type' => $this->normalizePeriodType($row['periodType'] ?? null),
                'academic_year_id' => $row['academicYearId'] ?? null,
                'term_id' => $row['termId'] ?? null,
            ]);

            if (! $period->exists) {
                $period->status = 'finalized';
            }

            $period->from_date = $this->normalizeDate($row['fromDate'] ?? null);
            $period->to_date = $this->normalizeDate($row['toDate'] ?? null);
            $period->save();
        }
    }

    private function periodKey(mixed $label, mixed $periodType, mixed $academicYearId, mixed $termId): string
    {
        return trim((string) $label).'|'.trim((string) $periodType).'|'.((string) $academicYearId).'|'.((string) $termId);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(array $row): string
    {
        return trim((string) ($row['label'] ?? $row['periodLabel'] ?? $row['period_label'] ?? ''));
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['draft', 'active', 'finalized', 'locked', 'archived'], true) ? $value : 'draft';
    }

    private function normalizePeriodType(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['monthly', 'term', 'annual'], true) ? $value : 'term';
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
