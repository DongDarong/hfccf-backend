<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
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
                return [
                    'label' => (string) ($row->label ?? ''),
                    'fromDate' => $row->from_date ?? null,
                    'toDate' => $row->to_date ?? null,
                    'latestAssessmentDate' => $row->to_date ?? null,
                    'assessmentCount' => (int) ($row->assessment_count ?? 0),
                    'studentCount' => (int) ($row->student_count ?? 0),
                    'classCount' => (int) ($row->class_count ?? 0),
                ];
            })
            ->values();
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
}
