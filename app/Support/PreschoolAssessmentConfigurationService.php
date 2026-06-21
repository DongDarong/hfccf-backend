<?php

namespace App\Support;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolAssessmentGradingScale;
use App\Models\PreschoolAssessmentReportPeriod;
use App\Models\PreschoolAssessmentSetting;
use App\Models\PreschoolAssessmentWeight;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreschoolAssessmentConfigurationService
{
    public const DEFAULT_PASSING_SCORE = 60;
    public const DEFAULT_GRADING_SCALE_TYPE = 'letter';

    public function getSettings(): PreschoolAssessmentSetting
    {
        $settings = PreschoolAssessmentSetting::query()->first();

        return $settings ?? new PreschoolAssessmentSetting([
            'passing_score' => self::DEFAULT_PASSING_SCORE,
            'grading_scale_type' => self::DEFAULT_GRADING_SCALE_TYPE,
            'weighting_enabled' => false,
        ]);
    }

    public function updateSettings(array $data, ?User $actor = null): PreschoolAssessmentSetting
    {
        $payload = [
            'passing_score' => (int) Arr::get($data, 'passing_score', self::DEFAULT_PASSING_SCORE),
            'grading_scale_type' => $this->normalizeGradingScaleType(Arr::get($data, 'grading_scale_type', self::DEFAULT_GRADING_SCALE_TYPE)),
            'weighting_enabled' => (bool) Arr::get($data, 'weighting_enabled', false),
            'updated_by' => $actor?->id,
        ];

        return DB::transaction(function () use ($payload, $actor): PreschoolAssessmentSetting {
            $settings = PreschoolAssessmentSetting::query()->first();

            if ($settings) {
                $settings->fill($payload);
                if (! $settings->created_by) {
                    $settings->created_by = $actor?->id;
                }
                $settings->save();

                return $settings->refresh();
            }

            $payload['created_by'] = $actor?->id;

            return PreschoolAssessmentSetting::query()->create($payload);
        });
    }

    public function getGradingScale(): Collection
    {
        return PreschoolAssessmentGradingScale::query()
            ->orderBy('sort_order')
            ->orderBy('minimum_score')
            ->get();
    }

    public function createGradeBand(array $data, ?User $actor = null): PreschoolAssessmentGradingScale
    {
        return DB::transaction(function () use ($data, $actor): PreschoolAssessmentGradingScale {
            $band = PreschoolAssessmentGradingScale::query()->create([
                'name' => trim((string) $data['name']),
                'grade' => trim((string) $data['grade']),
                'minimum_score' => (float) $data['minimum_score'],
                'maximum_score' => (float) $data['maximum_score'],
                'color' => $this->nullableText($data['color'] ?? null),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_passing' => (bool) ($data['is_passing'] ?? false),
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->assertGradingScaleIntegrity();

            return $band->refresh();
        });
    }

    public function updateGradeBand(PreschoolAssessmentGradingScale $band, array $data, ?User $actor = null): PreschoolAssessmentGradingScale
    {
        return DB::transaction(function () use ($band, $data, $actor): PreschoolAssessmentGradingScale {
            foreach (['name', 'grade', 'minimum_score', 'maximum_score', 'color', 'sort_order', 'is_passing'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $band->{$field} = match ($field) {
                    'name', 'grade' => trim((string) $data[$field]),
                    'minimum_score', 'maximum_score' => (float) $data[$field],
                    'color' => $this->nullableText($data[$field]),
                    'sort_order' => (int) $data[$field],
                    'is_passing' => (bool) $data[$field],
                };
            }

            $band->updated_by = $actor?->id;
            $band->save();

            $this->assertGradingScaleIntegrity();

            return $band->refresh();
        });
    }

    public function deleteGradeBand(PreschoolAssessmentGradingScale $band): void
    {
        DB::transaction(function () use ($band): void {
            $band->delete();
            $this->assertGradingScaleIntegrity();
        });
    }

    public function listCategories(bool $includeArchived = false): Collection
    {
        $query = PreschoolAssessmentCategory::query()->orderBy('sort_order')->orderBy('name');

        if ($includeArchived) {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function createCategory(array $data, ?User $actor = null): PreschoolAssessmentCategory
    {
        return PreschoolAssessmentCategory::query()->create([
            'code' => $this->nullableText($data['code'] ?? null),
            'name' => trim((string) $data['name']),
            'description' => $this->nullableText($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    public function updateCategory(PreschoolAssessmentCategory $category, array $data, ?User $actor = null): PreschoolAssessmentCategory
    {
        foreach (['code', 'name', 'description', 'sort_order', 'is_active'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $category->{$field} = match ($field) {
                'code', 'name', 'description' => $this->nullableText($data[$field]),
                'sort_order' => (int) $data[$field],
                'is_active' => (bool) $data[$field],
            };
        }

        $category->updated_by = $actor?->id;
        $category->save();

        return $category->refresh();
    }

    public function archiveCategory(PreschoolAssessmentCategory $category, ?User $actor = null): PreschoolAssessmentCategory
    {
        $category->is_active = false;
        $category->updated_by = $actor?->id;
        $category->save();
        $category->delete();

        return $category->refresh();
    }

    public function listReportPeriods(bool $includeArchived = false): Collection
    {
        $query = PreschoolAssessmentReportPeriod::query()
            ->with(['academicYear', 'term'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($includeArchived) {
            $query->withTrashed();
        }

        return $query->get();
    }

    public function createReportPeriod(array $data, ?User $actor = null): PreschoolAssessmentReportPeriod
    {
        return DB::transaction(function () use ($data, $actor): PreschoolAssessmentReportPeriod {
            $this->assertReportPeriodIntegrity($data['academic_year_id'] ?? null, $data['term_id'] ?? null, $data['start_date'] ?? null, $data['end_date'] ?? null);

            $period = PreschoolAssessmentReportPeriod::query()->create([
                'academic_year_id' => (int) $data['academic_year_id'],
                'term_id' => $this->nullableInt($data['term_id'] ?? null),
                'name' => trim((string) $data['name']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            return $period->refresh()->load(['academicYear', 'term']);
        });
    }

    public function updateReportPeriod(PreschoolAssessmentReportPeriod $period, array $data, ?User $actor = null): PreschoolAssessmentReportPeriod
    {
        return DB::transaction(function () use ($period, $data, $actor): PreschoolAssessmentReportPeriod {
            $academicYearId = array_key_exists('academic_year_id', $data) ? $data['academic_year_id'] : $period->academic_year_id;
            $termId = array_key_exists('term_id', $data) ? $data['term_id'] : $period->term_id;
            $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $period->start_date?->toDateString();
            $endDate = array_key_exists('end_date', $data) ? $data['end_date'] : $period->end_date?->toDateString();

            $this->assertReportPeriodIntegrity($academicYearId, $termId, $startDate, $endDate);

            foreach (['academic_year_id', 'term_id', 'name', 'start_date', 'end_date', 'is_active'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $period->{$field} = match ($field) {
                    'academic_year_id' => (int) $data[$field],
                    'term_id' => $this->nullableInt($data[$field] ?? null),
                    'name' => trim((string) $data[$field]),
                    'start_date', 'end_date' => $data[$field],
                    'is_active' => (bool) $data[$field],
                };
            }

            $period->updated_by = $actor?->id;
            $period->save();

            return $period->refresh()->load(['academicYear', 'term']);
        });
    }

    public function archiveReportPeriod(PreschoolAssessmentReportPeriod $period, ?User $actor = null): PreschoolAssessmentReportPeriod
    {
        $period->is_active = false;
        $period->updated_by = $actor?->id;
        $period->save();
        $period->delete();

        return $period->refresh()->load(['academicYear', 'term']);
    }

    public function listWeights(): Collection
    {
        return PreschoolAssessmentWeight::query()
            ->with('category')
            ->orderBy('category_id')
            ->get();
    }

    public function updateWeights(array $weights, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($weights, $actor): Collection {
            $rows = collect($weights)->map(function ($row) {
                return [
                    'category_id' => (int) ($row['category_id'] ?? 0),
                    'percentage' => (float) ($row['percentage'] ?? 0),
                ];
            })->filter(static fn (array $row): bool => $row['category_id'] > 0)->values();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'weights' => 'At least one assessment weight is required.',
                ]);
            }

            $total = round((float) $rows->sum('percentage'), 2);
            if ($total !== 100.0) {
                throw ValidationException::withMessages([
                    'weights' => 'Assessment weights must total 100%.',
                ]);
            }

            $categoryIds = $rows->pluck('category_id')->all();
            $existingCategories = PreschoolAssessmentCategory::query()->whereIn('id', $categoryIds)->pluck('id')->map(static fn ($id) => (int) $id)->all();
            if ($existingCategories !== $categoryIds) {
                throw ValidationException::withMessages([
                    'weights' => 'All weight entries must reference existing assessment categories.',
                ]);
            }

            PreschoolAssessmentWeight::query()->delete();

            foreach ($rows as $row) {
                PreschoolAssessmentWeight::query()->create([
                    'category_id' => $row['category_id'],
                    'percentage' => $row['percentage'],
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);
            }

            return $this->listWeights();
        });
    }

    public function getPassingScore(): int
    {
        $passingScore = $this->getSettings()->passing_score;

        return $passingScore === null ? self::DEFAULT_PASSING_SCORE : (int) $passingScore;
    }

    public function getGradeForScore(float|int|null $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $band = $this->getGradingScale()->first(function (PreschoolAssessmentGradingScale $scale) use ($score): bool {
            return (float) $score >= (float) $scale->minimum_score && (float) $score <= (float) $scale->maximum_score;
        });

        return $band?->grade;
    }

    public function getAssessmentCategories(bool $includeArchived = false): Collection
    {
        if ($includeArchived) {
            return $this->listCategories(true);
        }

        return $this->listCategories(false);
    }

    public function getActiveReportPeriod(): ?PreschoolAssessmentReportPeriod
    {
        return PreschoolAssessmentReportPeriod::query()
            ->with(['academicYear', 'term'])
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();
    }

    public function calculateWeightedScore(array $categoryScores): float
    {
        $settings = $this->getSettings();
        $scores = collect($categoryScores);

        if (! $settings->weighting_enabled) {
            return round((float) ($scores->avg('score') ?? 0), 2);
        }

        $weights = $this->listWeights()->keyBy('category_id');
        $weighted = $scores->reduce(function (float $carry, array $row) use ($weights): float {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $score = (float) ($row['score'] ?? 0);
            $weight = (float) ($weights->get($categoryId)?->percentage ?? 0);

            return $carry + ($score * ($weight / 100));
        }, 0.0);

        return round($weighted, 2);
    }

    private function assertGradingScaleIntegrity(): void
    {
        $bands = $this->getGradingScale()->sortBy('minimum_score')->values();

        if ($bands->isEmpty()) {
            return;
        }

        $previousMax = null;

        foreach ($bands as $band) {
            $min = (float) $band->minimum_score;
            $max = (float) $band->maximum_score;

            if ($min > $max) {
                throw ValidationException::withMessages([
                    'minimum_score' => 'Minimum score must be less than or equal to maximum score.',
                ]);
            }

            if ($previousMax === null) {
                $previousMax = $max;

                continue;
            }

            if ($min <= $previousMax) {
                throw ValidationException::withMessages([
                    'minimum_score' => 'Grading bands must not overlap.',
                ]);
            }

            $previousMax = $max;
        }
    }

    private function assertReportPeriodIntegrity(mixed $academicYearId, mixed $termId, mixed $startDate, mixed $endDate): void
    {
        $academicYear = PreschoolAcademicYear::query()->find($academicYearId);

        if (! $academicYear) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'The selected academic year is invalid.',
            ]);
        }

        $start = $this->normalizeDate($startDate);
        $end = $this->normalizeDate($endDate);
        if (! $start || ! $end) {
            throw ValidationException::withMessages([
                'start_date' => 'Valid start and end dates are required.',
            ]);
        }

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be after or equal to the start date.',
            ]);
        }

        if ($academicYear->start_date && $academicYear->end_date) {
            $yearStart = $academicYear->start_date->toDateString();
            $yearEnd = $academicYear->end_date->toDateString();

            if ($start < $yearStart || $end > $yearEnd) {
                throw ValidationException::withMessages([
                    'start_date' => 'The period must fall within the selected academic year.',
                    'end_date' => 'The period must fall within the selected academic year.',
                ]);
            }
        }

        if ($termId !== null && $termId !== '') {
            $term = PreschoolAcademicTerm::query()->find($termId);
            if (! $term || (int) $term->academic_year_id !== (int) $academicYearId) {
                throw ValidationException::withMessages([
                    'term_id' => 'The selected term must belong to the selected academic year.',
                ]);
            }
        }
    }

    private function normalizeGradingScaleType(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['percentage', 'letter', 'custom'], true) ? $value : self::DEFAULT_GRADING_SCALE_TYPE;
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

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : \Illuminate\Support\Carbon::parse($value)->toDateString();
    }
}
