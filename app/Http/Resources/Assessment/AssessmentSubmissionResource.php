<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'form_template_id' => $this->form_template_id,
            'form_template'    => $this->whenLoaded('formTemplate', fn () => [
                'id'   => $this->formTemplate->id,
                'name' => $this->formTemplate->name,
            ]),
            'student_id'       => $this->student_id,
            'student'          => $this->whenLoaded('student', fn () => [
                'id'        => $this->student->id,
                'full_name' => $this->student->full_name,
            ]),
            'assessor_id'      => $this->assessor_id,
            'reviewer_id'      => $this->reviewer_id,
            'approver_id'      => $this->approver_id,
            'status'           => $this->status,
            'module'           => $this->module,
            'review_note'      => $this->review_note,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at'     => $this->submitted_at?->toIso8601String(),
            'completed_at'     => $this->completed_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
            'total_score'      => $this->whenLoaded('scores', fn () =>
                $this->scores->first()?->total_score
            ),
            'risk_level'       => $this->whenLoaded('riskLevel', fn () =>
                $this->riskLevel ? [
                    'id'         => $this->riskLevel->id,
                    'level_name' => $this->riskLevel->level_name,
                    'color'      => $this->riskLevel->color,
                ] : null
            ),
        ];
    }
}
