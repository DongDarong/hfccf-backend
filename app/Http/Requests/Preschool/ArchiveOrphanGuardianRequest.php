<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveOrphanGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'integer', 'exists:preschool_guardians,id'],
            'confirmed' => ['required', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
