<?php

namespace App\Services;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PreschoolMonthlyAttendanceReportService
{
    /**
     * @param  array{academic_year_id:int,class_id:int,month:int,year:int,date_from:string,date_to:string}  $filters
     * @return array{
     *     class:PreschoolClass,
     *     academicYear:PreschoolAcademicYear,
     *     month:int,
     *     year:int,
     *     dateFrom:Carbon,
     *     dateTo:Carbon,
     *     days:array<int,Carbon>,
     *     roster:Collection<int,PreschoolStudent>,
     *     attendanceRecords:Collection<int,PreschoolAttendanceRecord>,
     *     attendanceLookup:array<string,PreschoolAttendanceRecord>,
     *     studentRows:array<int,array<string,mixed>>,
     *     summary:array{present:int,absent:int,late:int,excused:int,total:int,percentage:int},
     *     totalStudentCount:int,
     *     femaleStudentCount:int,
     *     compatibility:array<string,bool>
     * }
     */
    public function monthly(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'])->startOfDay();
        $to = Carbon::parse($filters['date_to'])->startOfDay();
        $class = PreschoolClass::query()->findOrFail($filters['class_id']);
        $academicYear = PreschoolAcademicYear::query()->findOrFail($filters['academic_year_id']);
        $days = $this->days($from, $to);
        $reportYear = (int) $filters['year'];
        $compatibleAcademicYearIds = $this->compatibleAcademicYearIds($academicYear, $reportYear);
        $legacyAcademicYearTokens = $this->legacyAcademicYearTokens($academicYear, $reportYear);
        $roster = $this->roster($class->id, $compatibleAcademicYearIds, $legacyAcademicYearTokens, $from, $to);
        $records = $this->attendanceRecords($class->id, $compatibleAcademicYearIds, $from, $to);
        $lookup = $this->attendanceLookup($records);
        $studentRows = $this->studentRows($roster, $lookup, $days);

        return [
            'class' => $class,
            'academicYear' => $academicYear,
            'month' => (int) $filters['month'],
            'year' => (int) $filters['year'],
            'dateFrom' => $from,
            'dateTo' => $to,
            'days' => $days,
            'roster' => $roster,
            'attendanceRecords' => $records,
            'attendanceLookup' => $lookup,
            'studentRows' => $studentRows,
            'summary' => $this->summary($records),
            'totalStudentCount' => $roster->count(),
            'femaleStudentCount' => $roster->filter(
                static fn (PreschoolStudent $student): bool => strtolower(trim((string) $student->gender)) === 'female',
            )->count(),
            'compatibility' => [
                'includes_null_academic_year_enrollments' => true,
                'includes_legacy_academic_year_text_enrollments' => true,
                'includes_null_academic_year_attendance_records' => true,
                'includes_same_calendar_year_academic_year_ids' => true,
            ],
        ];
    }

