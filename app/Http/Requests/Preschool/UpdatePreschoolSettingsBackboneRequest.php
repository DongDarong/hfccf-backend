<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreschoolSettingsBackboneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'academicYear' => ['sometimes', 'array'],
            'terms' => ['sometimes', 'array'],
            'classConfigurations' => ['sometimes', 'array'],
            'attendance' => ['sometimes', 'array'],
            'assessment' => ['sometimes', 'array'],
            'schedule' => ['sometimes', 'array'],
            'enrollment' => ['sometimes', 'array'],
            'payment' => ['sometimes', 'array'],
            'health' => ['sometimes', 'array'],
            'preferences' => ['sometimes', 'array'],
            'groups' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
