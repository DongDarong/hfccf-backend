<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PreschoolClassroomReportService
{
    public function __construct(
        private readonly PreschoolAssessmentAggregationService $aggregation,
    ) {}

    /**
     * Classroom reporting is derived from finalized assessments and dated
     * attendance rows so the same history remains trustworthy over time.
     */
    public function bundle(User $user, PreschoolClass $class, ?string $periodLabel = null): array
    {
        $periods = $this->aggregation->reportPeriods($user, null, $class);
        $selectedPeriod = $this->resolveSelectedPeriod($periods, $periodLabel);

        return [
            'class' => $this->classSnapshot($class),
            'periods' => $periods->all(),
            'period' => $selectedPeriod ? $this->periodFromPeriods($periods, $selectedPeriod) : null,
            'report' => $selectedPeriod ? $this->classroomReport($user, $class, $selectedPeriod, $periods) : null,
        ];
    }

    public function classroomReportForPeriod(User $user, PreschoolClass $class, string $periodLabel): array
    {
        $periods = $this->aggregation->reportPeriods($user, null, $class);
        $selectedPeriod = $this->normalizeLabel($periodLabel);

        if ($periods->doesntContain('label', $selectedPeriod)) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        return $this->classroomReport($user, $class, $selectedPeriod, $periods);
    }

    private function classroomReport(User $user, PreschoolClass $class, string $periodLabel, Collection $periods): array
    {
        $assessments = $this->aggregation->finalizedAssessmentsForClass($user, $class, $periodLabel);
        $period = $this->periodFromPeriods($periods, $periodLabel);

        if ($assessments->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        $attendance = $period
            ? $this->aggregation->classAttendanceSummary($class, $period['fromDate'], $period['toDate'])
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

        $studentAttendance = $period
            ? $this->aggregation->attendanceByStudent($class, $period['fromDate'], $period['toDate'])
            : collect();

        $studentSummaries = $class->students()
            ->withPivot(['enrolled_at', 'status'])
            ->orderBy('first_name')
            ->get()
            ->map(function ($student) use ($assessments, $studentAttendance): array {
                $studentAssessments = $assessments->where('student_id', $student->id);
                $scores = $studentAssessments->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);
                $attendance = $studentAttendance->get($student->id, [
                    'attendanceCount' => 0,
                    'presentCount' => 0,
                    'lateCount' => 0,
                    'absentCount' => 0,
                    'excusedCount' => 0,
                    'latestAttendanceDate' => null,
                ]);

                return [
                    'student' => [
                        'id' => $student->id,
                        'studentCode' => $student->student_code,
                        'fullName' => trim($student->first_name.' '.$student->last_name),
                        'status' => $student->status,
                    ],
                    'assessmentCount' => $studentAssessments->count(),
                    'averageScore' => $scores->count() ? round((float) $scores->avg(), 2) : null,
                    'latestAssessmentDate' => $studentAssessments->first()?->assessment_date?->toDateString(),
                    'attendanceSummary' => $attendance,
                ];
            })
            ->values()
            ->all();

        $observations = $assessments
            ->filter(static fn ($assessment) => trim((string) $assessment->observation) !== '' || trim((string) $assessment->teacher_comment) !== '')
            ->map(function ($assessment): array {
                return [
                    'assessmentId' => $assessment->id,
                    'studentId' => $assessment->student_id,
                    'studentName' => trim(($assessment->student?->first_name ?? '').' '.($assessment->student?->last_name ?? '')),
                    'assessmentDate' => $assessment->assessment_date?->toDateString(),
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
                'studentCount' => $studentSummaries ? count($studentSummaries) : 0,
            ],
            'attendanceSummary' => $attendance,
            'categorySummaries' => $categorySummaries,
            'studentSummaries' => $studentSummaries,
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

    private function classSnapshot(PreschoolClass $class): array
    {
        return [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'teacherUserId' => $class->teacher_user_id,
            'teacherDisplayName' => $class->teacher_display_name,
            'level' => $class->level,
            'schedule' => $class->schedule,
            'room' => $class->room,
            'status' => $class->status,
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