    /**
     * @return array<int,Carbon>
     */
    private function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Canonical roster rule: selected class is the base roster, scoped to the
     * selected academic year while accepting legacy rows that predate normalized
     * academic_year_id storage.
     *
     * @return Collection<int,PreschoolStudent>
     */
    private function roster(int $classId, array $academicYearIds, array $legacyAcademicYearTokens, Carbon $from, Carbon $to): Collection
    {
        $studentIds = PreschoolClassStudent::query()
            ->where('class_id', $classId)
            ->where(function ($query) use ($academicYearIds, $legacyAcademicYearTokens): void {
                $query->whereIn('academic_year_id', $academicYearIds)
                    ->orWhereNull('academic_year_id');

                foreach ($legacyAcademicYearTokens as $token) {
                    $query->orWhere('academic_year', $token)
                        ->orWhere('academic_year', 'like', '%'.$token.'%');
                }
            })
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('enrollment_status', 'active')
                    ->orWhereNull('enrollment_status');
            })
            ->where(function ($query) use ($to): void {
                $query->whereNull('enrollment_started_at')
                    ->orWhereDate('enrollment_started_at', '<=', $to->toDateString());
            })
            ->where(function ($query) use ($from): void {
                $query->whereNull('enrollment_ended_at')
                    ->orWhereDate('enrollment_ended_at', '>=', $from->toDateString());
            })
            ->pluck('student_id')
            ->unique()
            ->values();

        return PreschoolStudent::query()
            ->whereIn('id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('student_code')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int,PreschoolAttendanceRecord>
     */
    private function attendanceRecords(int $classId, array $academicYearIds, Carbon $from, Carbon $to): Collection
    {
        return PreschoolAttendanceRecord::query()
            ->with(['student', 'preschoolClass', 'recordedBy', 'attendanceSession.schedule', 'academicYear', 'term'])
            ->where('class_id', $classId)
            ->where(function ($query) use ($academicYearIds): void {
                $query->whereIn('academic_year_id', $academicYearIds)
                    ->orWhereNull('academic_year_id');
            })
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int,PreschoolAttendanceRecord>  $records
     * @return array<string,PreschoolAttendanceRecord>
     */
    private function attendanceLookup(Collection $records): array
    {
        $lookup = [];

        foreach ($records as $record) {
            $date = $record->attendance_date?->toDateString();
            if ($date === null) {
                continue;
            }

            $lookup[$record->student_id.'|'.$date] = $record;
        }

        return $lookup;
    }

    /**
     * @param  Collection<int,PreschoolStudent>  $students
     * @param  array<string,PreschoolAttendanceRecord>  $recordsByStudentDate
     * @param  array<int,Carbon>  $days
     * @return array<int,array<string,mixed>>
     */
    private function studentRows(Collection $students, array $recordsByStudentDate, array $days): array
    {
        return $students->values()->map(function (PreschoolStudent $student) use ($days, $recordsByStudentDate): array {
            $daily = [];
            $totals = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

            foreach ($days as $day) {
                $record = $recordsByStudentDate[$student->id.'|'.$day->toDateString()] ?? null;
                $status = $record?->status;

                if (array_key_exists((string) $status, $totals)) {
                    $totals[$status]++;
                }

                $daily[] = $this->statusMark($status);
            }

            $total = array_sum($totals);
            $percentage = $total > 0 ? (int) round(($totals['present'] / $total) * 100) : 0;

            return [
                'student' => $student,
                'daily' => $daily,
                'totals' => $totals,
                'recordTotal' => $total,
                'percentage' => $percentage,
            ];
        })->all();
    }

    /**
     * @param  Collection<int,PreschoolAttendanceRecord>  $records
     * @return array{present:int,absent:int,late:int,excused:int,total:int,percentage:int}
     */
    private function summary(Collection $records): array
    {
        $total = $records->count();
        $present = $records->where('status', 'present')->count();

        return [
            'present' => $present,
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total' => $total,
            'percentage' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
        ];
    }

    private function statusMark(?string $status): string
    {
        return match ($status) {
            'present' => 'P',
            'absent' => 'A',
            'late' => 'L',
            'excused' => 'E',
            default => '',
        };
    }

    /**
     * Some production-era rows point to a different normalized academic-year ID
     * while their label/code still identifies the same calendar year. Keep the
     * report scoped to the selected class and month, but accept those equivalent
     * academic-year rows so roster and attendance records resolve consistently.
     *
     * @return array<int,int>
     */
    private function compatibleAcademicYearIds(PreschoolAcademicYear $academicYear, int $reportYear): array
    {
        return PreschoolAcademicYear::query()
            ->where('id', $academicYear->id)
            ->orWhere('label', 'like', '%'.$reportYear.'%')
            ->orWhere('code', 'like', '%'.$reportYear.'%')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function legacyAcademicYearTokens(PreschoolAcademicYear $academicYear, int $reportYear): array
    {
        return collect([$academicYear->label, $academicYear->code, (string) $reportYear])
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->values()
            ->all();
    }
}
