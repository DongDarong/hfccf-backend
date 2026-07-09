<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolReportSnapshot;
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
    public function bundle(User $user, PreschoolClass $class, ?string $periodLabel = null, array $filters = []): array
    {
        $periods = $this->aggregation->reportPeriods($user, null, $class);
        $selectedPeriod = $this->resolveSelectedPeriod($periods, $periodLabel, $filters['report_period_id'] ?? null);

        return [
            'class' => $this->classSnapshot($class),
            'periods' => $periods->all(),
            'period' => $selectedPeriod,
            'report' => $selectedPeriod ? $this->classroomReportForPeriods($user, $class, $selectedPeriod, $periods) : null,
        ];
    }

    public function classroomReportForPeriod(User $user, PreschoolClass $class, string $periodLabel, array $filters = []): array
    {
        $periods = $this->aggregation->reportPeriods($user, null, $class);
        $selectedPeriod = $this->resolveSelectedPeriod($periods, $periodLabel, $filters['report_period_id'] ?? null);

        if (! $selectedPeriod) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        return $this->classroomReportForPeriods($user, $class, $selectedPeriod, $periods);
    }

    private function classroomReportForPeriods(User $user, PreschoolClass $class, array $period, Collection $periods): array
    {
        $snapshot = app(PreschoolReportSnapshotService::class)->latestForContext('classroom_report', $this->snapshotContext($class, $period));
        if ($snapshot) {
            return $this->decorateReport($snapshot->snapshot_payload['report'] ?? $snapshot->snapshot_payload, $snapshot);
        }

        $report = $this->buildClassroomReport($user, $class, $period, $periods);

        if ($this->isFrozenPeriod($period)) {
            $snapshot = app(PreschoolReportSnapshotService::class)->storeSnapshot(
                'classroom_report',
                $this->snapshotContext($class, $period),
                [
                    'class' => $this->classSnapshot($class),
                    'period' => $period,
                    'report' => $report,
                ],
                $user,
                true,
            );

            return $this->decorateReport($report, $snapshot);
        }

        return $this->decorateReport($report, null);
    }

    private function buildClassroomReport(User $user, PreschoolClass $class, array $period, Collection $periods): array
    {
        $periodModel = $this->periodModelFromRow($period);
        $assessments = $periodModel
            ? $this->aggregation->finalizedAssessmentsForPeriod($user, null, $class, $periodModel)
            : $this->aggregation->finalizedAssessmentsForClass($user, $class, (string) ($period['label'] ?? ''));

        if ($assessments->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'Selected report period is not available.',
            ]);
        }

        $attendance = $periodModel && $periodModel->from_date && $periodModel->to_date
            ? $this->aggregation->classAttendanceSummary($class, $periodModel->from_date->toDateString(), $periodModel->to_date->toDateString())
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

        $studentAttendance = $periodModel && $periodModel->from_date && $periodModel->to_date
            ? $this->aggregation->attendanceByStudent($class, $periodModel->from_date->toDateString(), $periodModel->to_date->toDateString())
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
            'scoreSummary' => $this->aggregation->scoreSummary($assessments),
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

    private function resolveSelectedPeriod(Collection $periods, ?string $periodLabel = null, mixed $reportPeriodId = null): ?array
    {
        $resolvedId = $this->nullableInt($reportPeriodId);
        if ($resolvedId !== null) {
            $matched = $periods->firstWhere('reportPeriodId', $resolvedId);
            if ($matched) {
                return $matched;
            }
        }

        $requested = $this->normalizeLabel($periodLabel ?? '');
        if ($requested !== '') {
            return $periods->firstWhere('label', $requested);
        }

        return $periods->first() ?: null;
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

    private function snapshotContext(PreschoolClass $class, array $period): array
    {
        return [
            'class_id' => $class->id,
            'academic_year_id' => $period['academicYearId'] ?? null,
            'term_id' => $period['termId'] ?? null,
            'report_period_id' => $period['reportPeriodId'] ?? $period['id'] ?? null,
            'lifecycle_state' => $period['status'] ?? 'finalized',
        ];
    }

    private function decorateReport(array $report, ?PreschoolReportSnapshot $snapshot): array
    {
        return array_merge($report, [
            'source' => $snapshot ? 'snapshot' : 'live',
            'snapshot' => $snapshot ? app(PreschoolReportSnapshotService::class)->snapshotPayload($snapshot) : null,
            'frozen' => (bool) $snapshot,
        ]);
    }

    private function isFrozenPeriod(array $period): bool
    {
        return in_array(strtolower((string) ($period['status'] ?? '')), ['finalized', 'locked', 'archived'], true);
    }

    private function periodModelFromRow(array $period): ?PreschoolReportPeriod
    {
        $reportPeriodId = $this->nullableInt($period['reportPeriodId'] ?? $period['id'] ?? null);

        if ($reportPeriodId === null) {
            return null;
        }

        return PreschoolReportPeriod::query()
            ->with(['academicYear', 'term', 'lockedBy', 'finalizedBy', 'archivedBy'])
            ->find($reportPeriodId);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
