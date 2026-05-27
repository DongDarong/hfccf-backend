<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolGuardianRequest extends FormRequest
{
    /**
     * Guard the new normalized guardian store endpoint so only Preschool
     * admins can create the reusable contact records.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:32'],
            'secondary_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:191'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive', 'archived'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
