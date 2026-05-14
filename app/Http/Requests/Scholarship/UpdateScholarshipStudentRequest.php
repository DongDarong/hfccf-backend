<?php

namespace App\Http\Requests\Scholarship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarshipStudentRequest extends FormRequest
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
        $studentId = (string) $this->route('id');

        return [
            'student_code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('scholarship_students', 'student_code')->ignore($studentId)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'school_name' => ['sometimes', 'required', 'string', 'max:191'],
            'grade_level' => ['sometimes', 'required', 'string', 'max:100'],
            'guardian_name' => ['sometimes', 'required', 'string', 'max:191'],
            'guardian_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'pending', 'inactive', 'graduated', 'archived'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
