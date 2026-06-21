<?php

namespace App\Http\Requests\Preschool;

class UpdatePreschoolAssessmentCategoryRequest extends StorePreschoolAssessmentCategoryRequest
{
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
