<?php

namespace App\Http\Resources\English;

use App\Models\EnglishTaskSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnglishTaskSubmission */
class EnglishSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'taskId' => $this->task_id,
            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task?->id,
                'title' => $this->task?->title,
                'taskStatus' => $this->task?->task_status,
            ]),
            'studentId' => $this->student_id,
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student?->id,
                'studentCode' => $this->student?->student_code,
                'fullName' => trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? '')),
            ]),
            'submissionText' => $this->submission_text,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'submissionStatus' => $this->submission_status,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'reviewedByUserId' => $this->reviewed_by_user_id,
            'reviewedByName' => trim(($this->reviewedBy?->first_name ?? '').' '.($this->reviewedBy?->last_name ?? '')),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
