<?php

namespace App\Http\Resources\English;

use App\Models\EnglishTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnglishTask */
class EnglishTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classId' => $this->class_id,
            'class' => $this->whenLoaded('class', fn (): array => [
                'id' => $this->class?->id,
                'classCode' => $this->class?->class_code,
                'name' => $this->class?->name,
                'level' => $this->class?->level,
            ]),
            'assignedByUserId' => $this->assigned_by_user_id,
            'assignedByName' => trim(($this->assignedBy?->first_name ?? '').' '.($this->assignedBy?->last_name ?? '')),
            'title' => $this->title,
            'description' => $this->description,
            'dueDate' => $this->due_date?->toDateString(),
            'taskStatus' => $this->task_status,
            'submissionsCount' => $this->submissions_count ?? $this->whenCounted('submissions'),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
