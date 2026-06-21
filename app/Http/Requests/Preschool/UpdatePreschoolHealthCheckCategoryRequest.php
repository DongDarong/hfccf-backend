<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Validation\Rule;

class UpdatePreschoolHealthCheckCategoryRequest extends StorePreschoolHealthCheckCategoryRequest
{
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('preschool_health_check_categories', 'code')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
