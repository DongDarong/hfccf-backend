<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * Preschool academic lifecycle is the first-class source for year/term state.
 * Settings remain the configuration backbone, but lifecycle records are what
 * attendance, schedules, assessments, assignments, and reporting stamp.
 */
class PreschoolAcademicLifecycleService
{
    public function academicYears(): Collection
    {
        return PreschoolAcademicYear::query()
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    public function terms(?int $academicYearId = null): Collection
    {
        $query = PreschoolAcademicTerm::query()->with('academicYear');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->orderByDesc('id')
            ->get();
    }

    public function currentAcademicYear(): ?PreschoolAcademicYear
    {
        return PreschoolAcademicYear::query()
            ->where('is_current', true)
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->first()
            ?? PreschoolAcademicYear::query()->where('status', 'active')->orderByDesc('start_date')->first()
            ?? PreschoolAcademicYear::query()->orderByDesc('start_date')->first();
    }

    public function currentTerm(?int $academicYearId = null): ?PreschoolAcademicTerm
    {
        $query = PreschoolAcademicTerm::query()->with('academicYear')
            ->where('status', 'active');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->where('is_current', true)->orderBy('sort_order')->first()
            ?? $query->orderBy('sort_order')->orderBy('start_date')->first()
            ?? PreschoolAcademicTerm::query()->with('academicYear')->orderByDesc('start_date')->first();
    }

    public function currentContext(): array
    {
        $year = $this->currentAcademicYear();
        $term = $this->currentTerm($year?->id);
        $fallback = app(PreschoolSettingsBackboneService::class)->currentAcademicContext();
        $reportPeriod = app(PreschoolReportPeriodService::class)->currentContext();

        if (! $year) {
            return array_merge($fallback, $reportPeriod);
        }

        $yearName = $this->resolveYearName($year);
        $termName = $term ? $this->resolveTermName($term) : ($fallback['term_label'] ?? '');

        return [
            'academic_year_id' => $year->id,
            'academic_year' => $yearName,
            'academic_year_name' => $yearName,
            'academic_year_label' => $year->label,
            'academic_year_date_range' => $this->formatDateRange($year->start_date, $year->end_date),
            'academic_year_status' => $year->status,
            'term_id' => $term?->id,
            'term_label' => $termName,
            'term_name' => $termName,
            'term_date_range' => $term ? $this->formatDateRange($term->start_date, $term->end_date) : '',
            'term_status' => $term?->status,
        ] + $reportPeriod;
    }

    public function resolveForDate(mixed $date): array
    {
        $date = $this->normalizeDate($date);
        if (! $date) {
            return $this->currentContext();
        }

        $year = PreschoolAcademicYear::query()
            ->whereDate('start_date', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date);
            })
            ->where('status', '!=', 'archived')
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->first() ?? $this->currentAcademicYear();

        $term = null;

