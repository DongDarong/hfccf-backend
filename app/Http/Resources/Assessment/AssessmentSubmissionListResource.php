<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSubmissionListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'form_template' => $this->whenLoaded('formTemplate', fn () => [
                'id'   => $this->formTemplate->id,
                'name' => $this->formTemplate->name,
            ]),
            'student' => $this->whenLoaded('student', fn () => [
                'id'        => $this->student->id,
                'full_name' => $this->student->full_name,
            ]),
        ];
    }
}
