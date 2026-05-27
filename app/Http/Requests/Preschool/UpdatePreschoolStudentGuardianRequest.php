<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolStudentGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'relationship_type' => ['sometimes', 'required', 'string', Rule::in(['mother', 'father', 'guardian', 'grandparent', 'sibling', 'relative', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'emergency_priority' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'archived'])],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
