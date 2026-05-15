<?php

namespace App\Http\Resources\English;

use App\Models\EnglishClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnglishClass */
class EnglishClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classCode' => $this->class_code,
            'name' => $this->name,
            'level' => $this->level,
            'teacherUserId' => $this->teacher_user_id,
            'teacherDisplayName' => $this->teacher_display_name ?? trim(($this->teacher?->first_name ?? '').' '.($this->teacher?->last_name ?? '')),
            'schedule' => $this->schedule,
            'room' => $this->room,
            'status' => $this->status,
            'description' => $this->description,
            'studentIds' => $this->whenLoaded('students', fn (): array => $this->students->pluck('id')->values()->all()),
            'students' => $this->whenLoaded('students', fn (): array => $this->students->map(static function ($student): array {
                return [
                    'id' => $student->id,
                    'studentCode' => $student->student_code,
                    'fullName' => trim($student->first_name.' '.$student->last_name),
                ];
            })->values()->all()),
            'studentsCount' => $this->students_count ?? $this->whenCounted('students'),
            'tasksCount' => $this->tasks_count ?? $this->whenCounted('tasks'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
