<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolStudentGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'integer', 'exists:preschool_guardians,id'],
            'relationship_type' => ['required', 'string', Rule::in(['mother', 'father', 'guardian', 'grandparent', 'sibling', 'relative', 'other'])],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'emergency_priority' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'archived'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
