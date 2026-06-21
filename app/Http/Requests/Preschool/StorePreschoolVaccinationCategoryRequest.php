<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class StorePreschoolVaccinationCategoryRequest extends PreschoolHealthConfigurationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('preschool_vaccination_categories', 'code')],
            'description' => ['nullable', 'string'],
            'recommended_age_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
