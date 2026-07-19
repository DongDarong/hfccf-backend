<?php

namespace App\Http\Resources\Preschool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreschoolMonthlySubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'academic_year' => [
                'id' => $this->academicYear?->id,
                'label' => $this->academicYear?->label,
            ],
            'class' => [
                'id' => $this->class?->id,
                'name' => $this->class?->name,
                'code' => $this->class?->code,
            ],
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'code' => $this->category?->code,
            ],
            'submission_month' => $this->submission_month?->format('Y-m-d'),
            'assessment_count' => $this->studentAssessments?->count() ?? 0,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by' => $this->submittedBy ? [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
            ] : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_by' => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null,
            'returned_at' => $this->returned_at?->toIso8601String(),
            'returned_by' => $this->returnedBy ? [
                'id' => $this->returnedBy->id,
                'name' => $this->returnedBy->name,
            ] : null,
            'return_reason' => $this->return_reason,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'finalized_by' => $this->finalizedBy ? [
                'id' => $this->finalizedBy->id,
                'name' => $this->finalizedBy->name,
            ] : null,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
