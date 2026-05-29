<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

        return [
            'academic_year_id' => $year->id,
            'academic_year' => trim((string) $year->label) !== '' ? $year->label : ($year->code ?: 'Current Academic Year'),
            'academic_year_status' => $year->status,
            'term_id' => $term?->id,
            'term_label' => $term ? trim((string) ($term->name ?: $term->code)) : ($fallback['term_label'] ?? ''),
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

        return [
            'academic_year_id' => $year?->id,
            'academic_year' => $year ? trim((string) ($year->label ?: $year->code)) : $this->currentContext()['academic_year'],
            'academic_year_status' => $year?->status,
            'term_id' => $term?->id,
            'term_label' => $term ? trim((string) ($term->name ?: $term->code)) : ($this->currentContext()['term_label'] ?? ''),
            'term_status' => $term?->status,
        ] + $reportPeriod;
    }

    public function createAcademicYear(array $data): PreschoolAcademicYear
    {
        $academicYear = new PreschoolAcademicYear([
            'code' => $this->normalizeCode($data['code'] ?? ''),
            'label' => $this->normalizeText($data['label'] ?? ''),
            'start_date' => $this->normalizeDate($data['start_date'] ?? null),
            'end_date' => $this->normalizeDate($data['end_date'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? 'active'),
            'is_current' => (bool) ($data['is_current'] ?? false),
            'notes' => $this->nullableText($data['notes'] ?? null),
        ]);

        $academicYear->save();

        if ($academicYear->is_current) {
            $this->markAcademicYearCurrent($academicYear);
        }

        return $academicYear->refresh();
    }

    public function updateAcademicYear(PreschoolAcademicYear $academicYear, array $data): PreschoolAcademicYear
    {
        foreach (['code', 'label', 'start_date', 'end_date', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $academicYear->{$field} = in_array($field, ['start_date', 'end_date'], true)
                    ? $this->normalizeDate($data[$field] ?? null)
                    : ($field === 'notes' ? $this->nullableText($data[$field] ?? null) : ($field === 'status' ? $this->normalizeStatus($data[$field]) : $this->normalizeText($data[$field])));
            }
        }

        if (array_key_exists('is_current', $data)) {
            $academicYear->is_current = (bool) $data['is_current'];
        }

        $academicYear->save();

        if ($academicYear->is_current) {
            $this->markAcademicYearCurrent($academicYear);
        }

        return $academicYear->refresh();
    }

    public function activateAcademicYear(PreschoolAcademicYear $academicYear): PreschoolAcademicYear
    {
        $academicYear->status = 'active';
        $academicYear->is_current = true;
        $academicYear->save();

        $this->markAcademicYearCurrent($academicYear);

        return $academicYear->refresh();
    }

    public function closeAcademicYear(PreschoolAcademicYear $academicYear): PreschoolAcademicYear
    {
        $academicYear->status = 'closed';
        $academicYear->is_current = false;
        $academicYear->save();

        if ($this->currentAcademicYear()?->id === $academicYear->id) {
            $this->markFallbackCurrentAcademicYear();
        }

        return $academicYear->refresh();
    }

    public function createTerm(array $data): PreschoolAcademicTerm
    {
        $this->assertAcademicYearBounds($data['academic_year_id'] ?? null, $data['start_date'] ?? null, $data['end_date'] ?? null);

        $term = new PreschoolAcademicTerm([
            'academic_year_id' => (int) $data['academic_year_id'],
            'code' => $this->normalizeCode($data['code'] ?? ''),
            'name' => $this->normalizeText($data['name'] ?? ''),
            'start_date' => $this->normalizeDate($data['start_date'] ?? null),
            'end_date' => $this->normalizeDate($data['end_date'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? 'active'),
            'is_current' => (bool) ($data['is_current'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'notes' => $this->nullableText($data['notes'] ?? null),
        ]);

        $term->save();

        if ($term->is_current) {
            $this->markTermCurrent($term);
        }

        return $term->refresh()->load('academicYear');
    }

    public function updateTerm(PreschoolAcademicTerm $term, array $data): PreschoolAcademicTerm
    {
        $academicYearId = array_key_exists('academic_year_id', $data) ? $data['academic_year_id'] : $term->academic_year_id;
        $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $term->start_date;
        $endDate = array_key_exists('end_date', $data) ? $data['end_date'] : $term->end_date;
        $this->assertAcademicYearBounds($academicYearId, $startDate, $endDate);

        foreach (['academic_year_id', 'code', 'name', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $term->{$field} = $field === 'academic_year_id'
                    ? (int) $data[$field]
                    : ($field === 'notes' ? $this->nullableText($data[$field] ?? null) : ($field === 'status' ? $this->normalizeStatus($data[$field]) : $this->normalizeText($data[$field])));
            }
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

        $term->save();

        if ($term->is_current) {
            $this->markTermCurrent($term);
        }

        return $term->refresh()->load('academicYear');
    }

    public function activateTerm(PreschoolAcademicTerm $term): PreschoolAcademicTerm
    {
        $term->status = 'active';
        $term->is_current = true;
        $term->save();

        $this->markTermCurrent($term);

        return $term->refresh()->load('academicYear');
    }

    public function closeTerm(PreschoolAcademicTerm $term): PreschoolAcademicTerm
    {
        $term->status = 'closed';
        $term->is_current = false;
        $term->save();

        if ($this->currentTerm((int) $term->academic_year_id)?->id === $term->id) {
            $this->markFallbackCurrentTerm((int) $term->academic_year_id);
        }

        return $term->refresh()->load('academicYear');
    }

    public function academicYearSnapshot(PreschoolAcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'code' => $year->code,
            'label' => $year->label,
            'startDate' => $year->start_date?->toDateString(),
            'endDate' => $year->end_date?->toDateString(),
            'status' => $year->status,
            'isCurrent' => (bool) $year->is_current,
            'notes' => $year->notes,
            'termsCount' => $year->relationLoaded('terms') ? $year->terms->count() : null,
            'createdAt' => $year->created_at?->toISOString(),
            'updatedAt' => $year->updated_at?->toISOString(),
        ];
    }

    public function termSnapshot(PreschoolAcademicTerm $term): array
    {
        return [
            'id' => $term->id,
            'academicYearId' => $term->academic_year_id,
            'academicYearCode' => $term->academicYear?->code,
            'academicYearLabel' => $term->academicYear?->label,
            'code' => $term->code,
            'name' => $term->name,
            'startDate' => $term->start_date?->toDateString(),
            'endDate' => $term->end_date?->toDateString(),
            'status' => $term->status,
            'isCurrent' => (bool) $term->is_current,
            'sortOrder' => (int) $term->sort_order,
            'notes' => $term->notes,
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

    private function normalizeText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeCode(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['active', 'closed', 'archived'], true) ? $value : 'active';
    }
}
