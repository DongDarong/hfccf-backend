<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolMonthlySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization checked in controller
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:preschool_academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:preschool_classes,id'],
            'assessment_category_id' => ['required', 'integer', 'exists:preschool_assessment_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'Academic year is required.',
            'academic_year_id.exists' => 'Academic year not found.',
            'class_id.required' => 'Class is required.',
            'class_id.exists' => 'Class not found.',
            'assessment_category_id.required' => 'Assessment category is required.',
            'assessment_category_id.exists' => 'Assessment category not found.',
        ];
    }
}
