<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAssessmentReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAssessmentReportPeriod */
class PreschoolAssessmentReportPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_type' => $this->period_type,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->academicYear?->label ?: $this->academicYear?->code,
            'term_id' => $this->term_id,
            'term_name' => $this->term?->name ?: $this->term?->code,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
