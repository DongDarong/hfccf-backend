<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreschoolClassroomResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'category' => ['sometimes', 'required', Rule::in(['books', 'toys', 'equipment', 'supplies', 'digital'])],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'condition' => ['sometimes', 'required', Rule::in(['good', 'fair', 'poor'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
