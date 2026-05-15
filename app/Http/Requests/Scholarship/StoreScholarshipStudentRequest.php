<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScholarshipStudentRequest extends FormRequest
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
        $user = $this->user();

        return $user !== null && in_array($user->role_code, ['superadmin', 'adminscholarship'], true);
    }

    public function rules(): array
    {
        return [
            'student_code' => ['nullable', 'string', 'max:50', 'unique:scholarship_students,student_code'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'school_name' => ['required', 'string', 'max:191'],
            'grade_level' => ['required', 'string', 'max:100'],
            'guardian_name' => ['required', 'string', 'max:191'],
            'guardian_phone' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'pending', 'inactive', 'graduated', 'archived'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
