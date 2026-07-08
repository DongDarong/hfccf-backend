<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolAssessmentAggregationService
{
    /**
     * Reporting stays derived from finalized assessments so report history
     * remains stable without adding a second source of truth.
     */
    public function reportPeriods(User $user, ?PreschoolStudent $student = null, ?PreschoolClass $class = null): Collection
    {
        $query = $this->finalizedAssessmentsQuery($user, $student, $class);

        return $query
            ->selectRaw('period_label as label, MIN(assessment_date) as from_date, MAX(assessment_date) as to_date, COUNT(*) as assessment_count, COUNT(DISTINCT student_id) as student_count, COUNT(DISTINCT class_id) as class_count')
            ->groupBy('period_label')
            ->orderByDesc('to_date')
            ->orderByDesc('period_label')
            ->get()
            ->map(static function ($row): array {
                $alignment = app(PreschoolAcademicLifecycleService::class)->resolveForDate($row->to_date ?? $row->from_date);
                $period = app(PreschoolReportPeriodService::class)->findByContext(
                    (string) ($row->label ?? ''),
                    null,
                    $alignment['academic_year_id'] ?? null,
                    $alignment['term_id'] ?? null,
                );

                return [
                    'label' => (string) ($row->label ?? ''),
                    'periodType' => $period?->period_type ?? 'term',
                    'reportPeriodId' => $period?->id,
                    'fromDate' => $row->from_date ?? null,
                    'toDate' => $row->to_date ?? null,
                    'latestAssessmentDate' => $row->to_date ?? null,
                    'assessmentCount' => (int) ($row->assessment_count ?? 0),
                    'studentCount' => (int) ($row->student_count ?? 0),
                    'classCount' => (int) ($row->class_count ?? 0),
                    'status' => $period?->status ?? 'finalized',
                    'academicYearId' => $alignment['academic_year_id'] ?? null,
                    'academicYear' => $alignment['academic_year'] ?? null,
                    'termId' => $alignment['term_id'] ?? null,
                    'termLabel' => $alignment['term_label'] ?? null,
                ];
            })
            ->values();
    }

    public function finalizedAssessmentsForPeriod(User $user, ?PreschoolStudent $student, ?PreschoolClass $class, PreschoolReportPeriod $period): Collection
    {
        return $this->finalizedAssessmentsQuery($user, $student, $class)
            ->where('period_label', $period->period_label)
            ->when($period->from_date && $period->to_date, function (Builder $query) use ($period): void {
                $query->whereBetween('assessment_date', [
                    $period->from_date->toDateString(),
                    $period->to_date->toDateString(),
                ]);
            })
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();
    }

    public function finalizedAssessmentsForStudent(User $user, PreschoolStudent $student, string $periodLabel): Collection
    {
        return $this->finalizedAssessmentsQuery($user, $student)
            ->where('period_label', $this->normalizeLabel($periodLabel))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();
    }

    public function finalizedAssessmentsForClass(User $user, PreschoolClass $class, string $periodLabel): Collection
    {
        return $this->finalizedAssessmentsQuery($user, null, $class)
            ->where('period_label', $this->normalizeLabel($periodLabel))
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();
    }

    public function studentAttendanceSummary(PreschoolStudent $student, string $fromDate, string $toDate): array
    {
        return $this->attendanceSummary(
            PreschoolAttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereBetween('attendance_date', [$fromDate, $toDate]),
        );
    }

    public function classAttendanceSummary(PreschoolClass $class, string $fromDate, string $toDate): array
    {
        return $this->attendanceSummary(
            PreschoolAttendanceRecord::query()
                ->where('class_id', $class->id)
                ->whereBetween('attendance_date', [$fromDate, $toDate]),
        );
    }

    public function attendanceByStudent(PreschoolClass $class, string $fromDate, string $toDate): Collection
    {
        return PreschoolAttendanceRecord::query()
            ->with(['student'])
            ->where('class_id', $class->id)
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->get()
            ->groupBy('student_id')
            ->map(static function (Collection $records): array {
                return [
                    'attendanceCount' => $records->count(),
                    'presentCount' => $records->where('status', 'present')->count(),
                    'lateCount' => $records->where('status', 'late')->count(),
                    'absentCount' => $records->where('status', 'absent')->count(),
                    'excusedCount' => $records->where('status', 'excused')->count(),
                    'latestAttendanceDate' => $records->sortByDesc('attendance_date')->first()?->attendance_date?->toDateString(),
                ];
            });
    }

    public function scoreSummary(Collection $assessments): array
    {
        $settings = app(PreschoolAssessmentConfigurationService::class);
        $grouped = $assessments->groupBy('category_id');
        $categorySummaries = $grouped->map(function (Collection $items) use ($settings): array {
            $scores = $items->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);
            $category = $items->first()?->category;
            $averageScore = $scores->count() ? round((float) $scores->avg(), 2) : null;

            return [
                'categoryId' => $category?->id,
                'category' => $category ? [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'description' => $category->description,
                    'sortOrder' => $category->sort_order,
                    'isActive' => (bool) $category->is_active,
                ] : null,
                'count' => $items->count(),
                'averageScore' => $averageScore,
                'weight' => $this->categoryWeight((int) ($category?->id ?? 0)),
                'weightedResult' => $averageScore === null
                    ? null
                    : round($averageScore * ($this->categoryWeight((int) ($category?->id ?? 0)) / 100), 2),
                'latestAssessmentDate' => $items->first()?->assessment_date?->toDateString(),
            ];
        })->values();

        $categoryScores = $categorySummaries
            ->filter(fn (array $row): bool => $row['categoryId'] !== null && $row['averageScore'] !== null)
            ->map(fn (array $row): array => [
                'category_id' => $row['categoryId'],
                'score' => $row['averageScore'],
            ])
            ->values();

        $overallScore = $settings->calculateWeightedScore($categoryScores->all());
        $passingScore = $settings->getPassingScore();

        return [
            'categorySummaries' => $categorySummaries->all(),
            'overallScore' => $overallScore,
            'grade' => $settings->getGradeForScore($overallScore),
            'passingScore' => $passingScore,
            'isPassing' => $overallScore !== null ? $overallScore >= $passingScore : false,
            'calculationMethod' => $settings->getSettings()->weighting_enabled ? 'weighted' : 'average',
            'includedAssessments' => $assessments->count(),
            'averageScore' => $assessments->pluck('score')->filter(static fn ($score) => $score !== null)->count()
                ? round((float) $assessments->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score)->avg(), 2)
                : null,
        ];
    }

    public function ensureUserCanAccessClass(?User $user, PreschoolClass $class): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless($user->role_code === 'teacher-preschool', Response::HTTP_FORBIDDEN, 'Forbidden.');
        abort_unless((string) $class->teacher_user_id === (string) $user->id, Response::HTTP_FORBIDDEN, 'Forbidden.');
    }

    public function accessibleClassIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if (in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            return [];
        }

        if ($user->role_code !== 'teacher-preschool') {
            return [];
        }

        return PreschoolClass::query()
            ->where('teacher_user_id', $user->id)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    public function finalizedAssessmentsQuery(User $user, ?PreschoolStudent $student = null, ?PreschoolClass $class = null): Builder
    {
        $query = PreschoolStudentAssessment::query()
            ->with(['student', 'preschoolClass', 'category', 'assessedBy', 'finalizedBy'])
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->whereNotNull('period_label')
            ->where('period_label', '!=', '');

        if ($student) {
            app(PreschoolAssessmentService::class)->ensureUserCanAccessStudent($user, $student, $class?->id);
            $query->where('student_id', $student->id);
        }

        if ($class) {
            $this->ensureUserCanAccessClass($user, $class);
            $query->where('class_id', $class->id);
        }

        if (! $student && ! $class) {
            $accessibleClassIds = $this->accessibleClassIds($user);
            if ($accessibleClassIds !== []) {
                $query->whereIn('class_id', $accessibleClassIds);
            } elseif (in_array($user->role_code ?? '', ['superadmin', 'adminpreschool'], true)) {
                // Super admin and admin preschool can read the full reporting set.
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    private function attendanceSummary(Builder $query): array
    {
        $records = $query->get();

        return [
            'attendanceCount' => $records->count(),
            'presentCount' => $records->where('status', 'present')->count(),
            'lateCount' => $records->where('status', 'late')->count(),
            'absentCount' => $records->where('status', 'absent')->count(),
            'excusedCount' => $records->where('status', 'excused')->count(),
            'latestAttendanceDate' => $records->sortByDesc('attendance_date')->first()?->attendance_date?->toDateString(),
        ];
    }

    private function normalizeLabel(string $periodLabel): string
    {
        return trim($periodLabel);
    }

    private function categoryWeight(int $categoryId): float
    {
        if ($categoryId <= 0) {
            return 0.0;
        }

        return (float) app(PreschoolAssessmentConfigurationService::class)
            ->listWeights()
            ->firstWhere('category_id', $categoryId)
            ?->percentage ?? 0.0;
    }
}
