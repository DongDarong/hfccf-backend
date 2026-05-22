<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolClassroomResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'category' => ['required', Rule::in(['books', 'toys', 'equipment', 'supplies', 'digital'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(['good', 'fair', 'poor'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
