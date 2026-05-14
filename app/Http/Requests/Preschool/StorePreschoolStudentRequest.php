<?php

namespace App\Http\Requests\Preschool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreschoolStudentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));

        if ($name !== '' && ! $this->filled('first_name') && ! $this->filled('last_name')) {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $this->merge([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->hasPreschoolAdminAccess();
    }

    private function hasPreschoolAdminAccess(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminpreschool'], true);
    }

    public function rules(): array
    {
        return [
            'student_code' => ['nullable', 'string', 'max:50', 'unique:preschool_students,student_code'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'guardian_name' => ['nullable', 'string', 'max:191'],
            'guardian_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'pending', 'inactive', 'graduated'])],
            'class_ids' => ['sometimes', 'array'],
            'class_ids.*' => ['integer', 'exists:preschool_classes,id'],
        ];
    }
}
