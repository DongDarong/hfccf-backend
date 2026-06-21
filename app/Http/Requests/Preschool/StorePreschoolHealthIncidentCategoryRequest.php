<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class StorePreschoolHealthIncidentCategoryRequest extends PreschoolHealthConfigurationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('preschool_health_incident_categories', 'code')],
            'description' => ['nullable', 'string'],
            'default_severity_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::exists('preschool_health_severity_levels', 'code')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true)),
            ],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
