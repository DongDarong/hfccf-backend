<?php

namespace App\Http\Resources\Dsam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'uuid'                => $this->uuid,
            'form_template_id'    => $this->form_template_id,
            'student_id'          => $this->student_id,
            'academic_year_id'    => $this->academic_year_id,
            'status'              => $this->status,
            'current_step'        => $this->current_step,
            // Scores
            'total_score'         => $this->total_score !== null ? (float) $this->total_score : null,
            'max_possible_score'  => $this->max_possible_score !== null ? (float) $this->max_possible_score : null,
            'score_percentage'    => $this->score_percentage !== null ? round((float) $this->score_percentage, 2) : null,
            'risk_level'          => $this->risk_level,
            'risk_color'          => $this->risk_level ? $this->riskColor() : null,
            // Workflow flags
            'can_edit'            => $this->canBeEdited(),
            'is_approved'         => $this->isApproved(),
            // Notes
            'submission_notes'    => $this->submission_notes,
            'rejection_reason'    => $this->rejection_reason,
            // Relations
            'form_template'       => $this->whenLoaded('formTemplate', fn () => [
                'id'             => $this->formTemplate->id,
                'uuid'           => $this->formTemplate->uuid,
                'name'           => $this->formTemplate->name,
                'name_kh'        => $this->formTemplate->name_kh,
                'version_number' => $this->formTemplate->version_number,
                'risk_config'    => $this->formTemplate->resolvedRiskConfig(),
                'sections'       => FormSectionResource::collection(
                    $this->whenLoaded('formTemplate', fn () => $this->formTemplate->relationLoaded('sections')
                        ? $this->formTemplate->sections
                        : null
                    ) ?? []
                ),
            ]),
            'student'             => $this->whenLoaded('student', fn () => [
                'id'           => $this->student->id,
                'student_code' => $this->student->student_code,
                'full_name'    => trim($this->student->first_name.' '.$this->student->last_name),
                'gender'       => $this->student->gender,
                'avatar'       => $this->student->avatar,
            ]),
            'academic_year'       => new AcademicYearResource($this->whenLoaded('academicYear')),
            'answers'             => AnswerResource::collection($this->whenLoaded('answers')),
            'scores'              => SubmissionScoreResource::collection($this->whenLoaded('scores')),
            'approvals'           => SubmissionApprovalResource::collection($this->whenLoaded('approvals')),
            'submitted_by'        => $this->whenLoaded('submittedBy', fn () => [
                'id'   => $this->submittedBy->id,
                'name' => trim($this->submittedBy->first_name.' '.$this->submittedBy->last_name),
            ]),
            'approved_by'         => $this->whenLoaded('approvedBy', fn () => [
                'id'   => $this->approvedBy->id,
                'name' => trim($this->approvedBy->first_name.' '.$this->approvedBy->last_name),
            ]),
            'submitted_at'        => $this->submitted_at?->toIso8601String(),
            'approved_at'         => $this->approved_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
