<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolClass */
class PreschoolClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeStudentAssignments = $this->whenLoaded('students', function () {
            return $this->students
                ->filter(static fn ($student) => ($student->pivot->status ?? 'active') === 'active')
                ->values()
                ->map(static function ($student) {
                    return [
                        'id' => $student->id,
                        'studentCode' => $student->student_code,
                        'fullName' => trim($student->first_name.' '.$student->last_name),
                        'status' => $student->pivot->status ?? 'active',
                        'enrolledAt' => $student->pivot->enrolled_at?->toISOString(),
                        'academicYear' => $student->pivot->academic_year,
                        'termLabel' => $student->pivot->term_label,
                        'academicYearId' => $student->pivot->academic_year_id,
                        'termId' => $student->pivot->term_id,
                        'enrollmentStatus' => $student->pivot->enrollment_status ?? $student->pivot->status ?? 'active',
                        'enrollmentStartedAt' => $student->pivot->enrollment_started_at?->toISOString(),
                        'enrollmentEndedAt' => $student->pivot->enrollment_ended_at?->toISOString(),
                        'updatedAt' => $student->pivot->updated_at?->toISOString(),
                    ];
                })
                ->all();
        }, []);

        $allStudentAssignments = $this->whenLoaded('students', function () {
            return $this->students
                ->values()
                ->map(static function ($student) {
                    return [
                        'id' => $student->id,
                        'studentCode' => $student->student_code,
                        'fullName' => trim($student->first_name.' '.$student->last_name),
                        'status' => $student->pivot->status ?? 'active',
                        'enrolledAt' => $student->pivot->enrolled_at?->toISOString(),
                        'academicYear' => $student->pivot->academic_year,
                        'termLabel' => $student->pivot->term_label,
                        'academicYearId' => $student->pivot->academic_year_id,
                        'termId' => $student->pivot->term_id,
                        'enrollmentStatus' => $student->pivot->enrollment_status ?? $student->pivot->status ?? 'active',
                        'enrollmentStartedAt' => $student->pivot->enrollment_started_at?->toISOString(),
                        'enrollmentEndedAt' => $student->pivot->enrollment_ended_at?->toISOString(),
                        'updatedAt' => $student->pivot->updated_at?->toISOString(),
                    ];
                })
                ->all();
        }, []);

        $teacherAssignments = $this->whenLoaded('teacherAssignments', function () {
            return $this->teacherAssignments
                ->values()
                ->map(static function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'classId' => $assignment->class_id,
                        'teacherUserId' => $assignment->teacher_user_id,
                        'teacherDisplayName' => $assignment->teacher_display_name,
                        'status' => $assignment->status ?? 'active',
                        'assignedAt' => $assignment->assigned_at?->toISOString(),
                        'academicYear' => $assignment->academic_year,
                        'termLabel' => $assignment->term_label,
                        'academicYearId' => $assignment->academic_year_id,
                        'termId' => $assignment->term_id,
                        'endedAt' => $assignment->ended_at?->toISOString(),
                        'notes' => $assignment->notes,
                        'updatedAt' => $assignment->updated_at?->toISOString(),
                    ];
                })
                ->all();
        }, []);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'teacherUserId' => $this->teacher_user_id,
            'teacherDisplayName' => $this->teacher_display_name ?: ($this->relationLoaded('teacher') ? $this->teacher?->name : null),
            'level' => $this->level,
            'schedule' => $this->schedule,
            // The visible student count should reflect only active assignments
            // while the studentAssignments payload keeps inactive history rows
            // available for the new assignment workflow page.
            'studentsCount' => $this->students_count ?? $this->students()->wherePivot('status', 'active')->count(),
            'studentAssignments' => $allStudentAssignments,
            'activeStudentAssignments' => $activeStudentAssignments,
            'teacherAssignments' => $teacherAssignments,
            'status' => $this->status,
            'room' => $this->room,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
