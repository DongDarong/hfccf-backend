<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolAssessmentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
