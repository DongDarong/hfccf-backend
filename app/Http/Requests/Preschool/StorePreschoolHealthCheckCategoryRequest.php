<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class StorePreschoolHealthCheckCategoryRequest extends PreschoolHealthConfigurationRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('preschool_health_check_categories', 'code')],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
