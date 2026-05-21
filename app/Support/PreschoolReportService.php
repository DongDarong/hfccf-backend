<?php

namespace App\Support;

use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PreschoolReportService
{
    public function __construct(
        private readonly PreschoolAssessmentAggregationService $aggregation,
    ) {}

    /**
     * Student reports stay derived from finalized assessment data so every
     * report page can safely reuse the same contract without a PDF snapshot.
     */
    public function bundle(User $user, PreschoolStudent $student, ?string $periodLabel = null): array
    {
        $periods = $this->aggregation->reportPeriods($user, $student);
        $selectedPeriod = $this->resolveSelectedPeriod($periods, $periodLabel);

        return [
            'student' => $this->studentSnapshot($student),
            'periods' => $periods->all(),
            'period' => $selectedPeriod ? $this->periodFromPeriods($periods, $selectedPeriod) : null,
            'report' => $selectedPeriod ? $this->studentReport($user, $student, $selectedPeriod, $periods) : null,
        ];
    }

    public function reportPeriods(User $user, ?PreschoolStudent $student = null): Collection
    {
        return $this->aggregation->reportPeriods($user, $student);
    }

    public function studentReportForPeriod(User $user, PreschoolStudent $student, string $periodLabel): array
    {
        $periods = $this->aggregation->reportPeriods($user, $student);
        $selectedPeriod = $this->normalizeLabel($periodLabel);

        if ($periods->doesntContain('label', $selectedPeriod)) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        return $this->studentReport($user, $student, $selectedPeriod, $periods);
    }

    private function studentReport(User $user, PreschoolStudent $student, string $periodLabel, Collection $periods): array
    {
        $assessments = $this->aggregation->finalizedAssessmentsForStudent($user, $student, $periodLabel);
        $period = $this->periodFromPeriods($periods, $periodLabel);

        if ($assessments->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        $attendance = $period
            ? $this->aggregation->studentAttendanceSummary($student, $period['fromDate'], $period['toDate'])
            : [
                'attendanceCount' => 0,
                'presentCount' => 0,
                'lateCount' => 0,
                'absentCount' => 0,
                'excusedCount' => 0,
                'latestAttendanceDate' => null,
            ];

        $assessmentScores = $assessments
            ->pluck('score')
            ->filter(static fn ($score) => $score !== null)
            ->map(static fn ($score) => (float) $score);

        $categorySummaries = $assessments
            ->groupBy('category_id')
            ->map(function (Collection $items): array {
                $scores = $items->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);
                $category = $items->first()?->category;

                return [
                    'category' => $this->categorySnapshot($category),
                    'count' => $items->count(),
                    'averageScore' => $scores->count() ? round((float) $scores->avg(), 2) : null,
                    'latestAssessmentDate' => $items->first()?->assessment_date?->toDateString(),
                    'observationCount' => $items->filter(static fn ($item) => trim((string) $item->observation) !== '' || trim((string) $item->teacher_comment) !== '')->count(),
                ];
            })
            ->values()
            ->all();

        $observations = $assessments
            ->filter(static fn ($assessment) => trim((string) $assessment->observation) !== '' || trim((string) $assessment->teacher_comment) !== '')
            ->map(function ($assessment): array {
                return [
                    'assessmentId' => $assessment->id,
                    'assessmentDate' => $assessment->assessment_date?->toDateString(),
                    'periodLabel' => $assessment->period_label,
                    'category' => $this->categorySnapshot($assessment->category),
                    'observation' => $assessment->observation,
                    'teacherComment' => $assessment->teacher_comment,
                    'assessedByName' => trim(($assessment->assessedBy?->first_name ?? '').' '.($assessment->assessedBy?->last_name ?? '')),
                    'rating' => $assessment->rating,
                    'score' => $assessment->score,
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'finalizedAssessments' => $assessments->count(),
                'averageScore' => $assessmentScores->count() ? round((float) $assessmentScores->avg(), 2) : null,
                'latestAssessmentDate' => $assessments->first()?->assessment_date?->toDateString(),
                'observationCount' => count($observations),
            ],
            'attendanceSummary' => $attendance,
            'categorySummaries' => $categorySummaries,
            'observations' => $observations,
            'assessments' => $assessments->map(fn ($assessment) => [
                'id' => $assessment->id,
                'studentId' => $assessment->student_id,
                'studentName' => trim(($assessment->student?->first_name ?? '').' '.($assessment->student?->last_name ?? '')),
                'classId' => $assessment->class_id,
                'className' => $assessment->preschoolClass?->name,
                'categoryId' => $assessment->category_id,
                'categoryCode' => $assessment->category?->code,
                'categoryName' => $assessment->category?->name,
                'category' => $this->categorySnapshot($assessment->category),
                'assessedByUserId' => $assessment->assessed_by_user_id,
                'assessedByName' => trim(($assessment->assessedBy?->first_name ?? '').' '.($assessment->assessedBy?->last_name ?? '')),
                'periodLabel' => $assessment->period_label,
                'assessmentDate' => $assessment->assessment_date?->toDateString(),
                'score' => $assessment->score,
                'rating' => $assessment->rating,
                'observation' => $assessment->observation,
                'teacherComment' => $assessment->teacher_comment,
                'status' => $assessment->status,
                'finalizedAt' => $assessment->finalized_at?->toISOString(),
                'finalizedByUserId' => $assessment->finalized_by_user_id,
                'finalizedByName' => trim(($assessment->finalizedBy?->first_name ?? '').' '.($assessment->finalizedBy?->last_name ?? '')),
            ])->all(),
            'generatedAt' => Carbon::now()->toISOString(),
        ];
    }

    private function resolveSelectedPeriod(Collection $periods, ?string $periodLabel = null): ?string
    {
        $requested = $this->normalizeLabel($periodLabel ?? '');

        if ($requested !== '') {
            return $requested;
        }

        return (string) ($periods->first()['label'] ?? '');
    }

    private function periodFromPeriods(Collection $periods, string $periodLabel): ?array
    {
        $period = $periods->firstWhere('label', $periodLabel);

        return $period ?: null;
    }

    private function studentSnapshot(PreschoolStudent $student): array
    {
        $guardianSnapshot = app(PreschoolGuardianSnapshotService::class)->preferredGuardianSnapshot($student);

        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'fullName' => trim($student->first_name.' '.$student->last_name),
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'gender' => $student->gender,
            'dateOfBirth' => $student->date_of_birth?->toDateString(),
            'guardianName' => $guardianSnapshot['guardianName'] ?? $student->guardian_name,
            'guardianPhone' => $guardianSnapshot['guardianPhone'] ?? $student->guardian_phone,
            'guardianSource' => $guardianSnapshot['source'] ?? 'legacy',
            'status' => $student->status,
        ];
    }

    private function categorySnapshot(mixed $category): ?array
    {
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'sortOrder' => $category->sort_order,
            'isActive' => (bool) $category->is_active,
        ];
    }

    private function normalizeLabel(string $periodLabel): string
    {
        return trim($periodLabel);
    }
}
