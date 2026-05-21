<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardianPortalAccount;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PreschoolGuardianPortalSummaryService
{
    /**
     * Portal summaries are derived from finalized assessments, attendance,
     * and weekly schedule rows so guardians only see stable read-only data.
     */
    public function __construct(
        private readonly PreschoolGuardianAccessService $access,
    ) {}

    public function me(User $user): array
    {
        $account = $this->access->resolveActiveAccount($user);

        return [
            'account' => $this->accountSnapshot($account),
            'guardian' => $this->guardianSnapshot($account->guardian),
            'studentsCount' => $this->access->visibleStudents($user)->count(),
            'generatedAt' => Carbon::now()->toISOString(),
        ];
    }

    public function visibleStudents(User $user): Collection
    {
        $account = $this->access->resolveActiveAccount($user);
        $today = now()->toDateString();

        return PreschoolStudentGuardian::query()
            ->with(['guardian', 'student.classes'])
            ->where('guardian_id', $account->guardian_id)
            ->where('status', PreschoolGuardianStatus::ACTIVE)
            ->where(function ($query) use ($today): void {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->orderByDesc('is_primary')
            ->orderByRaw('COALESCE(emergency_priority, 999999) ASC')
            ->orderBy('created_at')
            ->get()
            ->map(function (PreschoolStudentGuardian $relationship): array {
                return [
                    'relationship' => $this->relationshipSnapshot($relationship),
                    'student' => $this->studentSnapshot($relationship->student),
                    'guardian' => $this->guardianSnapshot($relationship->guardian),
                ];
            })
            ->values();
    }

    public function studentBundle(User $user, PreschoolStudent $student): array
    {
        $this->access->ensureCanAccessStudent($user, $student);

        return [
            'student' => $this->studentSnapshot($student->loadMissing(['classes', 'studentGuardians.guardian'])),
            'relationships' => $this->relationshipsForStudent($user, $student),
            'attendanceSummary' => $this->attendanceSummary($user, $student),
            'scheduleSummary' => $this->scheduleSummary($user, $student),
            'progressSummary' => $this->progressSummary($user, $student),
            'reports' => $this->reportsSummary($user, $student),
            'generatedAt' => Carbon::now()->toISOString(),
        ];
    }

    public function attendanceSummary(User $user, PreschoolStudent $student): array
    {
        $this->access->ensureCanAccessStudent($user, $student);

        $fromDate = Carbon::now()->subDays(30)->startOfDay()->toDateString();
        $toDate = Carbon::now()->endOfDay()->toDateString();

        $records = PreschoolAttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->orderByDesc('attendance_date')
            ->get();

        return [
            'period' => [
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ],
            'attendanceCount' => $records->count(),
            'presentCount' => $records->where('status', 'present')->count(),
            'lateCount' => $records->where('status', 'late')->count(),
            'absentCount' => $records->where('status', 'absent')->count(),
            'excusedCount' => $records->where('status', 'excused')->count(),
            'latestAttendanceDate' => $records->first()?->attendance_date?->toDateString(),
        ];
    }

    public function scheduleSummary(User $user, PreschoolStudent $student): array
    {
        $this->access->ensureCanAccessStudent($user, $student);

        $classes = $student->classes()
            ->wherePivot('status', 'active')
            ->with(['teacher'])
            ->orderBy('name')
            ->get();

        $classIds = $classes->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $entries = PreschoolScheduleEntry::query()
            ->with(['class', 'teacher'])
            ->where('status', PreschoolScheduleStatus::ACTIVE)
            ->whereIn('class_id', $classIds)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return [
            'classes' => $classes->map(fn (PreschoolClass $class): array => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'room' => $class->room,
                'teacherDisplayName' => $class->teacher_display_name,
            ])->values()->all(),
            'entries' => $entries->map(fn (PreschoolScheduleEntry $entry): array => [
                'id' => $entry->id,
                'classId' => $entry->class_id,
                'className' => $entry->class?->name,
                'teacherUserId' => $entry->teacher_user_id,
                'teacherDisplayName' => $entry->teacher?->full_name,
                'dayOfWeek' => $entry->day_of_week,
                'startTime' => $entry->start_time,
                'endTime' => $entry->end_time,
                'room' => $entry->room,
                'activityLabel' => $entry->activity_label,
                'status' => $entry->status,
            ])->values()->all(),
        ];
    }

    public function progressSummary(User $user, PreschoolStudent $student): array
    {
        $this->access->ensureCanAccessStudent($user, $student);

        $assessments = PreschoolStudentAssessment::query()
            ->with(['category', 'assessedBy', 'finalizedBy', 'preschoolClass'])
            ->where('student_id', $student->id)
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();

        $scores = $assessments->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);

        return [
            'summary' => [
                'finalizedAssessments' => $assessments->count(),
                'averageScore' => $scores->count() ? round((float) $scores->avg(), 2) : null,
                'latestAssessmentDate' => $assessments->first()?->assessment_date?->toDateString(),
                'observationCount' => $assessments->filter(static fn ($assessment) => trim((string) $assessment->observation) !== '' || trim((string) $assessment->teacher_comment) !== '')->count(),
            ],
            'categorySummaries' => $assessments
                ->groupBy('category_id')
                ->map(function (Collection $items): array {
                    $scores = $items->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);
                    $category = $items->first()?->category;

                    return [
                        'category' => $this->categorySnapshot($category),
                        'count' => $items->count(),
                        'averageScore' => $scores->count() ? round((float) $scores->avg(), 2) : null,
                        'latestAssessmentDate' => $items->first()?->assessment_date?->toDateString(),
                    ];
                })
                ->values()
                ->all(),
            'observations' => $assessments
                ->filter(static fn ($assessment) => trim((string) $assessment->observation) !== '' || trim((string) $assessment->teacher_comment) !== '')
                ->map(function ($assessment): array {
                    return [
                        'assessmentId' => $assessment->id,
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
                ->all(),
        ];
    }

    public function reportsSummary(User $user, PreschoolStudent $student): array
    {
        $this->access->ensureCanAccessStudent($user, $student);

        $periods = PreschoolStudentAssessment::query()
            ->where('student_id', $student->id)
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->whereNotNull('period_label')
            ->where('period_label', '!=', '')
            ->selectRaw('period_label as label, MIN(assessment_date) as from_date, MAX(assessment_date) as to_date, COUNT(*) as assessment_count')
            ->groupBy('period_label')
            ->orderByDesc('to_date')
            ->get()
            ->map(static function ($row): array {
                return [
                    'label' => (string) ($row->label ?? ''),
                    'fromDate' => $row->from_date ?? null,
                    'toDate' => $row->to_date ?? null,
                    'assessmentCount' => (int) ($row->assessment_count ?? 0),
                ];
            })
            ->values();

        $period = $periods->first()['label'] ?? null;
        $report = $period ? $this->reportForPeriod($student, $period) : null;

        return [
            'periods' => $periods->all(),
            'period' => $period ? $this->periodSnapshot($periods, $period) : null,
            'report' => $report,
        ];
    }

    private function reportForPeriod(PreschoolStudent $student, string $periodLabel): ?array
    {
        $assessments = PreschoolStudentAssessment::query()
            ->with(['category', 'assessedBy', 'finalizedBy', 'preschoolClass'])
            ->where('student_id', $student->id)
            ->where('status', PreschoolAssessmentStatus::FINALIZED)
            ->where('period_label', $periodLabel)
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->get();

        if ($assessments->isEmpty()) {
            return null;
        }

        $assessmentScores = $assessments->pluck('score')->filter(static fn ($score) => $score !== null)->map(static fn ($score) => (float) $score);

        return [
            'summary' => [
                'finalizedAssessments' => $assessments->count(),
                'averageScore' => $assessmentScores->count() ? round((float) $assessmentScores->avg(), 2) : null,
                'latestAssessmentDate' => $assessments->first()?->assessment_date?->toDateString(),
                'observationCount' => $assessments->filter(static fn ($assessment) => trim((string) $assessment->observation) !== '' || trim((string) $assessment->teacher_comment) !== '')->count(),
            ],
            'assessments' => $assessments->map(fn ($assessment): array => [
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
        ];
    }

    private function relationshipsForStudent(User $user, PreschoolStudent $student): array
    {
        return $this->access->activeRelationshipsForStudent($user, $student)
            ->map(fn (PreschoolStudentGuardian $relationship): array => $this->relationshipSnapshot($relationship))
            ->values()
            ->all();
    }

    private function accountSnapshot(PreschoolGuardianPortalAccount $account): array
    {
        return [
            'id' => $account->id,
            'guardianId' => $account->guardian_id,
            'userId' => $account->user_id,
            'email' => $account->email,
            'status' => $account->status,
            'invitedAt' => $account->invited_at?->toISOString(),
            'activatedAt' => $account->activated_at?->toISOString(),
            'revokedAt' => $account->revoked_at?->toISOString(),
            'lastLoginAt' => $account->last_login_at?->toISOString(),
        ];
    }

    private function guardianSnapshot(mixed $guardian): ?array
    {
        if (! $guardian) {
            return null;
        }

        return [
            'id' => $guardian->id,
            'fullName' => $guardian->full_name,
            'phone' => $guardian->phone,
            'email' => $guardian->email,
            'status' => $guardian->status,
        ];
    }

    private function studentSnapshot(PreschoolStudent $student): array
    {
        return [
            'id' => $student->id,
            'studentCode' => $student->student_code,
            'fullName' => trim($student->first_name.' '.$student->last_name),
            'firstName' => $student->first_name,
            'lastName' => $student->last_name,
            'gender' => $student->gender,
            'dateOfBirth' => $student->date_of_birth?->toDateString(),
            'guardianName' => $student->guardian_name,
            'guardianPhone' => $student->guardian_phone,
            'status' => $student->status,
            'classes' => $student->classes->map(fn (PreschoolClass $class): array => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'room' => $class->room,
                'teacherDisplayName' => $class->teacher_display_name,
            ])->values()->all(),
        ];
    }

    private function relationshipSnapshot(PreschoolStudentGuardian $relationship): array
    {
        return [
            'id' => $relationship->id,
            'studentId' => $relationship->student_id,
            'guardianId' => $relationship->guardian_id,
            'relationshipType' => $relationship->relationship_type,
            'isPrimary' => (bool) $relationship->is_primary,
            'canPickup' => (bool) $relationship->can_pickup,
            'emergencyPriority' => $relationship->emergency_priority,
            'status' => $relationship->status,
            'startsAt' => $relationship->starts_at?->toDateString(),
            'endsAt' => $relationship->ends_at?->toDateString(),
            'notes' => $relationship->notes,
            'guardian' => $this->guardianSnapshot($relationship->guardian),
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

    private function periodSnapshot(Collection $periods, string $label): ?array
    {
        return $periods->firstWhere('label', $label) ?: null;
    }
}
