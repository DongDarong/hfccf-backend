<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolClass;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolClass */
class PreschoolClassResource extends JsonResource
{
    private static function toIsoString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toISOString();
        }

        return Carbon::parse((string) $value)->toISOString();
    }

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
                        'enrolledAt' => self::toIsoString($student->pivot->enrolled_at),
                        'academicYear' => $student->pivot->academic_year,
                        'termLabel' => $student->pivot->term_label,
                        'academicYearId' => $student->pivot->academic_year_id,
                        'termId' => $student->pivot->term_id,
                        'enrollmentStatus' => $student->pivot->enrollment_status ?? $student->pivot->status ?? 'active',
                        'enrollmentStartedAt' => self::toIsoString($student->pivot->enrollment_started_at),
                        'enrollmentEndedAt' => self::toIsoString($student->pivot->enrollment_ended_at),
                        'updatedAt' => self::toIsoString($student->pivot->updated_at),
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
                        'enrolledAt' => self::toIsoString($student->pivot->enrolled_at),
                        'academicYear' => $student->pivot->academic_year,
                        'termLabel' => $student->pivot->term_label,
                        'academicYearId' => $student->pivot->academic_year_id,
                        'termId' => $student->pivot->term_id,
                        'enrollmentStatus' => $student->pivot->enrollment_status ?? $student->pivot->status ?? 'active',
                        'enrollmentStartedAt' => self::toIsoString($student->pivot->enrollment_started_at),
                        'enrollmentEndedAt' => self::toIsoString($student->pivot->enrollment_ended_at),
                        'updatedAt' => self::toIsoString($student->pivot->updated_at),
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
                        'assignedAt' => self::toIsoString($assignment->assigned_at),
                        'academicYear' => $assignment->academic_year,
                        'termLabel' => $assignment->term_label,
                        'academicYearId' => $assignment->academic_year_id,
                        'termId' => $assignment->term_id,
                        'endedAt' => self::toIsoString($assignment->ended_at),
                        'notes' => $assignment->notes,
                        'updatedAt' => self::toIsoString($assignment->updated_at),
                    ];
                })
                ->all();
        }, []);

        $classLevel = $this->whenLoaded('classLevel', function () {
            return [
                'id' => $this->classLevel?->id,
                'nameEn' => $this->classLevel?->name_en,
                'nameKh' => $this->classLevel?->name_kh,
                'code' => $this->classLevel?->code,
                'sortOrder' => $this->classLevel?->sort_order,
                'isActive' => (bool) $this->classLevel?->is_active,
                'status' => $this->classLevel?->is_active ? 'active' : 'inactive',
            ];
        });

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'teacherUserId' => $this->teacher_user_id,
            'teacherDisplayName' => $this->teacher_display_name ?: ($this->relationLoaded('teacher') ? $this->teacher?->name : null),
            'classLevelId' => $this->class_level_id,
            'classLevel' => $classLevel,
            'level' => $this->level ?: ($this->classLevel?->name_en ?? null),
            'schedule' => $this->schedule,
            'studentsCount' => (int) ($this->students_count ?? 0),
            'studentAssignments' => $allStudentAssignments,
            'activeStudentAssignments' => $activeStudentAssignments,
            'teacherAssignments' => $teacherAssignments,
            'status' => $this->status,
            'room' => $this->room,
            'notes' => $this->notes,
            'createdAt' => self::toIsoString($this->created_at),
            'updatedAt' => self::toIsoString($this->updated_at),
            'deletedAt' => self::toIsoString($this->deleted_at),
        ];
    }
}
