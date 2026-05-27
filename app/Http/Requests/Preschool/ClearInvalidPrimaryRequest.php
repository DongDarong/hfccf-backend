<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class ClearInvalidPrimaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required', 'integer', 'exists:preschool_student_guardians,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
