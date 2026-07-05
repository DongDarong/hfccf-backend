<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolAssessmentReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:preschool_academic_years,id'],
            'term_id' => ['required', 'integer', 'exists:preschool_terms,id'],
            'name' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
