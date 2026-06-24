<?php

namespace App\Http\Requests\Preschool;

class UpdatePreschoolAssessmentReportPeriodRequest extends StorePreschoolAssessmentReportPeriodRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => ['sometimes', 'required', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['sometimes', 'required', 'integer', 'exists:preschool_terms,id'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
