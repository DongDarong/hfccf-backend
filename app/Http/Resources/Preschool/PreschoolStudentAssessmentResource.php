<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolStudentAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolStudentAssessment */
class PreschoolStudentAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentId' => $this->student_id,
            'studentName' => trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? '')),
            'studentGender' => $this->student?->gender,
            'studentDateOfBirth' => $this->student?->date_of_birth?->toDateString(),
            'classId' => $this->class_id,
            'className' => $this->preschoolClass?->name,
            'categoryId' => $this->category_id,
            'categoryCode' => $this->category?->code,
            'categoryName' => $this->category?->name,
            'category' => PreschoolAssessmentCategoryResource::make($this->whenLoaded('category'))->resolve($request),
            'assessedByUserId' => $this->assessed_by_user_id,
            'assessedByName' => trim(($this->assessedBy?->first_name ?? '').' '.($this->assessedBy?->last_name ?? '')),
            'periodLabel' => $this->period_label,
            'academicYearId' => $this->academic_year_id,
            'termId' => $this->term_id,
            'academicYear' => $this->academicYear?->label,
            'termLabel' => $this->term?->name,
            'assessmentDate' => $this->assessment_date?->toDateString(),
            'score' => $this->score,
            'rating' => $this->rating,
            'observation' => $this->observation,
            'teacherComment' => $this->teacher_comment,
            'status' => $this->status,
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'finalizedByUserId' => $this->finalized_by_user_id,
            'finalizedByName' => trim(($this->finalizedBy?->first_name ?? '').' '.($this->finalizedBy?->last_name ?? '')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
