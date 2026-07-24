<?php

namespace App\Services;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use Illuminate\Support\Carbon;

class PreschoolGradeEntryReportService
{
    /**
     * @param  array{academic_year_id:int,class_id:int,month:int,year:int}  $filters
     * @return array<string,mixed>
     */
    public function monthly(array $filters): array
    {
        $class = PreschoolClass::query()->findOrFail((int) $filters['class_id']);
        $academicYear = PreschoolAcademicYear::query()->findOrFail((int) $filters['academic_year_id']);
        $month = (int) $filters['month'];
        $year = (int) $filters['year'];
        $submissionMonth = Carbon::create($year, $month, 1)->toDateString();

        $submission = PreschoolMonthlySubmission::query()
            ->with(['studentAssessments.student'])
            ->where('class_id', $class->id)
            ->where('academic_year_id', $academicYear->id)
            ->whereDate('submission_month', $submissionMonth)
            ->first();

        $assessmentsByStudent = $submission?->studentAssessments
            ? $submission->studentAssessments->keyBy('student_id')
            : collect();

        $students = $this->classRoster($class, $academicYear);

        return [
            'class' => $class,
            'academicYear' => $academicYear,
            'month' => $month,
            'year' => $year,
            'submission' => $submission,
            'students' => $students->map(function (PreschoolStudent $student, int $index) use ($assessmentsByStudent): array {
                $assessment = $assessmentsByStudent->get($student->id);

                return [
                    'number' => $index + 1,
                    'student' => $student,
                    'student_code' => $student->student_code ?: $student->public_id,
                    'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: '-',
                    'latin_name' => $student->latin_name,
                    'gender' => $student->gender,
                    'date_of_birth' => $student->date_of_birth?->toDateString(),
                    'class_name' => $student->classes->first()?->name,
                    'score' => $assessment?->score,
                    'rating' => $assessment?->rating,
                    'status' => $assessment?->status ?? $submission?->status,
                ];
            })->values()->all(),
        ];
    }

    private function classRoster(PreschoolClass $class, PreschoolAcademicYear $academicYear)
    {
        $legacyYearValues = array_values(array_filter([
            $academicYear->label,
            $academicYear->code,
        ]));

        return PreschoolStudent::query()
            ->with(['classes' => static function ($query) use ($class): void {
                $query->where('preschool_classes.id', $class->id);
            }])
            ->whereHas('classes', function ($query) use ($class, $academicYear, $legacyYearValues): void {
                $query->where('preschool_classes.id', $class->id)
                    ->where(static function ($pivotQuery) use ($academicYear, $legacyYearValues): void {
                        $pivotQuery->where('preschool_class_students.academic_year_id', $academicYear->id)
                            ->orWhereNull('preschool_class_students.academic_year_id');

                        if ($legacyYearValues !== []) {
                            $pivotQuery->orWhereIn('preschool_class_students.academic_year', $legacyYearValues);
                        }
                    })
                    ->where(static function ($pivotQuery): void {
                        $pivotQuery->whereNull('preschool_class_students.status')
                            ->orWhere('preschool_class_students.status', 'active');
                    })
                    ->where(static function ($pivotQuery): void {
                        $pivotQuery->whereNull('preschool_class_students.enrollment_status')
                            ->orWhere('preschool_class_students.enrollment_status', 'active');
                    });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('student_code')
            ->get();
    }
}