        if ($year) {
            $term = PreschoolAcademicTerm::query()
                ->with('academicYear')
                ->where('academic_year_id', $year->id)
                ->whereDate('start_date', '<=', $date)
                ->where(function (Builder $query) use ($date): void {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $date);
                })
                ->where('status', '!=', 'archived')
                ->orderByDesc('is_current')
                ->orderBy('sort_order')
                ->first();
        }

        if (! $term) {
            $term = $this->currentTerm($year?->id);
        }

        $reportPeriod = app(PreschoolReportPeriodService::class)->resolveForDate($date);

        $yearName = $year ? $this->resolveYearName($year) : $this->currentContext()['academic_year'];
        $termName = $term ? $this->resolveTermName($term) : ($this->currentContext()['term_label'] ?? '');

        return [
            'academic_year_id' => $year?->id,
            'academic_year' => $yearName,
            'academic_year_name' => $yearName,
            'academic_year_label' => $year?->label,
            'academic_year_date_range' => $year ? $this->formatDateRange($year->start_date, $year->end_date) : '',
            'academic_year_status' => $year?->status,
            'term_id' => $term?->id,
            'term_label' => $termName,
            'term_name' => $termName,
            'term_date_range' => $term ? $this->formatDateRange($term->start_date, $term->end_date) : '',
            'term_status' => $term?->status,
        ] + $reportPeriod;
    }

    public function createAcademicYear(array $data): PreschoolAcademicYear
    {
        $this->assertAcademicYearDates($data['start_date'] ?? null, $data['end_date'] ?? null);

        return DB::transaction(function () use ($data): PreschoolAcademicYear {
            $academicYear = new PreschoolAcademicYear([
                'code' => $this->nullableCode($data['code'] ?? null, $this->generateYearCode($data)),
                'label' => $this->normalizeYearName($data),
                'description' => $this->nullableDescription($data['description'] ?? $data['notes'] ?? null),
                'start_date' => $this->normalizeDate($data['start_date'] ?? null),
                'end_date' => $this->normalizeDate($data['end_date'] ?? null),
                'status' => $this->normalizeAcademicStatus($data['status'] ?? 'active'),
                'is_current' => (bool) ($data['is_current'] ?? $this->normalizeAcademicStatus($data['status'] ?? 'active') === 'active'),
                'notes' => $this->nullableDescription($data['notes'] ?? $data['description'] ?? null),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $academicYear->save();

            if ($academicYear->is_current && $academicYear->status === 'active') {
                $this->markAcademicYearCurrent($academicYear);
                $this->markTermsOutsideYearInactive($academicYear->id);
            }

            return $academicYear->refresh()->loadMissing('terms');
        });
    }

    public function updateAcademicYear(PreschoolAcademicYear $academicYear, array $data): PreschoolAcademicYear
    {
        $this->ensureAcademicYearEditable($academicYear);
        $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $academicYear->start_date;
        $endDate = array_key_exists('end_date', $data) ? $data['end_date'] : $academicYear->end_date;
        $this->assertAcademicYearDates($startDate, $endDate);

        return DB::transaction(function () use ($academicYear, $data): PreschoolAcademicYear {
            foreach (['code', 'name', 'label', 'description', 'notes', 'start_date', 'end_date', 'status'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                if (in_array($field, ['name', 'label'], true)) {
                    $academicYear->label = $this->normalizeYearName($data);
                    continue;
                }

                $academicYear->{$field} = match ($field) {
                    'code' => $this->nullableCode($data[$field] ?? null, $this->generateYearCode($data, $academicYear)),
                    'description', 'notes' => $this->nullableDescription($data[$field] ?? null),
                    'start_date', 'end_date' => $this->normalizeDate($data[$field] ?? null),
                    'status' => $this->normalizeAcademicStatus($data[$field]),
                };
            }

            if (array_key_exists('is_current', $data)) {
                $academicYear->is_current = (bool) $data['is_current'];
            }

            $academicYear->updated_by = auth()->id();
            $academicYear->save();

            if ($academicYear->is_current && $academicYear->status === 'active') {
                $this->markAcademicYearCurrent($academicYear);
                $this->markTermsOutsideYearInactive($academicYear->id);
            }

            return $academicYear->refresh()->loadMissing('terms');
        });
    }

    public function activateAcademicYear(PreschoolAcademicYear $academicYear): PreschoolAcademicYear
    {
        return DB::transaction(function () use ($academicYear): PreschoolAcademicYear {
            $academicYear->status = 'active';
            $academicYear->is_current = true;
            $academicYear->updated_by = auth()->id();
            $academicYear->save();

            $this->markAcademicYearCurrent($academicYear);
            $this->markTermsOutsideYearInactive($academicYear->id);

            return $academicYear->refresh()->loadMissing('terms');
        });
    }

    public function closeAcademicYear(PreschoolAcademicYear $academicYear): PreschoolAcademicYear
    {
        return DB::transaction(function () use ($academicYear): PreschoolAcademicYear {
            $academicYear->status = 'closed';
            $academicYear->is_current = false;
            $academicYear->updated_by = auth()->id();
            $academicYear->save();

            $this->markTermsInactiveInYear($academicYear->id);

            if ($this->currentAcademicYear()?->id === $academicYear->id) {
                $this->markFallbackCurrentAcademicYear();
            }

            return $academicYear->refresh()->loadMissing('terms');
        });
    }

    public function archiveAcademicYear(PreschoolAcademicYear $academicYear): PreschoolAcademicYear
    {
        return DB::transaction(function () use ($academicYear): PreschoolAcademicYear {
            $academicYear->status = 'archived';
            $academicYear->is_current = false;
            $academicYear->updated_by = auth()->id();
            $academicYear->deleted_at = now();
            $academicYear->save();

            $this->markTermsInactiveInYear($academicYear->id);

            if ($this->currentAcademicYear()?->id === $academicYear->id) {
                $this->markFallbackCurrentAcademicYear();
            }

            return $academicYear->refresh()->loadMissing('terms');
        });
    }

    public function createTerm(array $data): PreschoolAcademicTerm
    {
        $this->assertAcademicYearBounds($data['academic_year_id'] ?? null, $data['start_date'] ?? null, $data['end_date'] ?? null);

        return DB::transaction(function () use ($data): PreschoolAcademicTerm {
            $term = new PreschoolAcademicTerm([
                'academic_year_id' => (int) $data['academic_year_id'],
                'code' => $this->nullableCode($data['code'] ?? null, $this->generateTermCode($data)),
                'name' => $this->normalizeTermName($data),
                'description' => $this->nullableDescription($data['description'] ?? $data['notes'] ?? null),
                'start_date' => $this->normalizeDate($data['start_date'] ?? null),
                'end_date' => $this->normalizeDate($data['end_date'] ?? null),
                'status' => $this->normalizeAcademicStatus($data['status'] ?? 'active'),
                'is_current' => (bool) ($data['is_current'] ?? $this->normalizeAcademicStatus($data['status'] ?? 'active') === 'active'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'notes' => $this->nullableDescription($data['notes'] ?? $data['description'] ?? null),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $term->save();

            if ($term->is_current && $term->status === 'active') {
                $this->markTermCurrent($term);
            }

            return $term->refresh()->load('academicYear');
        });
    }

    public function updateTerm(PreschoolAcademicTerm $term, array $data): PreschoolAcademicTerm
    {
        $this->ensureTermEditable($term);
        $academicYearId = array_key_exists('academic_year_id', $data) ? $data['academic_year_id'] : $term->academic_year_id;
        $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $term->start_date;
        $endDate = array_key_exists('end_date', $data) ? $data['end_date'] : $term->end_date;
        $this->assertAcademicYearBounds($academicYearId, $startDate, $endDate);

        return DB::transaction(function () use ($term, $data): PreschoolAcademicTerm {
            foreach (['academic_year_id', 'code', 'name', 'description', 'notes', 'status'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $term->{$field} = match ($field) {
                    'academic_year_id' => (int) $data[$field],
                    'code' => $this->nullableCode($data[$field] ?? null, $this->generateTermCode($data, $term)),
                    'name' => $this->normalizeTermName($data),
                    'description', 'notes' => $this->nullableDescription($data[$field] ?? null),
                    'status' => $this->normalizeAcademicStatus($data[$field]),
                };
            }

            foreach (['start_date', 'end_date', 'sort_order'] as $field) {
                if (array_key_exists($field, $data)) {
                    $term->{$field} = $field === 'sort_order'
                        ? (int) $data[$field]
                        : $this->normalizeDate($data[$field] ?? null);
                }
            }

            if (array_key_exists('is_current', $data)) {
                $term->is_current = (bool) $data['is_current'];
            }

            $term->updated_by = auth()->id();
            $term->save();

            if ($term->is_current && $term->status === 'active') {
                $this->markTermCurrent($term);
            }

            return $term->refresh()->load('academicYear');
        });
    }

    public function activateTerm(PreschoolAcademicTerm $term): PreschoolAcademicTerm
    {
        return DB::transaction(function () use ($term): PreschoolAcademicTerm {
            $academicYear = PreschoolAcademicYear::query()->find($term->academic_year_id);
            if (! $academicYear || $academicYear->status !== 'active') {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'The selected academic year must be active before a term can be activated.',
                ]);
            }

            $term->status = 'active';
            $term->is_current = true;
            $term->updated_by = auth()->id();
            $term->save();

            $this->markTermCurrent($term);
            $this->markAcademicYearCurrent($academicYear);
            $this->markTermsOutsideYearInactive($academicYear->id);

            return $term->refresh()->load('academicYear');
        });
    }

    public function closeTerm(PreschoolAcademicTerm $term): PreschoolAcademicTerm
    {
        return DB::transaction(function () use ($term): PreschoolAcademicTerm {
            $term->status = 'closed';
            $term->is_current = false;
            $term->updated_by = auth()->id();
            $term->save();

            if ($this->currentTerm((int) $term->academic_year_id)?->id === $term->id) {
                $this->markFallbackCurrentTerm((int) $term->academic_year_id);
            }

            return $term->refresh()->load('academicYear');
        });
    }

    public function archiveTerm(PreschoolAcademicTerm $term): PreschoolAcademicTerm
    {
        return DB::transaction(function () use ($term): PreschoolAcademicTerm {
            $term->status = 'archived';
            $term->is_current = false;
            $term->updated_by = auth()->id();
            $term->deleted_at = now();
            $term->save();

            if ($this->currentTerm((int) $term->academic_year_id)?->id === $term->id) {
                $this->markFallbackCurrentTerm((int) $term->academic_year_id);
            }

            return $term->refresh()->load('academicYear');
        });
    }

    public function academicYearSnapshot(PreschoolAcademicYear $year): array
    {
        $name = $this->resolveYearName($year);

        return [
            'id' => $year->id,
            'code' => $year->code,
            'name' => $name,
            'label' => $year->label,
            'description' => $year->description ?? $year->notes,
            'startDate' => $year->start_date?->toDateString(),
            'endDate' => $year->end_date?->toDateString(),
            'dateRange' => $this->formatDateRange($year->start_date, $year->end_date),
            'status' => $year->status,
            'isCurrent' => (bool) $year->is_current,
            'isActive' => (bool) $year->is_current && $year->status === 'active',
            'notes' => $year->notes,
            'createdBy' => $year->created_by,
            'updatedBy' => $year->updated_by,
            'termsCount' => $year->relationLoaded('terms') ? $year->terms->count() : null,
            'createdAt' => $year->created_at?->toISOString(),
            'updatedAt' => $year->updated_at?->toISOString(),
        ];
    }

    public function termSnapshot(PreschoolAcademicTerm $term): array
    {
        $name = $this->resolveTermName($term);

        return [
            'id' => $term->id,
            'academicYearId' => $term->academic_year_id,
            'academicYearCode' => $term->academicYear?->code,
            'academicYearLabel' => $term->academicYear?->label,
            'academicYearName' => $term->academicYear ? $this->resolveYearName($term->academicYear) : null,
            'code' => $term->code,
            'name' => $name,
            'description' => $term->description ?? $term->notes,
            'startDate' => $term->start_date?->toDateString(),
            'endDate' => $term->end_date?->toDateString(),
            'dateRange' => $this->formatDateRange($term->start_date, $term->end_date),
            'status' => $term->status,
            'isCurrent' => (bool) $term->is_current,
            'isActive' => (bool) $term->is_current && $term->status === 'active',
            'sortOrder' => (int) $term->sort_order,
            'notes' => $term->notes,
            'createdBy' => $term->created_by,
            'updatedBy' => $term->updated_by,
            'createdAt' => $term->created_at?->toISOString(),
            'updatedAt' => $term->updated_at?->toISOString(),
        ];
    }

    private function markAcademicYearCurrent(PreschoolAcademicYear $academicYear): void
    {
        PreschoolAcademicYear::query()
            ->where('id', '!=', $academicYear->id)
            ->update(['is_current' => false]);
    }

    private function markFallbackCurrentAcademicYear(): void
    {
        $fallback = PreschoolAcademicYear::query()
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($fallback) {
            $fallback->is_current = true;
            $fallback->save();
        }
    }

    private function markTermCurrent(PreschoolAcademicTerm $term): void
    {
        PreschoolAcademicTerm::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('id', '!=', $term->id)
            ->update(['is_current' => false]);
    }

    private function markTermsOutsideYearInactive(int $academicYearId): void
    {
        PreschoolAcademicTerm::query()
            ->where('academic_year_id', '!=', $academicYearId)
            ->update(['is_current' => false]);
    }

    private function markTermsInactiveInYear(int $academicYearId): void
    {
        PreschoolAcademicTerm::query()
            ->where('academic_year_id', $academicYearId)
            ->update(['is_current' => false]);
    }

    private function markFallbackCurrentTerm(int $academicYearId): void
    {
        $fallback = PreschoolAcademicTerm::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($fallback) {
            $fallback->is_current = true;
            $fallback->save();
        }
    }

    private function assertAcademicYearBounds(mixed $academicYearId, mixed $startDate, mixed $endDate): void
    {
        if ($academicYearId === null || $academicYearId === '') {
            return;
        }

        $academicYear = PreschoolAcademicYear::query()->find($academicYearId);
        if (! $academicYear) {
            return;
        }

        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if ($start && $academicYear->start_date && Carbon::parse($start)->lt($academicYear->start_date)) {
            throw ValidationException::withMessages([
                'start_date' => 'Term start date must fall within the selected academic year.',
            ]);
        }

        if ($end && $academicYear->end_date && Carbon::parse($end)->gt($academicYear->end_date)) {
            throw ValidationException::withMessages([
                'end_date' => 'Term end date must fall within the selected academic year.',
            ]);
        }
    }

    private function ensureAcademicYearEditable(PreschoolAcademicYear $academicYear): void
    {
        if (in_array($academicYear->status, ['closed', 'archived'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Closed or archived academic years cannot be edited.',
            ]);
        }
    }

    private function ensureTermEditable(PreschoolAcademicTerm $term): void
    {
        if (in_array($term->status, ['closed', 'archived'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Closed or archived terms cannot be edited.',
            ]);
        }
    }

    private function assertAcademicYearDates(mixed $startDate, mixed $endDate): void
    {
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if ($start && $end && Carbon::parse($start)->gte(Carbon::parse($end))) {
            throw ValidationException::withMessages([
                'end_date' => 'Academic year end date must be after the start date.',
            ]);
        }
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableDescription(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableCode(mixed $value, ?string $fallback = null): ?string
    {
        $value = trim((string) $value);

        if ($value !== '') {
            return $value;
        }

        $fallback = trim((string) $fallback);

        return $fallback === '' ? null : $fallback;
    }

    private function generateYearCode(array $data, ?PreschoolAcademicYear $academicYear = null): string
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        $name = trim((string) ($data['name'] ?? $data['label'] ?? $academicYear?->label ?? ''));
        if ($name !== '') {
            return Str::upper(Str::slug($name, '-'));
        }

        return 'ACADEMIC-YEAR-'.now()->format('YmdHis');
    }

    private function generateTermCode(array $data, ?PreschoolAcademicTerm $term = null): string
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        $name = trim((string) ($data['name'] ?? $data['label'] ?? $term?->name ?? ''));
        if ($name !== '') {
            return Str::upper(Str::slug($name, '-'));
        }

        return 'TERM-'.now()->format('YmdHis');
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeAcademicStatus(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['active', 'closed', 'archived'], true) ? $value : 'active';
    }

    private function resolveYearName(PreschoolAcademicYear $year): string
    {
        return trim((string) ($year->label ?: $year->code)) !== ''
            ? trim((string) ($year->label ?: $year->code))
            : 'Academic Year';
    }

    private function resolveTermName(PreschoolAcademicTerm $term): string
    {
        return trim((string) ($term->name ?: $term->code)) !== ''
            ? trim((string) ($term->name ?: $term->code))
            : 'Term';
    }

    private function normalizeYearName(array $data): string
    {
        return trim((string) ($data['name'] ?? $data['label'] ?? ''));
    }

    private function normalizeTermName(array $data): string
    {
        return trim((string) ($data['name'] ?? $data['label'] ?? ''));
    }

    private function formatDateRange(mixed $startDate, mixed $endDate): string
    {
        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);

        if (! $start && ! $end) {
            return '';
        }

        return trim(($start ?: '—').' - '.($end ?: '—'));
    }
}
