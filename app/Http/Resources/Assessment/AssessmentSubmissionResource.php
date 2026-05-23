<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'template_id'     => $this->template_id,
            'form_template_id'=> $this->form_template_id,
            'template'        => $this->whenLoaded('template', fn () => [
                'id'   => $this->template->id,
                'name' => $this->template->name,
            ]),
            'form_template'   => $this->whenLoaded('template', fn () => [
                'id'   => $this->template->id,
                'name' => $this->template->name,
            ]),
            'student_id'      => $this->student_id,
            'student'         => $this->whenLoaded('student', fn () => [
                'id'        => $this->student->id,
                'full_name' => $this->student->full_name,
            ]),
            'assessor_id'     => $this->assessor_id,
            'reviewer_id'     => $this->reviewer_id,
            'approver_id'     => $this->approver_id,
            'status'          => $this->status,
            'module'          => $this->module,
            'review_note'     => $this->review_note,
            'rejection_note'  => $this->rejection_reason,
            'rejection_reason'=> $this->rejection_reason,
            'submitted_at'    => $this->submitted_at?->toIso8601String(),
            'reviewed_at'     => $this->reviewed_at?->toIso8601String(),
            'approved_at'     => $this->approved_at?->toIso8601String(),
            'rejected_at'     => $this->rejected_at?->toIso8601String(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'scores'          => $this->whenLoaded('scores', fn () =>
                $this->scores->map(fn ($score) => [
                    'id'             => $score->id,
                    'scope'          => $score->scope,
                    'scope_id'       => $score->scope_id,
                    'raw_score'      => $score->raw_score,
                    'weighted_score' => $score->weighted_score,
                    'max_score'      => $score->max_score,
                    'percentage'     => $score->percentage,
                    'risk_level_id'  => $score->risk_level_id,
                ])
            ),
            'total_score'     => $this->whenLoaded('scores', fn () =>
                $this->scores->first()?->raw_score
            ),
            'risk_level'      => $this->whenLoaded('riskLevel', fn () =>
                $this->riskLevel ? [
                    'id'          => $this->riskLevel->id,
                    'label'       => $this->riskLevel->label,
                    'level_name'  => $this->riskLevel->label,
                    'color_code'  => $this->riskLevel->color_code,
                    'color'       => $this->riskLevel->color_code,
                ] : null
            ),
        ];
    }
}
