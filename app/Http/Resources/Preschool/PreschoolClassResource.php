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
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'teacherUserId' => $this->teacher_user_id,
            'teacherDisplayName' => $this->teacher_display_name ?: $this->teacher?->name,
            'level' => $this->level,
            'schedule' => $this->schedule,
            'studentsCount' => $this->students_count ?? $this->students()->count(),
            'status' => $this->status,
            'room' => $this->room,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
