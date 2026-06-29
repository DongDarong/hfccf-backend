<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class StorePreschoolClassLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name_en' => trim((string) $this->input('name_en', $this->input('nameEn', ''))),
            'name_kh' => trim((string) $this->input('name_kh', $this->input('nameKh', ''))),
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'sort_order' => $this->input('sort_order', $this->input('sortOrder', 0)),
            'is_active' => $this->input('is_active', $this->input('isActive', true)),
        ]);
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_kh' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', 'unique:preschool_class_levels,code'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
